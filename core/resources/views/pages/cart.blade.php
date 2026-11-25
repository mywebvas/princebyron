@extends('layouts.app')

@section('content')
<section class="section-editorial animate-in" style="background: var(--color-cream);">
  <div class="cart-page-wrap">
    
    <!-- Breadcrumb -->
    <nav class="breadcrumb-nav" aria-label="Cart Breadcrumbs">
      <a href="{{ url('/store') }}">&larr; Back to Store</a>
      <span>/</span>
      <span>Your Shopping Bag</span>
    </nav>

    <span class="section-eyebrow">Shopping Bag</span>
    <h1 class="section-title" style="margin-bottom: 6px;">Your Shopping Bag</h1>
    <h2 style="font-family: var(--font-display); font-size: 1.6rem; color: var(--text-primary); margin-bottom: 12px;">Review Your Selection</h2>
    <p class="section-desc" style="margin-bottom: 32px;">
      All digital releases and physical merchandise are calculated authoritatively in USD.
    </p>

    <!-- Empty State -->
    <div x-show="$store.app.cartIsEmpty" style="background: var(--surface-card); border: 1px solid var(--border-light); border-radius: var(--radius-md); padding: 64px 24px; text-align: center; box-shadow: var(--shadow-sm);" x-cloak>
      <h2 style="font-family: var(--font-display); font-size: 1.6rem; color: var(--text-primary); margin-bottom: 12px;">Your shopping bag is currently empty</h2>
      <p style="color: var(--text-muted); max-width: 440px; margin: 0 auto 28px;">
        Explore our curated collection of worship releases and sacred apparel to fill your bag.
      </p>
      <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
        <a href="{{ url('/store') }}" class="btn-primary">Explore Sacred Apparel &rarr;</a>
        <a href="{{ url('/music') }}" class="btn-secondary">Listen to the Music</a>
      </div>
    </div>

    <!-- Filled Cart View -->
    <div x-show="!$store.app.cartIsEmpty">
      <div class="cart-table-wrap">
        <table class="cart-table" aria-label="Shopping bag contents">
          <thead>
            <tr>
              <th scope="col">Item</th>
              <th scope="col">Description &amp; Variant</th>
              <th scope="col">Price</th>
              <th scope="col">Quantity</th>
              <th scope="col">Total</th>
              <th scope="col">Remove</th>
            </tr>
          </thead>
          <tbody>
            <template x-for="item in $store.app.cartItems" :key="item.key">
              <tr>
                <td style="width: 72px;">
                  <div style="width: 56px; height: 56px; background: var(--color-charcoal); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; color: var(--color-gold); font-weight: 700;">
                    PB
                  </div>
                </td>
                <td>
                  <strong x-text="item.title" style="display: block; color: var(--text-primary); font-size: 0.95rem;"></strong>
                  <span x-text="item.variant_label" style="font-size: 0.8rem; color: var(--text-muted);"></span>
                  <span x-show="item.type === 'song'" class="badge" style="background: var(--status-success-bg); color: var(--status-success-text); border: 1px solid var(--status-success-border); font-size: 0.65rem; padding: 2px 6px; border-radius: var(--radius-sm); margin-left: 6px;">Digital Download</span>
                </td>
                <td>
                  <span style="font-weight: 600;" x-text="'$' + (item.unit_price_cents / 100).toFixed(2)"></span>
                </td>
                <td>
                  <div class="cart-qty-ctrl" style="width: 110px;" x-show="item.type === 'variant'">
                    <button type="button" @click="$store.app.decrementItem(item)" aria-label="Decrease quantity">&minus;</button>
                    <span x-text="item.quantity"></span>
                    <button type="button" @click="$store.app.incrementItem(item)" aria-label="Increase quantity">&plus;</button>
                  </div>
                  <span x-show="item.type === 'song'" style="font-size: 0.8rem; color: var(--text-muted);">1</span>
                </td>
                <td>
                  <strong style="color: var(--color-clay); font-size: 1.05rem;" x-text="'$' + (item.line_total_cents / 100).toFixed(2)"></strong>
                </td>
                <td>
                  <button 
                    type="button" 
                    @click="$store.app.removeItem(item)" 
                    style="background: none; border: none; color: var(--status-error-text); cursor: pointer; font-size: 0.82rem; text-decoration: underline;"
                    aria-label="Remove item"
                  >&times; Remove</button>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Cart Bottom Bar & Summary -->
      <div style="margin-top: 36px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 32px;">
        <div style="max-width: 440px;">
          <a href="{{ url('/store') }}" class="btn-secondary" style="margin-bottom: 16px;">&larr; Continue Shopping</a>
          <p style="font-size: 0.82rem; color: var(--text-muted); line-height: 1.5;">
            Pricing and item entitlements are server-verified at checkout. Physical merchandise shipping rates are determined server-side based on destination.
          </p>
        </div>

        <div style="background: var(--surface-card); border: 1px solid var(--border-light); border-radius: var(--radius-md); padding: 28px; width: 100%; max-width: 400px; box-shadow: var(--shadow-sm);">
          <h2 style="font-family: var(--font-display); font-size: 1.25rem; color: var(--text-primary); margin-bottom: 20px;">
            Order Summary
          </h2>
          
          <div style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 0.9rem; color: var(--text-muted);">
            <span>Bag Subtotal:</span>
            <strong style="color: var(--text-primary);" x-text="$store.app.cartSubtotalFormatted"></strong>
          </div>

          <div style="display: flex; justify-content: space-between; margin-bottom: 16px; font-size: 0.9rem; color: var(--text-muted);">
            <span>Shipping:</span>
            <span>Calculated at Checkout</span>
          </div>

          <div style="border-top: 1px solid var(--border-light); padding-top: 16px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: baseline;">
            <span style="font-weight: 700; color: var(--text-primary);">Estimated Total:</span>
            <span style="font-size: 1.4rem; font-weight: 800; color: var(--color-clay);" x-text="$store.app.cartSubtotalFormatted + ' USD'"></span>
          </div>

          <a 
            href="{{ url('/checkout') }}" 
            class="btn-primary" 
            style="width: 100%; text-align: center; justify-content: center; box-sizing: border-box; padding: 16px;"
          >Proceed to Dedicated Checkout &rarr;</a>
        </div>
      </div>
    </div>

  </div>
</section>
@endsection
