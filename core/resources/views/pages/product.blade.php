@extends('layouts.app')

@section('content')
<section class="section-editorial" style="background: var(--color-cream);" 
  x-data="{
    selectedColor: '{{ $product['colors'][0] }}',
    selectedSize: '{{ $product['sizes'][0] }}',
    qty: 1,
    priceCents: {{ $product['priceCents'] }}
  }"
>
  <div class="section-container">
    
    <!-- Breadcrumb Navigation -->
    <nav class="breadcrumb-nav" aria-label="Product Breadcrumbs">
      <a href="{{ url('/store') }}">&larr; Back to Store</a>
      <span>/</span>
      <span>{{ $product['category'] }}</span>
      <span>/</span>
      <span>{{ $product['title'] }}</span>
    </nav>

    <!-- Product Detail Layout Grid -->
    <div class="product-detail-grid">
      
      <!-- Left: High-Res Visual Gallery -->
      <div class="detail-gallery">
        <div class="detail-main-frame">
          <img 
            src="{{ asset($product['image']) }}" 
            alt="{{ $product['title'] }}" 
            class="product-detail-photo"
            width="600"
            height="600"
          >
        </div>

        <div class="detail-thumbs">
          <button type="button" class="detail-thumb active" aria-label="View front studio shot">Studio</button>
          <button type="button" class="detail-thumb" aria-label="View typography detail">Detail</button>
          <button type="button" class="detail-thumb" aria-label="View fabric texture">Fabric</button>
        </div>
      </div>

      <!-- Right: Purchase Configuration Panel -->
      <div class="detail-info-panel">
        <span class="detail-badge">{{ $product['category'] }}</span>
        <h1 class="detail-title">{{ $product['title'] }}</h1>
        <div class="detail-sku">SKU: {{ $product['sku'] }}</div>
        
        <div class="detail-price">
          ${{ number_format($product['priceCents'] / 100, 2) }} USD
        </div>

        <div class="detail-stock-pill">
          <span style="color: var(--status-success-text);">&#9679;</span> 
          <span>Verified In Stock &bull; Ready to Fulfill</span>
        </div>

        <!-- Color Options -->
        <div style="margin-bottom: 24px;">
          <div class="picker-label">
            <span>Color Palette:</span>
            <strong x-text="selectedColor"></strong>
          </div>
          <div class="options-row">
            @foreach($product['colors'] as $color)
              <button 
                type="button" 
                class="picker-btn" 
                :class="{ 'selected': selectedColor === '{{ $color }}' }" 
                @click="selectedColor = '{{ $color }}'"
              >{{ $color }}</button>
            @endforeach
          </div>
        </div>

        <!-- Size Options -->
        <div style="margin-bottom: 28px;">
          <div class="picker-label">
            <span>Select Size:</span>
            <strong x-text="selectedSize"></strong>
          </div>
          <div class="options-row">
            @foreach($product['sizes'] as $size)
              <button 
                type="button" 
                class="picker-btn" 
                :class="{ 'selected': selectedSize === '{{ $size }}' }" 
                @click="selectedSize = '{{ $size }}'"
              >{{ $size }}</button>
            @endforeach
          </div>
        </div>

        <!-- Quantity Stepper -->
        <div style="margin-bottom: 32px;">
          <div class="picker-label">
            <span>Quantity:</span>
          </div>
          <div class="cart-qty-ctrl" style="width: 140px; justify-content: space-between;">
            <button type="button" @click="if (qty > 1) qty--" aria-label="Decrease quantity">&minus;</button>
            <span x-text="qty"></span>
            <button type="button" @click="qty++" aria-label="Increase quantity">&plus;</button>
          </div>
        </div>

        <!-- Add to Bag CTA -->
        <div style="display: flex; gap: 16px; margin-bottom: 36px; flex-wrap: wrap;">
          <button 
            type="button" 
            class="btn-primary" 
            style="flex: 1; min-width: 240px; justify-content: center; text-align: center;"
            @click="$store.app.addToCart({
              id: '{{ $product['id'] }}',
              title: '{{ $product['title'] }}',
              variant: selectedColor + ' / ' + selectedSize,
              priceCents: priceCents,
              qty: qty,
              type: 'physical'
            })"
          >
            Add to Bag &bull; <span x-text="'$' + ((priceCents * qty) / 100).toFixed(2)"></span>
          </button>
          <a href="{{ url('/cart') }}" class="btn-secondary" style="justify-content: center;">View Bag</a>
        </div>

        <!-- Craft & Materials Box -->
        <div class="detail-specs-box">
          <h2 style="font-family: var(--font-display); font-size: 1.15rem; color: var(--text-primary); margin-bottom: 12px;">Materials &amp; Sourcing Details</h2>
          <ul>
            @foreach($product['specs'] as $spec)
              <li>{{ $spec }}</li>
            @endforeach
          </ul>
        </div>

      </div>
    </div>

    <!-- Related Products -->
    <div style="margin-top: 80px; border-top: 1px solid var(--border-light); padding-top: 56px;">
      <h2 style="font-family: var(--font-display); font-size: 1.8rem; color: var(--color-obsidian); margin-bottom: 32px;">
        Complete the Collection
      </h2>
      <div class="product-grid">
        @foreach($relatedProducts as $rel)
          <article class="product-card">
            <div class="product-image-wrap">
              <img src="{{ asset($rel['image']) }}" alt="{{ $rel['title'] }}" class="product-card-photo" loading="lazy">
            </div>
            <div class="product-content">
              <h3 class="product-title" style="font-family: var(--font-display); font-size: 1.15rem; font-weight: 600; margin-bottom: 6px;">{{ $rel['title'] }}</h3>
              <p class="product-desc">{{ $rel['desc'] }}</p>
              <div class="product-foot">
                <span class="product-price">${{ number_format($rel['priceCents'] / 100, 2) }} USD</span>
                <a href="{{ url('/store/' . $rel['slug']) }}" class="btn-store-detail">View &rarr;</a>
              </div>
            </div>
          </article>
        @endforeach
      </div>
    </div>

  </div>
</section>
@endsection
