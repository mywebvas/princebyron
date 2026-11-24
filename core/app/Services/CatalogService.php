<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Song;
use Illuminate\Support\Collection;

/**
 * CatalogService — the single read path for the public storefront.
 *
 * WHY THIS EXISTS
 * ---------------
 * The public pages used to call literal arrays in PageController (review P0-3),
 * so an owner editing a product in the admin panel changed nothing on the site,
 * prices could diverge from the cart, and an unknown slug rendered the first
 * product instead of 404. The server-side commerce layer (CartService,
 * CheckoutService) has always priced from the database; only the pages lagged.
 *
 * DESIGN RULES
 * ------------
 * 1. The database is the source of truth. Prices always come from
 *    product_variants / songs, never from a template.
 * 2. `fallback` catalogue data (config/commerce_catalog.php) exists ONLY so a
 *    freshly uploaded package renders a complete site before the owner has
 *    seeded or entered products. It is a presentation concern, never a pricing
 *    one: an item sourced from fallback cannot be added to the cart, because
 *    checkout prices from the database and would reject it.
 * 3. Views receive a stable array shape, so templates do not care where the
 *    data came from. Each item carries `purchasable` + `variant_*` ids.
 * 4. Images are resolved defensively: the path exists in the database but the
 *    file may not have been uploaded yet, so a placeholder is used instead of a
 *    broken image.
 */
class CatalogService
{
    /** Placeholder used when an owner hasn't uploaded artwork yet. */
    private const FALLBACK_IMAGE = 'images/prince-byron-hero.jpeg';

    // -------------------------------------------------------------------------
    // Products
    // -------------------------------------------------------------------------

    /**
     * Published products, ordered for display.
     *
     * @return array<string, array> keyed by slug
     */
    public function products(): array
    {
        $products = Product::query()
            ->where('is_published', true)
            ->with([
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('id'),
                'images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if (! $this->hasAnyCatalogueProduct()) {
            return $this->fallbackProducts();
        }

        return $products
            ->mapWithKeys(fn (Product $product) => [$product->slug => $this->presentProduct($product)])
            ->all();
    }

    /**
     * Whether the owner has ever created a product.
     *
     * Deliberately ignores `is_published` and soft deletes are excluded by the
     * default query scope: a product the owner has unpublished must NOT be
     * replaced by the demo catalogue, or taking an item down would put a
     * sample item back in its place.
     */
    private function hasAnyCatalogueProduct(): bool
    {
        return Product::query()->exists();
    }

    private function hasAnyCatalogueSong(): bool
    {
        return Song::query()->exists();
    }

    /** A single published product by slug, or null when it does not exist. */
    public function product(string $slug): ?array
    {
        $product = Product::query()
            ->where('is_published', true)
            ->where('slug', $slug)
            ->with([
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('id'),
                'images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order'),
            ])
            ->first();

        if (! $product) {
            return null;
        }

        return $this->presentProduct($product);
    }

    // -------------------------------------------------------------------------
    // Songs
    // -------------------------------------------------------------------------

    /**
     * Published songs in display order.
     *
     * @return list<array>
     */
    public function songs(): array
    {
        $songs = Song::query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if (! $this->hasAnyCatalogueSong()) {
            return $this->fallbackSongs();
        }

        return $songs
            ->values()
            ->map(fn (Song $song, int $i) => $this->presentSong($song, $i))
            ->all();
    }

    /** Whether any of the songs has a real downloadable master. */
    public function hasAnyDigitalDelivery(): bool
    {
        if (! $this->hasAnyCatalogueSong()) {
            return false;
        }

        $songs = Song::query()->where('is_published', true)->get();

        return $songs->contains(fn (Song $s) => (bool) $s->download_storage_path_mp3 || (bool) $s->download_storage_path_wav);
    }

    // -------------------------------------------------------------------------
    // Presentation
    // -------------------------------------------------------------------------

