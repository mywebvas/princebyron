<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PageController extends Controller
{
    /**
     * Standard Catalog Data (Fictional demonstration data with sample pricing).
     */
    protected function getProducts()
    {
        return [
            'tee' => [
                'id' => 'tee',
                'slug' => 'tee',
                'title' => 'Prince Byron Signature Tee',
                'category' => 'Apparel',
                'priceCents' => 3200,
                'sku' => 'PB-TEE-001',
                'image' => 'images/products/pb-tee.jpg',
                'desc' => 'Heavyweight combed cotton jersey featuring debossed gold scripture typography.',
                'colors' => ['Sand Linen', 'Charcoal Obsidian', 'Terracotta'],
                'sizes' => ['S', 'M', 'L', 'XL', 'XXL'],
                'inStock' => true,
                'stockCount' => 18,
                'specs' => [
                    '100% heavyweight ring-spun combed cotton (260 GSM).',
                    'Relaxed architectural silhouette with drop shoulders.',
                    'Screen-printed gold scripture and signature monogram crest.',
                    'Preshrunk fabric with durable ribbed collar and double-needle seams.',
                ],
            ],
            'hoodie' => [
                'id' => 'hoodie',
                'slug' => 'hoodie',
                'title' => 'Abide Arch Pullover Hoodie',
                'category' => 'Apparel',
                'priceCents' => 6800,
                'sku' => 'PB-HD-002',
                'image' => 'images/products/pb-hoodie.jpg',
                'desc' => 'Double-fleece brushed interior with subtle tonal embroidered cross and relaxed drape.',
                'colors' => ['Stone Gray', 'Charcoal Obsidian', 'Desert Taupe'],
                'sizes' => ['S', 'M', 'L', 'XL', 'XXL'],
                'inStock' => true,
                'stockCount' => 12,
                'specs' => [
                    '450 GSM ultra-heavyweight brushed French terry cotton.',
                    'Tonal precision embroidery at center chest and wrist cuff.',
                    'Double-lined hood with custom antique metal aglet drawstrings.',
                    'Kangaroo front pocket with hidden reinforced seams.',
                ],
            ],
            'cap' => [
                'id' => 'cap',
                'slug' => 'cap',
                'title' => 'PB Monogram Curved Brim Cap',
                'category' => 'Accessories',
                'priceCents' => 2800,
                'sku' => 'PB-CAP-003',
                'image' => 'images/products/pb-cap.jpg',
                'desc' => 'Unstructured 6-panel silhouette with antique brass buckle and gold monogram embroidery.',
                'colors' => ['Cocoa Brown', 'Washed Obsidian', 'Desert Sand'],
                'sizes' => ['One Size Fits All (Adjustable)'],
                'inStock' => true,
                'stockCount' => 24,
                'specs' => [
                    'Washed vintage cotton twill with soft low-profile crown.',
                    'Custom antique brass slider buckle with tuck-in strap.',
                    'Precision gold metallic chain-stitch monogram embroidery.',
                    'Pre-curved bill with interior moisture-wicking sweatband.',
                ],
            ],
            'tote' => [
                'id' => 'tote',
                'slug' => 'tote',
                'title' => 'Carry Hope Heavyweight Canvas Tote',
                'category' => 'Accessories',
                'priceCents' => 2500,
                'sku' => 'PB-TOTE-004',
                'image' => 'images/products/pb-tote.jpg',
                'desc' => 'Durable reinforced canvas bag with interior zipper pocket and lyric print.',
                'colors' => ['Natural Ecru Canvas'],
                'sizes' => ['Standard (18L Capacity)'],
                'inStock' => true,
                'stockCount' => 35,
                'specs' => [
                    '16 oz heavy natural ecru canvas with reinforced box stitching.',
                    'Interior brass zippered pocket for essentials and audio gear.',
                    'Screen-printed "CARRY HOPE" typography stanzas.',
                    'Generous 18L capacity with 26-inch reinforced shoulder handles.',
                ],
            ],
            'print' => [
                'id' => 'print',
                'slug' => 'print',
                'title' => 'Faith in the In-Between Art Print',
                'category' => 'Music Art',
                'priceCents' => 3500,
                'sku' => 'PB-ART-005',
                'image' => 'images/music/faith-for-the-in-between-album.jpg',
                'desc' => 'Museum-grade heavy rag paper with debossed artist seal and lyric stanza.',
                'colors' => ['Natural Archival Rag'],
                'sizes' => ['12x18 in (Unframed)'],
                'inStock' => true,
                'stockCount' => 15,
                'specs' => [
                    '310 GSM Hahnemühle German etching archival rag paper.',
                    'Blind debossed artist crest and individualized serial imprint.',
                    'Rich mineral pigment archival giclée print.',
                    'Standard 12x18 in dimension suited for gallery framing.',
                ],
            ],
            'bundle' => [
                'id' => 'bundle',
                'slug' => 'bundle',
                'title' => 'Everyday Faith Ministry Bundle',
                'category' => 'Bundles',
                'priceCents' => 11000,
                'sku' => 'PB-BND-006',
                'image' => 'images/products/pb-tee.jpg',
                'desc' => 'Includes Signature Tee, Monogram Cap, Art Print, and Full 5-Track Digital Collection.',
                'colors' => ['Curated Gift Set'],
                'sizes' => ['Tee Sizes S-XXL Available'],
                'inStock' => true,
                'stockCount' => 8,
                'specs' => [
                    'Curated gift bundle containing physical merch and digital audio.',
                    'Includes Signature Tee, Monogram Cap, and Archival 12x18 Print.',
                    'Immediate high-res 24-bit WAV & 320kbps MP3 digital download access.',
                ],
            ],
        ];
    }

    protected function getSongs()
    {
        return [
            [
                'index' => '01',
                'title' => 'Free',
                'subtitle' => 'Lead Single • MP3 + 24-bit WAV • Duration 4:18',
                'note' => 'A reminder that grace makes room for every beginning.',
                'priceCents' => 0,
                'isFree' => true,
                'tag' => 'Free Download',
            ],
            [
                'index' => '02',
                'title' => 'Purify Me',
                'subtitle' => 'Worship Single • MP3 + 24-bit WAV • Duration 5:02',
                'note' => 'A quiet prayer for a clear and steadfast heart.',
                'priceCents' => 199,
                'isFree' => false,
                'tag' => '$1.99 USD',
            ],
            [
                'index' => '03',
                'title' => 'I Always Win',
                'subtitle' => 'Praise Anthem • MP3 + 24-bit WAV • Duration 3:45',
                'note' => 'An anthem of triumphant hope through every trial.',
                'priceCents' => 199,
                'isFree' => false,
                'tag' => '$1.99 USD',
            ],
            [
                'index' => '04',
                'title' => 'Toduyun',
                'subtitle' => 'Afro-Gospel Celebration • MP3 + 24-bit WAV • Duration 4:32',
                'note' => 'A vibrant celebration of joyful praise and thanksgiving.',
                'priceCents' => 199,
                'isFree' => false,
                'tag' => '$1.99 USD',
            ],
            [
                'index' => '05',
                'title' => 'Follow Me',
                'subtitle' => 'Soulful Discipleship • MP3 + 24-bit WAV • Duration 4:54',
                'note' => 'A soulful call to walk in faith, one day at a time.',
                'priceCents' => 199,
                'isFree' => false,
                'tag' => '$1.99 USD',
            ],
        ];
    }

    public function home()
    {
        return view('pages.home', [
            'title' => 'Prince Byron — Official Gospel Music, Ministry & Store',
            'products' => array_slice($this->getProducts(), 0, 3),
            'songs' => $this->getSongs(),
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
            'songs' => $this->getSongs(),
        ]);
    }

    public function store()
    {
        return view('pages.store', [
            'title' => 'Official Store & Merchandise — Prince Byron',
            'products' => $this->getProducts(),
        ]);
    }

    public function product($slug = 'tee')
    {
        $products = $this->getProducts();
        $slugAliases = [
            'prince-byron-t-shirt' => 'tee',
            'prince-byron-hoodie' => 'hoodie',
            'prince-byron-cap' => 'cap',
            'prince-byron-tote-bag' => 'tote',
            'prince-byron-art-print' => 'print',
            'prince-byron-sticker-pack' => 'sticker',
        ];
        $resolvedKey = $slugAliases[$slug] ?? $slug;
        $product = $products[$resolvedKey] ?? $products[$slug] ?? $products['tee'];

        return view('pages.product', [
            'title' => $product['title'] . ' — Prince Byron Store',
            'product' => $product,
            'relatedProducts' => array_slice($products, 0, 3),
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
            'songs' => $this->getSongs(),
            'products' => $this->getProducts(),
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
