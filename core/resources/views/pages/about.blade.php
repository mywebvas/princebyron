@extends('layouts.app')

@section('content')
<!-- About Page Hero -->
<section class="hero-stage">
  <div class="hero-inner">
    <span class="hero-eyebrow">Our Story &amp; Calling</span>
    <h1 class="hero-title">
      A ministry of worship,<br>
      <em>testimony &amp; grace.</em>
    </h1>
    <p class="hero-lead">
      Discover the heart, calling, and vision behind Prince Byron's music ministry — dedicated to pointing hearts toward Christ through genuine praise.
    </p>
  </div>
</section>

<!-- Narrative Section with Authentic Portrait -->
<section class="section-editorial" style="background: var(--color-paper);">
  <div class="section-container">
    <div class="bio-grid">
      <div class="bio-portrait-frame">
        <x-img
          src="images/prince-byron-portrait.jpeg"
          alt="Prince Byron standing in natural studio lighting"
          sizes="(max-width: 900px) 92vw, 520px"
          fetchpriority="high"
        />
      </div>

      <div class="bio-narrative">
        <span class="bio-eyebrow">Heart of the Ministry</span>
        <h2 class="bio-title">
          From the Sanctuary<br>
          <em>to the Soul.</em>
        </h2>
        <blockquote class="bio-quote">
          &ldquo;True worship doesn't begin on a platform; it begins in the quiet places of surrender and follows us into every season of life.&rdquo;
        </blockquote>
        <p class="bio-body">
          Prince Byron began his musical journey rooted deep in congregational worship. Over the years, his ministry has grown from local sanctuary leading to nationwide conferences and recorded praise anthems, touching believers from all walks of life.
        </p>
        <p class="bio-body">
          Every melody is crafted to draw listeners into the presence of God, blending rich gospel roots with contemporary soulful reverence. Whether ministering in acoustic intimacy or full-band worship, the singular goal remains: to glorify God and strengthen the body of Christ.
        </p>

        <!-- 3 Ministry Pillars -->
        <div style="margin-top: 40px; display: grid; gap: 20px;">
          <div style="border-left: 3px solid var(--color-gold); padding-left: 16px;">
            <h3 style="color: var(--color-obsidian); font-family: var(--font-display); font-size: 1.15rem; margin-bottom: 4px;">1. Christ-Centered Worship</h3>
            <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">Anchoring every melody and lyric in biblical truth, exalting Jesus Christ above all else.</p>
          </div>
          <div style="border-left: 3px solid var(--color-gold); padding-left: 16px;">
            <h3 style="color: var(--color-obsidian); font-family: var(--font-display); font-size: 1.15rem; margin-bottom: 4px;">2. Sound of Testimony</h3>
            <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">Giving voice to everyday struggles, steadfast faith, and God's miraculous grace through authentic lyrics.</p>
          </div>
          <div style="border-left: 3px solid var(--color-gold); padding-left: 16px;">
            <h3 style="color: var(--color-obsidian); font-family: var(--font-display); font-size: 1.15rem; margin-bottom: 4px;">3. Relational Fellowship</h3>
            <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">Partnering with local pastors, youth leaders, and ministries to cultivate enduring spiritual growth.</p>
          </div>
        </div>

        <div style="margin-top: 48px; display: flex; gap: 16px; flex-wrap: wrap;">
          <a href="{{ url('/booking') }}" class="btn-primary">Request Ministry Appearance &rarr;</a>
          <a href="{{ url('/music') }}" class="btn-secondary">Listen to the Music</a>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection
