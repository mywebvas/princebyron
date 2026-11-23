@extends('layouts.app')

@section('content')
<!-- Store Hero Stage -->
<section class="hero-stage">
  <div class="hero-inner">
    <span class="hero-eyebrow">Official Worship Apparel &amp; Merchandise</span>
    <h1 class="hero-title">
      Worn in faith.<br>
      <em>Crafted with care.</em>
    </h1>
    <p class="hero-lead">
      Elevated essentials, embroidered symbols of faith, and archival art crafted to endure every season.
    </p>
  </div>
</section>

<!-- Store Catalog Section -->
<section class="section-editorial" style="background: var(--color-linen);" x-data="{ activeCategory: 'all' }">
  <div class="section-container">
    
    @if($samplePricing)
      <p class="price-notice" style="color: var(--text-primary); border-left-color: var(--color-amber); background: rgba(200, 136, 59, 0.07);" role="note">
        <strong>Please note:</strong> {{ $samplePricingNotice }}
        Amounts are shown for demonstration only while the catalog is being finalised.
      </p>
    @endif

    <!-- Filter Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 36px; flex-wrap: wrap; gap: 16px;">
      <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button 
          type="button" 
          class="picker-btn" 
          :class="{ 'selected': activeCategory === 'all' }" 
          @click="activeCategory = 'all'"
        >All Merchandise</button>
        <button 
          type="button" 
          class="picker-btn" 
          :class="{ 'selected': activeCategory === 'Apparel' }" 
          @click="activeCategory = 'Apparel'"
        >Apparel</button>
        <button 
          type="button" 
          class="picker-btn" 
          :class="{ 'selected': activeCategory === 'Accessories' }" 
          @click="activeCategory = 'Accessories'"
        >Accessories</button>
        <button 
          type="button" 
          class="picker-btn" 
          :class="{ 'selected': activeCategory === 'Music Art' }" 
          @click="activeCategory = 'Music Art'"
        >Music Art</button>
        <button 
          type="button" 
          class="picker-btn" 
          :class="{ 'selected': activeCategory === 'Bundles' }" 
          @click="activeCategory = 'Bundles'"
        >Bundles</button>
      </div>

      <div style="font-size: 0.85rem; color: var(--text-muted);">
        Showing {{ count($products) }} Curated Offerings
      </div>
    </div>

    <!-- Product Grid -->
    <div class="product-grid">
      @foreach($products as $product)
        <article 
          class="product-card" 
          x-show="activeCategory === 'all' || activeCategory === '{{ $product['category'] }}'"
        >
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

    <!-- Sourcing & Shipping Notice -->
    <div style="margin-top: 56px; padding: 24px; background: var(--surface-card); border: 1px solid var(--border-light); border-radius: var(--radius-md); text-align: center;">
      <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
        <strong>Ethical Craft &amp; Sourcing:</strong> Every piece is produced with certified sustainable practices and pre-shrunk for enduring comfort. Delivery options and regional shipping rates are calculated authoritatively at checkout.
      </p>
    </div>

  </div>
</section>
@endsection
