@props([
    'src',                    // e.g. 'images/prince-byron-hero.jpeg'
    'alt' => '',              // required in practice; see guard below
    'sizes' => '100vw',       // must match the CSS layout, not the file
    'width' => null,          // intrinsic px; read from the file when omitted
    'height' => null,
    'loading' => 'lazy',      // hero images pass 'eager'
    'fetchpriority' => null,  // hero images pass 'high'
    'class' => null,
])

@php
    use App\Support\ResponsiveImage;

    // Never emit an <img> without alt. A missing alt is a screen-reader defect
    // and, on a site that sells art and apparel, it is also an SEO defect. The
    // guard throws in local/testing so it cannot ship, and degrades to an empty
    // alt in production rather than taking the page down over a template typo.
    if ($alt === '' && ! config('app.debug')) {
        // deliberately silent in production
    } elseif ($alt === '') {
        throw new \RuntimeException("The <x-img> component requires an alt attribute for [{$src}].");
    }

    $dims = ResponsiveImage::dimensions($src);
    $width ??= $dims[0] ?? null;
    $height ??= $dims[1] ?? null;

    $webpSrcset = ResponsiveImage::srcset($src, 'webp');
    $jpegSrcset = ResponsiveImage::srcset($src, 'jpg');
    $fallback = ResponsiveImage::fallback($src);
@endphp

@if ($webpSrcset)
    <picture>
        {{-- WebP first: 20–40% smaller than JPEG at the same visual quality. --}}
        <source type="image/webp" srcset="{{ $webpSrcset }}" sizes="{{ $sizes }}">

        {{-- JPEG is the fallback for anything without WebP support, and the
             `src` is deliberately the modest 960px file rather than the master:
             only a browser that ignores srcset reads it. --}}
        <img
            src="{{ $fallback }}"
            @if ($jpegSrcset) srcset="{{ $jpegSrcset }}" sizes="{{ $sizes }}" @endif
            @if ($width) width="{{ $width }}" @endif
            @if ($height) height="{{ $height }}" @endif
            alt="{{ $alt }}"
            loading="{{ $loading }}"
            decoding="async"
            @if ($fetchpriority) fetchpriority="{{ $fetchpriority }}" @endif
            @if ($class) class="{{ $class }}" @endif
        >
    </picture>
@else
    {{-- No derivatives on disk (generator not run): fall back to the original so
         the image still appears. A broken srcset would be worse than a big file. --}}
    <img
        src="{{ asset($src) }}"
        @if ($width) width="{{ $width }}" @endif
        @if ($height) height="{{ $height }}" @endif
        alt="{{ $alt }}"
        loading="{{ $loading }}"
        decoding="async"
        @if ($fetchpriority) fetchpriority="{{ $fetchpriority }}" @endif
        @if ($class) class="{{ $class }}" @endif
    >
@endif
