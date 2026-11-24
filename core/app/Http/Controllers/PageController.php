<?php

namespace App\Http\Controllers;

use App\Services\CatalogService;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function __construct(
        protected CatalogService $catalog
    ) {}

    public function home()
    {
        return view('pages.home', [
            'title' => 'Prince Byron — Official Gospel Music, Ministry & Store',
            'products' => array_slice($this->catalog->products(), 0, 3),
            'songs' => $this->catalog->songs(),
        ]);
    }

    public function about()
    {
        return view('pages.about', [
            'title' => 'Our Story & Ministry Vision — Prince Byron',
        ]);
    }

    public function music()
    {
        return view('pages.music', [
            'title' => 'The Complete Music Experience — Prince Byron',
            'songs' => $this->catalog->songs(),

            // Commercial policy comes from config/commerce.php rather than
            // being hardcoded in the template, so commercial rules can change
            // without touching the view.
            'formats' => config('commerce.formats'),
            'samplePricing' => config('commerce.sample_pricing'),
            'samplePricingNotice' => config('commerce.sample_pricing_notice'),
            'seo' => [
                'title' => 'The Complete Music Experience — Prince Byron',
                'description' => 'Stream previews and buy Prince Byron releases as instant MP3 downloads. Gospel, soul and worship.',
                'image' => 'images/music/faith-for-the-in-between-album.jpg',
                'type' => 'website',
                // The catalogue is what a music search should surface: an
                // ItemList of MusicRecording entries, one per published song.
                'schema' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'ItemList',
                    'name' => 'Prince Byron — releases',
                    'itemListElement' => collect($this->catalog->songs())
                        ->values()
                        ->map(fn ($song, $i) => [
                            '@type' => 'ListItem',
                            'position' => $i + 1,
                            'item' => [
                                '@type' => 'MusicRecording',
                                'name' => $song['title'],
                                'byArtist' => ['@type' => 'MusicGroup', 'name' => 'Prince Byron'],
                                'url' => url('/music'),
                            ],
                        ])
                        ->all(),
                ],
            ],
        ]);
    }

    public function store()
    {
        return view('pages.store', [
            'title' => 'Official Store & Merchandise — Prince Byron',
            'products' => $this->catalog->products(),
            'samplePricing' => config('commerce.sample_pricing'),
            'samplePricingNotice' => config('commerce.sample_pricing_notice'),
        ]);
    }

    public function product(string $slug)
    {
        $products = $this->catalog->products();

        // An unknown slug used to render the first product, so a mistyped or
        // delisted link silently sold the wrong item. It must 404.
        if (! isset($products[$slug])) {
            abort(404);
        }

        $product = $products[$slug];

        // Related items: other products, never the one being viewed.
        $related = array_values(array_filter(
            $products,
            fn ($p) => $p['slug'] !== $slug
        ));

        return view('pages.product', [
            'title' => $product['title'] . ' — Prince Byron Store',
            'product' => $product,
            'relatedProducts' => array_slice($related, 0, 3),
            'samplePricing' => config('commerce.sample_pricing'),
            'samplePricingNotice' => config('commerce.sample_pricing_notice'),

            // P2-4: a product page that describes itself in structured data is
            // what lets a search result show a price instead of a bare link.
            // The sample-catalogue items are deliberately excluded: advertising
            // a structured `Offer` for something that cannot be bought is the
            // machine-readable version of the false-advertising defect (P2-8).
            'seo' => [
                'title' => $product['title'] . ' — Prince Byron Store',
                'description' => $product['desc'] ?? null,
                'image' => $product['image'] ?? null,
                'type' => 'product',
                'noindex' => ! $product['purchasable'],
                'schema' => $product['purchasable'] ? [
                    '@context' => 'https://schema.org',
                    '@type' => 'Product',
                    'name' => $product['title'],
                    'description' => $product['desc'] ?? '',
                    'image' => asset($product['image']),
                    'brand' => ['@type' => 'Brand', 'name' => 'Prince Byron'],
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => number_format($product['priceCents'] / 100, 2, '.', ''),
                        'priceCurrency' => 'USD',
                        'availability' => 'https://schema.org/InStock',
                        'url' => url()->current(),
                    ],
                ] : null,
            ],
        ]);
    }

    public function cart()
    {
        return view('pages.cart', [
            'title' => 'Your Shopping Bag — Prince Byron Store',
        ]);
    }

    public function checkout()
    {
        return view('pages.checkout', [
            'title' => 'Dedicated USD Checkout — Prince Byron Store',
        ]);
    }

    public function booking()
    {
        return view('pages.booking', [
            'title' => 'Ministry Appearance & Booking RFQ — Prince Byron',
        ]);
    }

    /**
     * Legal pages (Phase 6).
     *
     * WHY THESE EXIST AS OWNER-EDITABLE CONTENT AND NOT AS HARD-CODED BLADE
     * ---------------------------------------------------------------------
     * A store that takes money must publish refund, privacy, shipping and terms
     * pages. Card processors require them, and several jurisdictions require a
     * cancellation notice for digital goods. None of that text can be invented
     * here: it depends on the owner's jurisdiction, entity and processor terms.
     *
     * So each page renders owner-written content when it exists and an explicitly
     * marked template when it does not. A page that silently reads like real
     * policy would be worse than no page at all.
     */
    public function legal(string $slug)
    {
        $page = \App\Support\LegalPage::find($slug);

        abort_if($page === null, 404);

        return view('pages.legal', [
            'title' => $page->title().' — Prince Byron',
            'slug' => $slug,
            'page' => $page,
            // The layout reads $seo[...], not a top-level variable. Passing
            // 'noindex' directly silently did nothing, which is the kind of bug
            // that looks correct in a browser and only shows up in the HTML.
            'seo' => [
                'title' => $page->title().' — Prince Byron',
                'description' => $page->description(),
                'noindex' => $page->isPlaceholder(),
            ],
        ]);
    }

    public function account()
    {
        return view('pages.account', [
            'title' => 'Customer Fan Vault & Entitlements — Prince Byron',
        ]);
    }

    public function adminPreview()
    {
        return view('pages.admin-preview', [
            'title' => 'Backoffice Management Console (Simulation) — Prince Byron',
            'songs' => $this->catalog->songs(),
            'products' => $this->catalog->products(),
        ]);
    }

    /**
     * Immediate free promotional preview download for lead single.
     */
    public function downloadFreePreview()
    {
        $filePath = storage_path('app/private/audio/free.mp3');
        if (!file_exists($filePath)) {
            $filePath = public_path('audio/Prince_Byron_Free_Lead_Track.mp3');
        }

        return response()->download($filePath, 'Prince_Byron_Free_Lead_Track.mp3', [
            'Content-Type' => 'audio/mpeg',
            'Cache-Control' => 'no-cache, private',
        ]);
    }
}
