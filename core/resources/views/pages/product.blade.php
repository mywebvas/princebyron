@extends('layouts.app')

@section('content')
<section class="section-editorial" style="background: var(--color-cream);" 
  x-data="{
    variants: @js($product['variants']),
    purchasable: {{ $product['purchasable'] ? 'true' : 'false' }},
    selectedColor: '',
    selectedSize: '',
    qty: 1,
    adding: false,

    init() {
      // Default to the first variant actually in stock, so the page can never
      // open on a combination that cannot be bought.
      const first = this.variants.find(v => v.inStock) || this.variants[0];
      if (first) { this.selectedColor = first.color || ''; this.selectedSize = first.size || ''; }
    },

    get colors() { return [...new Set(this.variants.filter(v => v.inStock).map(v => v.color).filter(Boolean))]; },
    get sizes()  { return [...new Set(this.variants.filter(v => v.inStock && (!this.selectedColor || v.color === this.selectedColor)).map(v => v.size).filter(Boolean))]; },

    get selectedVariant() {
      return this.variants.find(v => (v.color || '') === this.selectedColor && (v.size || '') === this.selectedSize) || null;
    },

    get priceCents() { return this.selectedVariant ? this.selectedVariant.priceCents : 0; },

    get canAdd() { return this.purchasable && !!this.selectedVariant && this.selectedVariant.inStock; },

    async addToBag() {
      if (!this.canAdd || this.adding) return;
      this.adding = true;
      const ok = await $store.app.addVariantToCart(this.selectedVariant.id, this.qty);
      this.adding = false;
      if (ok) {
        this.qty = 1;
        $store.app.openBag();
      }
    }
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
          {{-- The gallery frame is the LCP element on this page, so it is eager
               and high priority. Intrinsic dimensions come from the component,
               which reads them from the file rather than trusting a hard-coded
               600x600 that no longer matches the assets. --}}
          <x-img
            :src="$product['image']"
            :alt="$product['title']"
            class="product-detail-photo"
            sizes="(max-width: 900px) 92vw, 560px"
            loading="eager"
            fetchpriority="high"
          />
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
          @if($product['purchasable'])
            <template x-if="canAdd">
              <div><span style="color: var(--status-success-text);">&#9679;</span> <span>In Stock &bull; Ready to Fulfill</span></div>
            </template>
            <template x-if="!canAdd">
              <div><span style="color: var(--status-warning-text);">&#9679;</span> <span>Currently unavailable in this option</span></div>
            </template>
          @else
            <div><span style="color: var(--text-muted);">&#9679;</span> <span>Not yet available for purchase &bull; owner has not published inventory</span></div>
          @endif
        </div>

        <!-- Color Options -->
        <div style="margin-bottom: 24px;">
          <div class="picker-label">
            <span>Color Palette:</span>
            <strong x-text="selectedColor"></strong>
          </div>
          <div class="options-row">
            <template x-for="color in colors" :key="color">
              <button
                type="button"
                class="picker-btn"
                :class="{ 'selected': selectedColor === color }"
                @click="selectedColor = color; if (!sizes.includes(selectedSize)) selectedSize = sizes[0] || '';"
                x-text="color"
              ></button>
            </template>
          </div>
        </div>

        <!-- Size Options -->
        <div style="margin-bottom: 28px;">
          <div class="picker-label">
            <span>Select Size:</span>
            <strong x-text="selectedSize"></strong>
          </div>
          <div class="options-row">
            <template x-for="size in sizes" :key="size">
              <button
                type="button"
                class="picker-btn"
                :class="{ 'selected': selectedSize === size }"
                @click="selectedSize = size"
                x-text="size"
              ></button>
            </template>
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
            <button type="button" @click="if (qty < 10) qty++" aria-label="Increase quantity">&plus;</button>
          </div>
        </div>

        <!-- Add to Bag CTA -->
        <div style="display: flex; gap: 16px; margin-bottom: 36px; flex-wrap: wrap;">
          <button
            type="button"
            class="btn-primary"
            style="flex: 1; min-width: 240px; justify-content: center; text-align: center;"
            x-bind:disabled="!canAdd || adding"
            x-bind:aria-disabled="(!canAdd || adding).toString()"
            @click="addToBag()"
          >
            <span x-show="canAdd">
              Add to Bag &bull; <span x-text="'$' + ((priceCents * qty) / 100).toFixed(2)"></span>
            </span>
            <span x-show="!canAdd">
              {{ $product['purchasable'] ? 'Select an Available Option' : 'Not Yet Available' }}
            </span>
          </button>
          <a href="{{ url('/cart') }}" class="btn-secondary" style="justify-content: center;">View Bag</a>
        </div>

        <!-- Craft & Materials Box -->
        @if(! empty($product['specs']))
          <div class="detail-specs-box">
            <h2 style="font-family: var(--font-display); font-size: 1.15rem; color: var(--text-primary); margin-bottom: 12px;">Materials &amp; Sourcing Details</h2>
            <ul>
              @foreach($product['specs'] as $spec)
                <li>{{ $spec }}</li>
              @endforeach
            </ul>
          </div>
        @endif

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
              <x-img
                :src="$rel['image']"
                :alt="$rel['title']"
                class="product-card-photo"
                sizes="(max-width: 620px) 92vw, (max-width: 1024px) 46vw, 360px"
              />
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
