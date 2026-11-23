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
