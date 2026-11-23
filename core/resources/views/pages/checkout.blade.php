@extends('layouts.app')

@section('content')
<section 
  class="section-editorial" 
  style="background: var(--color-cream);"
  x-data="{
    step: 1,
    contactEmail: 'fan@example.com',
    contactName: 'Demo Supporter',
    contactPhone: '+1 (555) 019-2834',
    shippingAddress: '777 Worship Way, Suite 400',
    shippingCity: 'Atlanta',
    shippingCountry: 'United States',
    shippingPostal: '30303',
    shippingMethod: 'standard',
    shippingFeeCents: 500,
    selectedPayment: 'stripe',
    simulateDecline: false,
    orderCompleted: false,
    orderRef: '',
    orderSuccessStatus: '',

    get totalCents() {
      return $store.app.cartSubtotalCents + this.shippingFeeCents;
    },

    submitCheckout() {
      if (this.simulateDecline && this.selectedPayment === 'stripe') {
        $store.app.showToast('Stripe Decline Simulation: Card #4242 was declined (insufficient_funds). Please choose PayPal or Manual Bank Wire.');
        return;
      }

      this.orderRef = 'PB-202611-' + Math.floor(1000 + Math.random() * 9000);
      if (this.selectedPayment === 'wire') {
        this.orderSuccessStatus = 'Status: Awaiting Bank Verification • Routing instructions dispatched to ' + this.contactEmail;
      } else {
        this.orderSuccessStatus = 'Status: Paid & Settled • Digital Entitlements Active in Fan Vault';
      }

      this.orderCompleted = true;
      $store.app.clearCart();
      $store.app.showToast('Demo order ' + this.orderRef + ' submitted successfully.');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  }"
