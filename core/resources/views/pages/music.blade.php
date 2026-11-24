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
        <x-img
          src="images/music/faith-for-the-in-between-album.jpg"
          alt="Faith for the In-Between album art"
          class="album-cover-photo"
          sizes="(max-width: 700px) 70vw, 260px"
        />
      </div>
      <div class="album-feature-info">
        <span class="hero-eyebrow" style="color: var(--color-gold); margin-bottom: 8px;">Featured Collection &bull; 5 Master Tracks</span>
        <h2 style="font-family: var(--font-display); font-size: 2.2rem; color: var(--text-inverse); margin-bottom: 12px; line-height: 1.15;">
          Faith for the In-Between
        </h2>
        <p style="color: var(--color-sand); line-height: 1.6; margin-bottom: 24px;">
          Five tracks: the lead single "Free", "Purify Me", "I Always Win", "Toduyun" and "Follow Me". Each track is available as an instant MP3 download. Studio WAV masters are not available yet — the track list marks where they are pending rather than promising them.
        </p>
        <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
          {{-- Play the lead-single preview that actually ships. The previous
               button announced a 1200-second "collection" audio file that does
               not exist, alongside a $12.00 price for a product the owner never
               approved (P2-8). Individual tracks are purchasable below. --}}
          <button 
            type="button" 
            class="btn-primary" 
            @click="$store.app.playTrack('Purify Me', 30)"
          >
            &#9658; Play Preview
          </button>
          <a href="#tracklist" class="btn-secondary" style="text-decoration: none;">
            Browse the Tracks &darr;
          </a>
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

    <div class="track-grid" id="tracklist">
      @foreach($songs as $song)
        <div 
          class="track-row {{ $song['isFree'] ? 'music-item-free' : 'music-item-paid' }}"
          x-show="activeFilter === 'all' || (activeFilter === 'free' && {{ $song['isFree'] ? 'true' : 'false' }}) || (activeFilter === 'paid' && {{ !$song['isFree'] ? 'true' : 'false' }})"
        >
          <span class="track-number">{{ $song['index'] }}</span>
          <div class="track-details">
            <h3 class="track-title" style="font-family: var(--font-sans); font-size: 1.05rem; font-weight: 700; color: inherit; margin-bottom: 4px;">{{ $song['title'] }}</h3>
            <p style="font-size: 0.85rem; color: var(--color-sand); margin: 0;">{{ $song['subtitle'] }}</p>
            {{-- P2-8: state the format truthfully per track. The catalogue
                 advertises 24-bit WAV while `has_wav` is false on every row and
                 the masters are still pending, so the honest label is shown only
                 where the file genuinely exists. --}}
            <p class="track-format">
              @if(! empty($song['hasWav']))
                MP3 + 24-bit WAV
              @else
                MP3 download &bull; WAV master pending
              @endif
            </p>
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

    <!-- 4. Playlists by Moment -->
    {{-- These cards name specific tracks. Render them only when those tracks
         are actually published, otherwise a visitor is pointed at songs that
         no longer exist. --}}
    @php
      $publishedTitles = collect($songs)->pluck('title')->all();
      $playlistsAvailable = count(array_intersect(
          ['Purify Me', 'Follow Me', 'I Always Win', 'Free', 'Toduyun'],
          $publishedTitles
      )) >= 2;
    @endphp
    @if($playlistsAvailable)
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
    @endif

  </div>
</section>
@endsection
