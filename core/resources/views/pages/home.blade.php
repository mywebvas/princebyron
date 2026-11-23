@extends('layouts.app')

@section('content')
<!-- Hero Stage Section (Cinematic Dark Sanctuary) -->
<section class="hero-stage">
  <div class="hero-inner">
    <div class="hero-grid">
      <div>
        <span class="hero-eyebrow">Official Music Ministry of Prince Byron</span>
        <h1 class="hero-title">
          A sound for<br>
          <em>every season.</em>
        </h1>
        <p class="hero-lead">
          Welcome to the official sanctuary of Prince Byron's music and ministry. From quiet acoustic devotion to congregational praise anthems, explore songs written to anchor weary hearts and exalt the living God.
        </p>
        
        <div class="hero-actions">
          <a href="{{ url('/music') }}" class="btn-primary">Listen to the Music &rarr;</a>
          <a href="{{ url('/booking') }}" class="btn-secondary">Request Ministry Booking</a>
        </div>

        <!-- Persistent Audio Player Teaser -->
        <div class="hero-audio-bar" id="hero-player">
          <button 
            type="button" 
            class="audio-play-btn" 
            id="btn-hero-play" 
            aria-label="Play Purify Me preview audio" 
            @click="$store.app.playTrack('Purify Me', 199)"
          >
            <span x-html="$store.app.player.playing && $store.app.player.trackTitle === 'Purify Me' ? '&#10074;&#10074;' : '&#9658;'"></span>
          </button>
          <div class="audio-meta">
            <div class="audio-title">Lead Single &bull; Purify Me</div>
            <div class="audio-subtitle">Acoustic Studio Preview &bull; Master Track Arriving Soon</div>
          </div>
          <div class="audio-time">0:30 Preview</div>
        </div>
      </div>

      <div class="hero-visual">
        <div class="hero-portrait-frame">
          {{-- P2-6: this was a 276 KB JPEG served to every screen size, including
               a 390 px phone. Now the browser picks a 480/960/1600 px WebP; the
               hero is the LCP element, so it is eager + high priority and the
               intrinsic dimensions reserve its box before the bytes land (CLS). --}}
          <x-img
            src="images/prince-byron-hero.jpeg"
            alt="Prince Byron sitting thoughtfully in an earthy, warm studio portrait"
            sizes="(max-width: 700px) 92vw, (max-width: 1180px) 46vw, 560px"
            loading="eager"
            fetchpriority="high"
          />
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Narrative Bio Band (Tactile Linen & Clay) -->
<section class="bio-band">
  <div class="bio-inner">
    <div class="bio-grid">
      <div class="bio-portrait-frame">
        {{-- 927 KB as a single file before Sprint 3. --}}
        <x-img
          src="images/prince-byron-portrait.jpeg"
          alt="Prince Byron standing in natural studio lighting"
          sizes="(max-width: 900px) 92vw, 520px"
        />
      </div>
      <div class="bio-narrative">
        <span class="bio-eyebrow">Our Story &amp; Calling</span>
        <h2 class="bio-title">
          Rooted in faith.<br>
          <em>Born for this moment.</em>
        </h2>
        <blockquote class="bio-quote">
          &ldquo;Music is not an escape from reality; it is the sanctuary where we meet God face-to-face and find the grace to carry forward.&rdquo;
        </blockquote>
        <p class="bio-body">
          From local church sanctuaries to nationwide worship gatherings, Prince Byron crafts gospel anthems that bridge sacred reverence and everyday life. Every lyric carries the weight of authentic testimony, and every melody is an open invitation to deeper fellowship in Christ.
        </p>
        <p class="bio-body">
          Whether ministering in quiet acoustic reflection or leading thousands in unified praise, Byron's calling remains steadfast: to point all glory to God, strengthen the local church, and bring hope to the in-between seasons of life.
        </p>
        <div style="margin-top: 32px; display: flex; gap: 16px; flex-wrap: wrap;">
          <a href="{{ url('/about') }}" class="btn-primary">Read the Ministry Vision &rarr;</a>
          <a href="{{ url('/booking') }}" class="btn-secondary">Invite Prince Byron</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Featured Music Spotlight (All 5 Songs) -->
