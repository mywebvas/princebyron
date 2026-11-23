@extends('layouts.app')

@section('content')
<!-- Music Hero Stage -->
<section class="hero-stage">
  <div class="hero-inner">
    <span class="hero-eyebrow">The Complete Music Experience</span>
    <h1 class="hero-title">
      A sound for<br>
      <em>every season.</em>
    </h1>
    <p class="hero-lead">
      Stream studio previews, download verified lead tracks, and explore the curated 5-song collection. Purchases deliver authentic high-resolution audio masters to your private vault.
    </p>
    <div style="font-size: 0.82rem; color: var(--color-gold); margin-top: 10px;">
      Includes instant private Fan Vault digital downloads upon completed checkout.
    </div>
  </div>
</section>

<!-- Music Catalog Section -->
<section class="section-editorial" x-data="{ activeFilter: 'all', activeFormat: '{{ $formats['mp3']['label'] }}' }">
  <div class="section-container">
    
    <!-- 1. Featured Collection / Album Card -->
    <div class="album-feature-card">
      <div style="display: flex; justify-content: center; align-items: center;">
        <img 
          src="{{ asset('images/music/faith-for-the-in-between-album.jpg') }}" 
          alt="Faith for the In-Between Album Art" 
          class="album-cover-photo"
          width="260"
          height="260"
        >
      </div>
      <div class="album-feature-info">
        <span class="hero-eyebrow" style="color: var(--color-gold); margin-bottom: 8px;">Featured Collection &bull; 5 Master Tracks</span>
        <h2 style="font-family: var(--font-display); font-size: 2.2rem; color: var(--text-inverse); margin-bottom: 12px; line-height: 1.15;">
          Faith for the In-Between
        </h2>
        <p style="color: var(--color-sand); line-height: 1.6; margin-bottom: 24px;">
          The complete 5-track digital collection including Lead Single "Free", "Purify Me", "I Always Win", "Toduyun", and "Follow Me". Includes high-res digital booklet, 320kbps MP3s, and 24-bit studio WAV masters.
        </p>
        <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
          <button 
            type="button" 
            class="btn-primary" 
            @click="$store.app.playTrack('Faith for the In-Between', 1200, 'collection')"
          >
            &#9658; Play Full Collection
          </button>
          <button 
            type="button" 
            class="btn-secondary" 
            @click="$store.app.addToCart({ id: 'album-collection', title: 'Faith for the In-Between (Digital Collection)', variant: activeFormat, priceCents: 1200, type: 'digital' })"
          >
            Add Collection &bull; $12.00 USD
          </button>
        </div>
      </div>
    </div>

    <!-- 2. Catalog Filtering & Format Tools -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
      <div style="display: flex; gap: 10px;">
        <button 
          type="button" 
          class="picker-btn" 
          :class="{ 'selected': activeFilter === 'all' }" 
          @click="activeFilter = 'all'"
        >All Songs (05)</button>
        <button 
          type="button" 
          class="picker-btn" 
          :class="{ 'selected': activeFilter === 'free' }" 
          @click="activeFilter = 'free'"
        >Free Lead (01)</button>
        <button 
          type="button" 
          class="picker-btn" 
          :class="{ 'selected': activeFilter === 'paid' }" 
          @click="activeFilter = 'paid'"
        >Collection Singles (04)</button>
      </div>

      <div style="display: flex; align-items: center; gap: 10px; font-size: 0.85rem; font-weight: 700;">
        <label for="music-format-select" style="color: var(--text-muted);">Audio Format:</label>
        <select 
          id="music-format-select" 
          class="form-control" 
          style="width: auto; padding: 6px 12px; font-size: 0.82rem;" 
          x-model="activeFormat"
          @change="$store.app.showToast('Audio format selected: ' + activeFormat)"
        >
          @foreach($formats as $key => $format)
            <option 
              value="{{ $format['label'] }}" 
              @disabled(! $format['available'])
            >{{ $format['label'] }}{{ $format['available'] ? '' : ' — not yet available' }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <!-- 3. Tracklist -->
    @if($samplePricing)
      <p class="price-notice" role="note">
        <strong>Please note:</strong> {{ $samplePricingNotice }}
        Amounts are shown for demonstration only while the catalog is being finalised.
      </p>
    @endif

    <div class="track-grid">
      @foreach($songs as $song)
        <div 
          class="track-row {{ $song['isFree'] ? 'music-item-free' : 'music-item-paid' }}"
          x-show="activeFilter === 'all' || (activeFilter === 'free' && {{ $song['isFree'] ? 'true' : 'false' }}) || (activeFilter === 'paid' && {{ !$song['isFree'] ? 'true' : 'false' }})"
        >
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
              <button 
                type="button" 
                class="btn-add-track" 
                @click="$store.app.addToCart({ id: 'song-{{ $song['index'] }}', title: '{{ $song['title'] }} (Digital Track)', variant: activeFormat, priceCents: {{ $song['priceCents'] }}, type: 'digital' })"
              >
                Add &bull; ${{ number_format($song['priceCents'] / 100, 2) }}
              </button>
            @endif
          </div>
        </div>
      @endforeach
    </div>

    <!-- 4. Playlists by Moment -->
    <div style="margin-top: 64px;">
      <span class="section-eyebrow">Listen by Moment</span>
      <h2 class="section-title">Curated Ministry Playlists</h2>
      <p class="section-desc">Experience the music grouped by spiritual atmosphere and setting.</p>
      
      <div class="playlist-grid">
        <button 
          type="button" 
          class="playlist-card" 
          @click="$store.app.playTrack('Purify Me', 199); $store.app.showToast('Playing Stillness Moment: Purify Me & Follow Me')"
        >
          <span class="playlist-number">01 / STILLNESS</span>
          <h3 style="font-family: var(--font-display); font-size: 1.25rem; font-weight: 600; margin-bottom: 6px;">Take a quiet moment.</h3>
          <p class="playlist-tracks">Purify Me &bull; Follow Me</p>
          <span class="playlist-play-cta">&#9658; Start Preview</span>
        </button>

        <button 
          type="button" 
          class="playlist-card" 
          @click="$store.app.playTrack('I Always Win', 199); $store.app.showToast('Playing Strength Moment: I Always Win & Follow Me')"
        >
          <span class="playlist-number">02 / STRENGTH</span>
          <h3 style="font-family: var(--font-display); font-size: 1.25rem; font-weight: 600; margin-bottom: 6px;">Keep going.</h3>
          <p class="playlist-tracks">I Always Win &bull; Follow Me</p>
          <span class="playlist-play-cta">&#9658; Start Preview</span>
        </button>

        <button 
          type="button" 
          class="playlist-card" 
          @click="$store.app.playTrack('Free', 0); $store.app.showToast('Playing Joy Moment: Free & Toduyun')"
        >
          <span class="playlist-number">03 / JOY</span>
          <h3 style="font-family: var(--font-display); font-size: 1.25rem; font-weight: 600; margin-bottom: 6px;">Celebrate God's goodness.</h3>
          <p class="playlist-tracks">Free &bull; Toduyun</p>
          <span class="playlist-play-cta">&#9658; Start Preview</span>
        </button>
      </div>
    </div>

  </div>
</section>
@endsection