>
  <div class="checkout-page-wrap">
    
    <!-- Breadcrumb -->
    <nav class="breadcrumb-nav" aria-label="Checkout Breadcrumbs">
      <a href="{{ url('/cart') }}">&larr; Back to Shopping Bag</a>
      <span>/</span>
      <span>Dedicated USD Checkout</span>
    </nav>

    <div style="margin-bottom: 32px;">
      <span class="section-eyebrow">Dedicated Checkout</span>
      <h1 class="section-title" style="margin-bottom: 8px;">Complete Your Order</h1>
      <p class="section-desc" style="margin-bottom: 0;">
        Review your delivery details, payment method, and complete your order.
      </p>
    </div>

    <!-- Success State View -->
    <div x-show="orderCompleted" style="background: var(--surface-card); border: 1px solid var(--border-light); border-radius: var(--radius-md); padding: 56px 36px; text-align: center; box-shadow: var(--shadow-sm);" x-cloak>
      <div style="width: 64px; height: 64px; background: var(--status-success-bg); color: var(--status-success-text); border: 1px solid var(--status-success-border); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 20px;">
        &#10003;
      </div>
      <span class="section-eyebrow" style="color: var(--status-success-text);">Order Confirmation</span>
      <h2 style="font-family: var(--font-display); font-size: 2rem; color: var(--text-primary); margin-bottom: 12px;">Thank you for your order!</h2>
      <p style="color: var(--text-muted); max-width: 520px; margin: 0 auto 24px;">
        Your demonstration order has been created. A receipt and instructions have been simulated for <strong x-text="contactEmail"></strong>.
      </p>
      
      <div style="background: var(--color-paper); border: 1px solid var(--border-light); border-radius: var(--radius-sm); padding: 20px; max-width: 480px; margin: 0 auto 32px; text-align: left;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
          <span style="font-size: 0.85rem; color: var(--text-muted);">Order Reference:</span>
          <strong style="color: var(--color-clay);" x-text="orderRef"></strong>
        </div>
        <div style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;" x-text="orderSuccessStatus"></div>
      </div>

      <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
        <a href="{{ url('/account') }}" class="btn-primary">View in Fan Vault Account &rarr;</a>
        <a href="{{ url('/music') }}" class="btn-secondary">Return to Music Catalog</a>
      </div>
    </div>

    <!-- Active Checkout Flow -->
    <div x-show="!orderCompleted" class="checkout-grid">
      
      <!-- Left: Multi-step Checkout Flow -->
      <div class="checkout-flow-card">
        
        <!-- Stepper Indicator -->
        <div class="checkout-stepper">
          <div class="checkout-step-item" :class="{ 'active': step === 1 }">
            <span class="checkout-step-num">1</span>
            <span>Contact</span>
          </div>
          <span>&rarr;</span>
          <div class="checkout-step-item" :class="{ 'active': step === 2 }">
            <span class="checkout-step-num">2</span>
            <span>Delivery</span>
          </div>
          <span>&rarr;</span>
          <div class="checkout-step-item" :class="{ 'active': step === 3 }">
            <span class="checkout-step-num">3</span>
            <span>Payment Review</span>
          </div>
        </div>

        <form @submit.prevent="if (step === 1) step = 2; else if (step === 2) step = 3; else submitCheckout()">
          
          <!-- Step 1: Contact Information -->
          <div x-show="step === 1">
            <h3 style="font-family: var(--font-display); font-size: 1.35rem; color: var(--color-obsidian); margin-bottom: 16px;">
              Step 1: Contact &amp; Entitlement Account
            </h3>
            
            <div class="form-group" style="margin-bottom: 20px;">
              <label for="chk-email">Email Address (for order receipts &amp; digital links):</label>
              <input type="email" id="chk-email" class="form-control" x-model="contactEmail" required>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="chk-name">Full Name:</label>
                <input type="text" id="chk-name" class="form-control" x-model="contactName" required>
              </div>
              <div class="form-group">
                <label for="chk-phone">Mobile Phone (optional):</label>
                <input type="tel" id="chk-phone" class="form-control" x-model="contactPhone">
              </div>
            </div>

            <div style="margin-top: 28px; display: flex; justify-content: flex-end;">
              <button type="button" class="btn-primary" @click="step = 2">
                Continue to Delivery &rarr;
              </button>
            </div>
          </div>

          <!-- Step 2: Shipping Delivery Address -->
          <div x-show="step === 2" x-cloak>
            <h3 style="font-family: var(--font-display); font-size: 1.35rem; color: var(--color-obsidian); margin-bottom: 16px;">
              Step 2: Shipping &amp; Delivery Destination
            </h3>
            
            <div class="form-group" style="margin-bottom: 20px;">
              <label for="chk-address">Street Address:</label>
              <input type="text" id="chk-address" class="form-control" x-model="shippingAddress" required>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="chk-city">City:</label>
                <input type="text" id="chk-city" class="form-control" x-model="shippingCity" required>
              </div>
              <div class="form-group">
                <label for="chk-postal">Postal Code / ZIP:</label>
                <input type="text" id="chk-postal" class="form-control" x-model="shippingPostal" required>
              </div>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
              <label for="chk-country">Country / Region:</label>
              <select id="chk-country" class="form-control" x-model="shippingCountry">
                <option value="United States">United States</option>
                <option value="United Kingdom">United Kingdom</option>
                <option value="Canada">Canada</option>
                <option value="Nigeria">Nigeria</option>
                <option value="Ghana">Ghana</option>
                <option value="Other">Other Region (Calculated server-side)</option>
              </select>
            </div>

            <div style="background: var(--color-paper); border: 1px solid var(--border-light); border-radius: var(--radius-sm); padding: 18px; margin-bottom: 28px;">
              <strong style="display: block; font-size: 0.9rem; color: var(--color-obsidian); margin-bottom: 8px;">Delivery Option:</strong>
              <div style="display: flex; gap: 16px;">
                <label style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; cursor: pointer;">
                  <input type="radio" name="shipping_method" value="standard" x-model="shippingMethod" @change="shippingFeeCents = 500">
                  Standard Tracked (+$5.00 USD)
                </label>
                <label style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; cursor: pointer;">
                  <input type="radio" name="shipping_method" value="express" x-model="shippingMethod" @change="shippingFeeCents = 1200">
                  Express Courier (+$12.00 USD)
                </label>
              </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center;">
              <button type="button" class="btn-secondary" @click="step = 1">&larr; Back to Contact</button>
              <button type="button" class="btn-primary" @click="step = 3">Continue to Payment Review &rarr;</button>
            </div>
          </div>

          <!-- Step 3: Payment Method Selection -->
          <div x-show="step === 3" x-cloak>
            <h3 style="font-family: var(--font-display); font-size: 1.35rem; color: var(--color-obsidian); margin-bottom: 16px;">
              Step 3: Payment Method Review
            </h3>

            <!-- Payment Options Radio Accordion -->
            <div style="display: grid; gap: 16px; margin-bottom: 28px;">
              
              <!-- Option 1: Stripe -->
              <div 
                style="border: 1px solid var(--border-light); border-radius: var(--radius-sm); padding: 18px; cursor: pointer;"
                :style="selectedPayment === 'stripe' ? 'border-color: var(--color-gold); background: var(--surface-dark-glass);' : 'background: var(--surface-card);'"
                @click="selectedPayment = 'stripe'"
              >
                <label style="display: flex; align-items: center; gap: 12px; font-weight: 700; color: var(--text-primary); cursor: pointer;">
                  <input type="radio" name="payment_method" value="stripe" x-model="selectedPayment">
                  <span>Credit / Debit Card (Stripe Simulation)</span>
                </label>
                <div x-show="selectedPayment === 'stripe'" style="margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border-light);" x-cloak>
                  <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 12px;">
                    Test card: <code class="code-path">4242 &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; 4242</code> &bull; Exp: 12/28 &bull; CVC: 123
                  </p>
                  <label style="display: flex; align-items: center; gap: 8px; font-size: 0.8rem; color: var(--status-error-text); cursor: pointer;">
                    <input type="checkbox" x-model="simulateDecline">
                    <span>Simulate card decline error to test failure recovery</span>
                  </label>
                </div>
              </div>

              <!-- Option 2: PayPal -->
              <div 
                style="border: 1px solid var(--border-light); border-radius: var(--radius-sm); padding: 18px; cursor: pointer;"
                :style="selectedPayment === 'paypal' ? 'border-color: var(--color-gold); background: var(--surface-dark-glass);' : 'background: var(--surface-card);'"
                @click="selectedPayment = 'paypal'"
              >
                <label style="display: flex; align-items: center; gap: 12px; font-weight: 700; color: var(--text-primary); cursor: pointer;">
                  <input type="radio" name="payment_method" value="paypal" x-model="selectedPayment">
                  <span>PayPal (Sandbox Simulation)</span>
                </label>
                <div x-show="selectedPayment === 'paypal'" style="margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border-light); font-size: 0.82rem; color: var(--text-muted);" x-cloak>
                  You will be directed to PayPal's secure sandbox simulation to finalize checkout.
                </div>
              </div>

              <!-- Option 3: Manual Bank Transfer -->
              <div 
                style="border: 1px solid var(--border-light); border-radius: var(--radius-sm); padding: 18px; cursor: pointer;"
                :style="selectedPayment === 'wire' ? 'border-color: var(--color-gold); background: var(--surface-dark-glass);' : 'background: var(--surface-card);'"
                @click="selectedPayment = 'wire'"
              >
                <label style="display: flex; align-items: center; gap: 12px; font-weight: 700; color: var(--text-primary); cursor: pointer;">
                  <input type="radio" name="payment_method" value="wire" x-model="selectedPayment">
                  <span>Manual Bank Wire Transfer</span>
                </label>
                <div x-show="selectedPayment === 'wire'" style="margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border-light); font-size: 0.82rem; color: var(--text-muted);" x-cloak>
                  <div style="background: var(--surface-amber-glow); border-left: 3px solid var(--color-gold); padding: 10px 14px; border-radius: var(--radius-sm); margin-bottom: 8px;">
                    <strong>Verification Requirement:</strong> Order will be placed in an <code class="code-path">awaiting_bank_verification</code> state. The owner must independently reconcile incoming bank funds before digital audio or merchandise is released.
                  </div>
                </div>
              </div>

            </div>

            <!-- Security Reassurance -->
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 28px; line-height: 1.5;">
              &#128274; 256-bit TLS encrypted transaction &bull; Card data is never stored on the server &bull; Authoritative server-side pricing.
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center;">
              <button type="button" class="btn-secondary" @click="step = 2">&larr; Back to Delivery</button>
              <button type="submit" class="btn-primary" style="padding: 16px 32px;">
                Complete Demo Order &bull; <span x-text="'$' + (totalCents / 100).toFixed(2) + ' USD'"></span>
              </button>
            </div>
          </div>

        </form>
      </div>

      <!-- Right: Itemized Order Summary Card -->
      <div>
        <div style="background: var(--surface-card); border: 1px solid var(--border-light); border-radius: var(--radius-md); padding: 28px; box-shadow: var(--shadow-sm); position: sticky; top: 100px;">
          <h2 style="font-family: var(--font-display); font-size: 1.25rem; color: var(--text-primary); margin-bottom: 20px;">
            Items in Bag (<span x-text="$store.app.cartCount"></span>)
          </h2>

          <div style="max-height: 280px; overflow-y: auto; margin-bottom: 20px; border-bottom: 1px solid var(--border-light); padding-bottom: 16px;">
            <template x-for="item in $store.app.cart" :key="item.id + item.variant">
              <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; font-size: 0.88rem;">
                <div>
                  <strong x-text="item.title" style="display: block; color: var(--text-primary);"></strong>
                  <span style="font-size: 0.78rem; color: var(--text-muted);" x-text="item.variant + ' &bull; Qty: ' + item.qty"></span>
                </div>
                <strong style="color: var(--color-clay);" x-text="'$' + ((item.priceCents * item.qty) / 100).toFixed(2)"></strong>
              </div>
            </template>
          </div>

          <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.88rem; color: var(--text-muted);">
            <span>Subtotal:</span>
            <strong style="color: var(--color-obsidian);" x-text="$store.app.cartSubtotalFormatted"></strong>
          </div>

          <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.88rem; color: var(--text-muted);">
            <span>Shipping:</span>
            <span x-text="'$' + (shippingFeeCents / 100).toFixed(2) + ' USD'"></span>
          </div>

          <div style="border-top: 1px solid var(--border-light); padding-top: 16px; margin-top: 16px; display: flex; justify-content: space-between; align-items: baseline;">
            <span style="font-weight: 700; color: var(--color-obsidian);">Total (USD):</span>
            <span style="font-size: 1.4rem; font-weight: 800; color: var(--color-clay);" x-text="'$' + (totalCents / 100).toFixed(2)"></span>
          </div>
        </div>
      </div>

    </div>

  </div>
</section>
@endsection