<section class="section-editorial" style="background: var(--color-obsidian); color: var(--color-sand);">
  <div class="section-container">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
      <div>
        <span class="hero-eyebrow" style="color: var(--color-gold);">Curated Worship Releases</span>
        <h2 style="font-family: var(--font-display); font-size: 2.2rem; color: var(--text-inverse); line-height: 1.15; margin-top: 8px;">
          Faith for the<br><em>in-between.</em>
        </h2>
      </div>
      <a href="{{ url('/music') }}" class="btn-secondary-sm" style="color: var(--color-gold); border-color: var(--border-gold);">View Full 5-Track Catalog &rarr;</a>
    </div>

    <div class="track-grid">
      @foreach($songs as $song)
        <div class="track-row {{ $song['isFree'] ? 'music-item-free' : 'music-item-paid' }}">
          <span class="track-number">{{ $song['index'] }}</span>
          <div class="track-details">
            <h3 class="track-title" style="font-family: var(--font-sans); font-size: 1.05rem; font-weight: 700; color: inherit; margin-bottom: 4px;">{{ $song['title'] }}</h3>
            <p style="font-size: 0.85rem; color: var(--color-sand); margin: 0;">{{ $song['subtitle'] }}</p>
          </div>
          <span class="{{ $song['isFree'] ? 'track-badge-free' : 'track-badge-price' }}">{{ $song['tag'] }}</span>
          <div class="track-actions">
            <button 
              type="button" 
              class="btn-play-track" 
              @click="$store.app.playTrack('{{ $song['title'] }}', {{ $song['priceCents'] }})" 
              aria-label="Play preview for {{ $song['title'] }}"
            >&#9658;</button>
            @if($song['isFree'])
              <a 
                href="{{ route('music.download-free') }}" 
                class="btn-add-track" 
                style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;"
                download
              >Receive Free Track &darr;</a>
            @else
              @if(! empty($song['dbId']))
                <button
                  type="button"
                  class="btn-add-track"
                  @click="$store.app.addSongToCart({{ $song['dbId'] }}).then(ok => { if (ok) $store.app.openBag(); })"
                >
                  Add &bull; ${{ number_format($song['priceCents'] / 100, 2) }}
                </button>
              @else
                <button type="button" class="btn-add-track" disabled aria-disabled="true" title="This track has not been published for sale yet.">
                  Not yet available
                </button>
              @endif
            @endif
          </div>
        </div>
      @endforeach
    </div>
  </div>
</section>

<!-- Featured Merchandise Spotlight -->
<section class="section-editorial" style="background: var(--color-linen);">
  <div class="section-container">
    <div style="text-align: center; max-width: 680px; margin: 0 auto 48px;">
      <span class="section-eyebrow">Sacred Textiles &amp; Testimony</span>
      <h2 class="section-title">Worn in faith.<br><em>Crafted with care.</em></h2>
      <p class="section-desc">
        Elevated essentials, embroidered symbols of faith, and archival art crafted to endure every season.
      </p>
    </div>

    <div class="product-grid">
      @foreach($products as $product)
        <article class="product-card">
          <div class="product-badge">{{ $product['category'] }}</div>
          <div class="product-image-wrap">
            <img src="{{ asset($product['image']) }}" alt="{{ $product['title'] }}" class="product-card-photo" loading="lazy">
          </div>
          <div class="product-content">
            <h3 class="product-title" style="font-family: var(--font-display); font-size: 1.25rem; font-weight: 600; margin-bottom: 6px; color: var(--text-primary);">{{ $product['title'] }}</h3>
            <p class="product-desc">{{ $product['desc'] }}</p>
            <div class="product-foot">
              <span class="product-price">${{ number_format($product['priceCents'] / 100, 2) }} USD</span>
              <a href="{{ url('/store/' . $product['slug']) }}" class="btn-store-detail">View Options &rarr;</a>
            </div>
          </div>
        </article>
      @endforeach
    </div>

    <div style="text-align: center; margin-top: 48px;">
      <a href="{{ url('/store') }}" class="btn-primary">Browse Entire Official Store &rarr;</a>
    </div>
  </div>
</section>
@endsection
