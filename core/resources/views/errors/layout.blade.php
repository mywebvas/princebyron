<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#14100C">
  <meta name="robots" content="noindex">
  <title>{{ $title }} — Prince Byron</title>
  <link rel="icon" href="/favicon.ico" sizes="32x32">
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <link rel="preload" href="/fonts/cinzel-latin.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="{{ asset('build/errors.css') }}">
  <style>
    @font-face{font-family:'Cinzel';font-style:normal;font-weight:400 900;font-display:swap;
      src:url('/fonts/cinzel-latin.woff2') format('woff2')}
    @font-face{font-family:'Source Sans 3';font-style:normal;font-weight:200 900;font-display:swap;
      src:url('/fonts/source-sans-latin.woff2') format('woff2')}
  </style>
</head>
<body>
  <div class="err-shell">
    <a class="err-skip" href="#err-main">Skip to content</a>

    <header class="err-head">
      <a class="err-brand" href="/" aria-label="Prince Byron — home">Prince Byron</a>
    </header>

    <main class="err-main" id="err-main">
      <p class="err-code" aria-hidden="true">{{ $status }}</p>

      <h1 class="err-title">{{ $title }}</h1>

      <p class="err-lede">{{ $lede }}</p>

      {{-- Every error page offers a way forward. A dead end with a status code
           is where a customer stops being a customer. --}}
      <nav class="err-actions" aria-label="Ways forward">
        <a class="err-btn err-btn-primary" href="/">Back to the homepage</a>
        <a class="err-btn" href="/store">Browse the store</a>
        <a class="err-btn" href="/music">Listen to the music</a>
      </nav>

      @if (! empty($detail))
        <p class="err-detail">{{ $detail }}</p>
      @endif
    </main>

    <footer class="err-foot">
      <p>Prince Byron — Gospel music, ministry &amp; store.</p>
      @if (! empty($reference))
        <p class="err-ref">Reference: <strong>{{ $reference }}</strong></p>
      @endif
    </footer>
  </div>
</body>
</html>
