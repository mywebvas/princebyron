@props([
    'title' => null,
    'description' => null,
    'image' => null,          // path under public/, e.g. images/music/album.jpg
    'type' => 'website',      // og:type — 'website' | 'music.song' | 'product'
    'noindex' => false,
    'schema' => null,         // array for JSON-LD; omit for site defaults
])

@php
    use App\Support\ResponsiveImage;

    $siteName = 'Prince Byron';
    $tagline = 'Official Gospel Music, Ministry & Store';

    $pageTitle = $title
        ? $title
        : $siteName.' — '.$tagline;
    $pageDescription = $description
        ?: 'Prince Byron — gospel music, ministry bookings, apparel and instant downloads. A sound for every season.';

    // Canonical strips the query string: paginated or tracked URLs should all
    // point at the same document, otherwise the search engine sees duplicates
    // and splits what little ranking authority a new site has.
    $canonical = url()->current();

    $imageUrl = $image ? asset($image) : asset('images/prince-byron-hero.jpeg');
    $dims = $image ? ResponsiveImage::dimensions($image) : ResponsiveImage::dimensions('images/prince-byron-hero.jpeg');
    $imageWidth = $dims[0] ?? 1200;
    $imageHeight = $dims[1] ?? 630;

    $socials = [
        'https://open.spotify.com/artist/089TupQTFoR8OvKrDOp1NA',
        'https://www.youtube.com/channel/UCeeZS5FkmYj6F4fwykc7f0g',
        'https://www.tiktok.com/@princebyron94',
        'https://www.facebook.com/princebyron89/',
    ];

    // Default structured data: who this is, and where else they exist. `sameAs`
    // is what lets a search engine connect this domain to the artist's profiles
    // — without it the site is an unlinked island even though the footer links
    // out to all four.
    $structuredData = $schema ?? [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $siteName,
        'url' => url('/'),
        'logo' => asset('icons/icon-512.png'),
        'description' => $pageDescription,
        'sameAs' => $socials,
    ];
@endphp

<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ $pageDescription }}">
<link rel="canonical" href="{{ $canonical }}">

@if ($noindex)
    <meta name="robots" content="noindex, follow">
@else
    <meta name="robots" content="index, follow, max-image-preview:large">
@endif

{{-- Open Graph: what a link looks like when a customer pastes it into WhatsApp,
     Instagram DMs or Facebook. Without it the platform guesses, usually badly. --}}
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:type" content="{{ $type }}">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $pageDescription }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $imageUrl }}">
<meta property="og:image:width" content="{{ $imageWidth }}">
<meta property="og:image:height" content="{{ $imageHeight }}">
<meta property="og:image:alt" content="{{ $title ?: $siteName }}">
<meta property="og:locale" content="en_US">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $pageTitle }}">
<meta name="twitter:description" content="{{ $pageDescription }}">
<meta name="twitter:image" content="{{ $imageUrl }}">

{{-- JSON-LD. @json escapes for a <script> context; do not use {!! !!} here. --}}
<script type="application/ld+json">@json($structuredData, JSON_UNESCAPED_SLASHES)</script>
