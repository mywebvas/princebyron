<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="{{ config('tokens.primitives.obsidian') }}">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  {{-- P2-4: title, description, canonical, Open Graph, Twitter card and JSON-LD
       in one place. Previously the head carried a single hardcoded description
       and nothing else — no og:*, no canonical, no structured data — so every
       share looked generic and a search engine had nothing to link the artist's
       profiles to. Per-page values arrive via @section or the view's $seo array. --}}
  <x-seo
    :title="$seo['title'] ?? $title ?? null"
    :description="$seo['description'] ?? null"
    :image="$seo['image'] ?? null"
    :type="$seo['type'] ?? 'website'"
    :noindex="$seo['noindex'] ?? false"
    :schema="$seo['schema'] ?? null"
  />
  <link rel="icon" href="/favicon.ico" sizes="32x32">
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
  <link rel="manifest" href="/manifest.webmanifest">

  {{-- P2-7: preload only the display face. It is used above the fold for the
       hero headline, so it is on the critical path; the body face is not
       preloaded because `font-display: swap` already keeps text readable while
       it loads, and a second preload would compete with the LCP image. --}}
  <link rel="preload" href="/fonts/cinzel-latin.woff2" as="font" type="font/woff2" crossorigin>

  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data>

  <!-- Skip Navigation Link for Keyboard Accessibility (WCAG 2.2 AA) -->
  <a href="#main-content" class="skip-link">Skip to main content</a>

  <div class="site-wrapper">

    <!-- Mobile Navigation Drawer Overlay & Panel (Accessible Modal Dialog) -->
    <div 
      class="mobile-nav-overlay" 
      id="mobile-nav-overlay" 
      role="dialog" 
      aria-modal="true" 
      aria-label="Mobile Navigation Menu" 
      :class="{ 'open': $store.app.mobileNavOpen }"
      :aria-hidden="(!$store.app.mobileNavOpen).toString()"
      @click.self="$store.app.closeMobileNav()"
      x-cloak
    >
      <div class="mobile-nav-panel" id="mobile-nav-panel">
        <div class="mobile-nav-header">
          <div class="brand-logo">
            <div class="brand-monogram" aria-hidden="true">PB</div>
            <div class="brand-titles">
              <span class="mobile-menu-brand-name">Prince Byron</span>
            </div>
          </div>
          <button 
            type="button" 
            class="btn-close-mobile-nav" 
            id="btn-close-mobile-nav" 
            aria-label="Close navigation menu"
            @click="$store.app.closeMobileNav()"
          >&times;</button>
        </div>

        <nav class="mobile-nav-links" aria-label="Mobile Navigation Links">
          <a href="{{ url('/') }}" class="mobile-nav-link {{ request()->is('/') ? 'active' : '' }}" @click="$store.app.closeMobileNav()">Home</a>
          <a href="{{ url('/about') }}" class="mobile-nav-link {{ request()->is('about') ? 'active' : '' }}" @if(request()->is('about')) aria-current="page" @endif @click="$store.app.closeMobileNav()">Our Story &amp; Vision</a>
          <a href="{{ url('/music') }}" class="mobile-nav-link {{ request()->is('music*') ? 'active' : '' }}" @click="$store.app.closeMobileNav()">Music Releases</a>
          <a href="{{ url('/store') }}" class="mobile-nav-link {{ request()->is('store*') ? 'active' : '' }}" @click="$store.app.closeMobileNav()">Official Store</a>
          <a href="{{ url('/booking') }}" class="mobile-nav-link {{ request()->is('booking') ? 'active' : '' }}" @click="$store.app.closeMobileNav()">Ministry Booking &amp; RFQ</a>
          <a href="{{ url('/cart') }}" class="mobile-nav-link {{ request()->is('cart') ? 'active' : '' }}" @click="$store.app.closeMobileNav()">Shopping Bag (<span x-text="$store.app.cartCount"></span>)</a>
          <a href="{{ url('/checkout') }}" class="mobile-nav-link {{ request()->is('checkout') ? 'active' : '' }}" @click="$store.app.closeMobileNav()">Dedicated Checkout</a>
          <a href="{{ url('/account') }}" class="mobile-nav-link {{ request()->is('account*') ? 'active' : '' }}" @click="$store.app.closeMobileNav()">Fan Vault &amp; Entitlements</a>
        </nav>

        <div class="mobile-nav-footer">
          <div class="mobile-nav-footer-title">Official Streaming &amp; Ministry</div>
          <div class="mobile-nav-socials">
            <a href="https://open.spotify.com/artist/089TupQTFoR8OvKrDOp1NA" target="_blank" rel="noopener noreferrer" aria-label="Prince Byron on Spotify">Spotify</a>
            <a href="https://www.youtube.com/channel/UCeeZS5FkmYj6F4fwykc7f0g" target="_blank" rel="noopener noreferrer" aria-label="Prince Byron on YouTube">YouTube</a>
            <a href="https://www.instagram.com/prince_byron_official" target="_blank" rel="noopener noreferrer" aria-label="Prince Byron on Instagram">Instagram</a>
            <a href="https://www.tiktok.com/@princebyron94" target="_blank" rel="noopener noreferrer" aria-label="Prince Byron on TikTok">TikTok</a>
            <a href="https://www.facebook.com/princebyron89/" target="_blank" rel="noopener noreferrer" aria-label="Prince Byron on Facebook">Facebook</a>
          </div>
        </div>
      </div>
    </div>

    @if(!(request()->is('account*') && auth()->check()))
    <!-- Sticky Header (Cinematic Dark Hybrid) -->
    <header class="site-header">
      <div class="header-inner">
        <a href="{{ url('/') }}" class="brand-logo" aria-label="Prince Byron Home">
          <div class="brand-monogram" aria-hidden="true">PB</div>
          <div class="brand-titles">
            <span class="brand-title">Prince Byron</span>
          </div>
        </a>

        <!-- Desktop Navigation -->
        <nav class="site-nav" aria-label="Main Navigation">
          <a href="{{ url('/') }}" class="nav-link {{ request()->is('/') ? 'active' : '' }}" @if(request()->is('/')) aria-current="page" @endif>Home</a>
          <a href="{{ url('/about') }}" class="nav-link {{ request()->is('about') ? 'active' : '' }}" @if(request()->is('about')) aria-current="page" @endif>Our Story</a>
          <a href="{{ url('/music') }}" class="nav-link {{ request()->is('music*') ? 'active' : '' }}" @if(request()->is('music*')) aria-current="page" @endif>Music</a>
          <a href="{{ url('/store') }}" class="nav-link {{ request()->is('store*') ? 'active' : '' }}" @if(request()->is('store*')) aria-current="page" @endif>Store</a>
          <a href="{{ url('/booking') }}" class="nav-link {{ request()->is('booking') ? 'active' : '' }}" @if(request()->is('booking')) aria-current="page" @endif>Booking</a>
          <a href="{{ url('/account') }}" class="nav-link {{ request()->is('account*') ? 'active' : '' }}" @if(request()->is('account*')) aria-current="page" @endif>Fan Vault</a>
        </nav>

        <div class="header-actions">
          <button 
            type="button" 
            class="btn-bag" 
            id="btn-open-bag" 
            aria-label="Open Shopping Bag"
            :aria-expanded="$store.app.bagOpen.toString()"
            @click="$store.app.openBag()"
          >
            <span>Bag</span>
            <div class="badge-count" x-text="$store.app.cartCount"></div>
          </button>
          <button 
            type="button" 
            class="btn-mobile-menu" 
            id="btn-menu-toggle" 
            aria-label="Open Navigation Menu"
            :aria-expanded="$store.app.mobileNavOpen.toString()"
            @click="$store.app.openMobileNav()"
          >
            <span></span>
            <span></span>
            <span></span>
          </button>
        </div>
      </div>
    </header>
    @endif

    <!-- Main Content Injection -->
    <main id="main-content">
      @yield('content')
    </main>

    @if(!(request()->is('account*') && auth()->check()))
    <!-- Site Footer (Intentional Multi-Device Grid Layout) -->
    <footer class="site-footer">
      <div class="footer-inner">
        <!-- 1. Artist / Site Name and Description First Across Full Width on Mobile -->
        <div class="footer-brand">
          <h4>Prince Byron</h4>
          <p>Faith-led gospel music and ministry, dedicated to lifting spirits and uniting hearts under God's unfailing grace.</p>
        </div>

        <!-- 2. Footer Navigation Links in Intentional Columns -->
        <div class="footer-col">
          <h5>Music &amp; Ministry</h5>
          <div class="footer-links">
            <a href="{{ url('/about') }}">Our Story &amp; Vision</a>
            <a href="{{ url('/music') }}">Free Lead Track Download</a>
            <a href="{{ url('/booking') }}">Ministry Booking &amp; RFQ</a>
          </div>
        </div>

        <div class="footer-col">
          <h5>Store &amp; Orders</h5>
          <div class="footer-links">
            <a href="{{ url('/store') }}">All Apparel &amp; Merch</a>
            <a href="{{ url('/store/tee') }}">Prince Byron Signature Tee</a>
            <a href="{{ url('/account') }}">Customer Entitlements</a>
          </div>
        </div>

        <div class="footer-col">
          <h5>Official Streaming &amp; Social</h5>
          <div class="footer-links">
            <a href="https://open.spotify.com/artist/089TupQTFoR8OvKrDOp1NA" target="_blank" rel="noopener noreferrer" aria-label="Listen on Spotify">Spotify Official &rarr;</a>
            <a href="https://www.youtube.com/channel/UCeeZS5FkmYj6F4fwykc7f0g" target="_blank" rel="noopener noreferrer" aria-label="Watch on YouTube">YouTube Channel &rarr;</a>
            <a href="https://www.instagram.com/prince_byron_official" target="_blank" rel="noopener noreferrer" aria-label="Follow on Instagram">Instagram &rarr;</a>
            <a href="https://www.tiktok.com/@princebyron94" target="_blank" rel="noopener noreferrer" aria-label="Follow on TikTok">TikTok &rarr;</a>
            <a href="https://www.facebook.com/princebyron89/" target="_blank" rel="noopener noreferrer" aria-label="Follow on Facebook">Facebook &rarr;</a>
          </div>
        </div>
      </div>

      <div class="footer-bottom">
        <div>&copy; {{ date('Y') }} Prince Byron &bull; princebyron.com &bull; All Rights Reserved.</div>
        <div>Faith-led worship music, ministry invitations, and author-verified apparel.</div>
      </div>
    </footer>
    @endif

    <!-- Shopping Bag Drawer Panel (95% mobile width with visible backdrop slice) -->
    <div 
      class="drawer-overlay" 
      id="bag-drawer" 
      role="dialog" 
      aria-modal="true" 
      aria-label="Shopping Bag Drawer" 
      :class="{ 'open': $store.app.bagOpen }"
      :aria-hidden="(!$store.app.bagOpen).toString()"
      @click.self="$store.app.closeBag()"
      x-cloak
    >
      <aside class="drawer-panel" aria-label="Shopping Bag">
        <div class="drawer-header">
          <h3>Your Bag (<span x-text="$store.app.cartCount"></span>)</h3>
          <button 
            type="button" 
            class="btn-close-drawer" 
            id="btn-close-bag" 
            aria-label="Close Shopping Bag"
            @click="$store.app.closeBag()"
          >&times;</button>
        </div>

        <div class="drawer-body" id="drawer-items-list">
          <template x-if="$store.app.cartIsEmpty">
            <div style="text-align: center; padding: 48px 16px; color: var(--text-muted);">
              <p style="font-size: 1.15rem; margin-bottom: 8px; font-family: var(--font-display); color: var(--text-primary);">Your bag is currently empty.</p>
              <p style="font-size: 0.85rem; margin-bottom: 20px; color: var(--text-muted);">Explore our worship releases and sacred apparel to support Prince Byron's ministry.</p>
              <a href="{{ url('/store') }}" class="btn-secondary-sm" @click="$store.app.closeBag()">Discover Official Apparel &rarr;</a>
            </div>
          </template>

          <!-- Items come from the server summary: prices, stock and
               availability are decided server-side on every refresh. -->
          <template x-for="item in $store.app.cartItems" :key="item.key">
            <div class="cart-item">
              <div class="cart-item-thumb" aria-hidden="true">PB</div>
              <div class="cart-item-info">
                <h4 x-text="item.title"></h4>
                <div class="meta" x-text="item.variant_label"></div>
                <div class="meta" style="color: var(--color-gold); font-weight: 700;" x-text="'$' + (item.unit_price_cents / 100).toFixed(2) + ' USD'"></div>
                <div class="cart-qty-ctrl" x-show="item.type === 'variant'">
                  <button type="button" @click="$store.app.decrementItem(item)" aria-label="Decrease quantity">&minus;</button>
                  <span x-text="item.quantity"></span>
                  <button type="button" @click="$store.app.incrementItem(item)" aria-label="Increase quantity">&plus;</button>
                </div>
                <div class="meta" x-show="item.type === 'song'">Digital single &bull; 1 copy</div>
              </div>
              <div style="text-align: right;">
                <div style="font-weight: 700; color: var(--text-main);" x-text="'$' + (item.line_total_cents / 100).toFixed(2)"></div>
                <button 
                  type="button" 
                  class="btn-remove" 
                  @click="$store.app.removeItem(item)"
                  aria-label="Remove item"
                  style="margin-top: 12px; font-size: 0.75rem; color: var(--status-error-text); background: none; border: none; cursor: pointer; text-decoration: underline;"
                >Remove</button>
              </div>
            </div>
          </template>
        </div>

        <div class="drawer-footer">
          <div class="drawer-subtotal">
            <span>Subtotal (USD):</span>
            <span class="amount" id="drawer-subtotal-val" x-text="$store.app.cartSubtotalFormatted"></span>
          </div>
          <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 16px;">
            Shipping and applicable sales taxes calculated authoritatively during secure checkout.
          </div>
          <a 
            href="{{ url('/checkout') }}" 
            class="btn-primary" 
            style="width: 100%; text-align: center; box-sizing: border-box; justify-content: center;"
            @click="$store.app.closeBag()"
          >Proceed to Dedicated Checkout &rarr;</a>
          <a 
            href="{{ url('/cart') }}" 
            style="display: block; text-align: center; margin-top: 12px; font-size: 0.82rem; color: var(--color-amber); text-decoration: underline;"
            @click="$store.app.closeBag()"
          >Open Itemized Bag Page &rarr;</a>
        </div>
      </aside>
    </div>

    <!-- Persistent Bottom Audio Player Bar -->
    <div 
      class="music-player-bar" 
      id="music-player-bar" 
      role="region" 
      aria-label="Audio Player Preview"
      x-show="$store.app.player.active"
      :class="{ 'active': $store.app.player.active }"
      x-cloak
    >
      <button 
        type="button" 
        class="player-ctrl-btn" 
        id="player-toggle-btn" 
        :aria-label="$store.app.player.playing ? 'Pause audio' : 'Play audio'"
        @click="$store.app.togglePlay()"
      >
        <span x-html="$store.app.player.playing ? '&#10074;&#10074;' : '&#9658;'"></span>
      </button>
      <div class="player-track-info">
        <strong id="player-track-title" x-text="$store.app.player.trackTitle"></strong>
        <small id="player-track-subtitle" x-text="$store.app.player.trackSubtitle"></small>
      </div>
      <div class="player-scrubber" aria-label="Track progress bar">
        <div class="player-scrubber-fill" id="player-scrubber-fill" :style="'width: ' + $store.app.player.progress + '%'"></div>
      </div>
      <button 
        type="button" 
        class="player-close-btn" 
        aria-label="Close audio player" 
        @click="$store.app.closePlayer()"
      >&times;</button>
    </div>

    <!-- Toast Notification (Accessible Polite Status Announcement) -->
    <div 
      class="toast-notification" 
      id="toast" 
      role="status" 
      aria-live="polite"
      :class="{ 'visible': $store.app.toast.visible }"
      x-text="$store.app.toast.message"
    ></div>

  </div><!-- /site-wrapper -->

</body>
</html>