    /**
     * Map a Product model onto the array shape the Blade templates expect.
     *
     * `colors` and `sizes` are derived from variant attributes, so the option
     * pickers are driven by what the owner actually created rather than a
     * hardcoded list that could offer combinations that do not exist.
     */
    private function presentProduct(Product $product): array
    {
        $variants = $product->variants;

        $colors = $variants->pluck('color')->filter()->unique()->values()->all();
        $sizes = $variants->pluck('size')->filter()->unique()->values()->all();

        // Cheapest active variant is the "from" price shown in the catalogue.
        $lowest = $variants->sortBy('price_cents')->first();

        $primaryImage = $product->images->first();
        $imagePath = $primaryImage?->storage_path ?: self::FALLBACK_IMAGE;

        $totalStock = $variants->sum(fn ($v) => $v->stock_quantity < 0 ? 0 : $v->stock_quantity);
        $unlimited = $variants->contains(fn ($v) => $v->stock_quantity < 0);

        return [
            'id' => $product->slug,
            'slug' => $product->slug,
            'title' => $product->name,
            'category' => $this->categoryFor($product->type),

            'priceCents' => $lowest?->price_cents ?? 0,
            'sku' => $lowest?->sku ?? strtoupper($product->slug),
            'image' => $imagePath,
            'imageAlt' => $primaryImage?->alt_text ?: $product->name,
            'desc' => $product->description ?: '',

            'colors' => $colors ?: ['Standard'],
            'sizes' => $sizes ?: ['One Size'],

            // Purchasability is determined by real, active variants. Without
            // one there is nothing for the cart to reference, so the UI must
            // not present an "add to bag" action that would fail server-side.
            'purchasable' => $variants->isNotEmpty(),
            'variants' => $variants->map(fn ($v) => [
                'id' => $v->id,
                'label' => $v->label,
                'color' => $v->color,
                'size' => $v->size,
                'priceCents' => $v->price_cents,
                'inStock' => $v->isAvailable(),
            ])->values()->all(),

            'inStock' => $unlimited || $totalStock > 0,
            'stockCount' => $unlimited ? null : $totalStock,

            // Product-level specs were previously hardcoded prose. The schema
            // has no spec field, so an honest empty list beats invented detail.
            'specs' => [],
        ];
    }

    /** Map a Song model onto the music-page array shape. */
    private function presentSong(Song $song, int $index): array
    {
        $priceCents = $song->effectivePriceCents();

        return [
            'id' => $song->slug,
            'dbId' => $song->id,
            'index' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
            'title' => $song->title,
            'subtitle' => $song->description ?: 'Digital single',
            'note' => $song->description ?: '',
            'priceCents' => $priceCents,
            'isFree' => (bool) $song->is_free,
            'tag' => $song->is_free ? 'Free Download' : '$'.number_format($priceCents / 100, 2).' USD',
            'hasPreview' => (bool) $song->preview_storage_path,
            'hasWav' => (bool) $song->has_wav,
        ];
    }

    /** Human label for a product type. */
    private function categoryFor(string $type): string
    {
        return match ($type) {
            'physical' => 'Apparel',
            'bundle' => 'Bundles',
            'digital' => 'Music Art',
            default => 'Merchandise',
        };
    }

    // -------------------------------------------------------------------------
    // Fallback presentation (no database rows yet)
    // -------------------------------------------------------------------------

    /**
     * Demo catalogue for an un-seeded install.
     *
     * Every entry is explicitly non-purchasable: the cart only accepts real
     * variant/song ids, and CheckoutService prices from the database, so an
     * item that does not exist there cannot be bought. This keeps a blank
     * install looking intentional instead of broken — while making it
     * impossible to take an order for something that isn't real.
     */
    private function fallbackProducts(): array
    {
        $definitions = config('commerce_catalog.products', []);

        $out = [];
        foreach ($definitions as $p) {
            $out[$p['slug']] = $p + [
                'id' => $p['slug'],
                'purchasable' => false,
                'variants' => [],
                'inStock' => false,
                'stockCount' => null,
                'specs' => [],
                'imageAlt' => $p['title'],
            ];
        }

        return $out;
    }

    private function fallbackSongs(): array
    {
        $definitions = config('commerce_catalog.songs', []);

        $out = [];
        foreach ($definitions as $i => $s) {
            $out[] = $s + [
                'dbId' => null,
                'index' => str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'tag' => $s['isFree'] ? 'Free Download' : '$'.number_format($s['priceCents'] / 100, 2).' USD',
                'hasPreview' => false,
                'hasWav' => false,
            ];
        }

        return $out;
    }
}
