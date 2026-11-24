import Alpine from 'alpinejs';

// Setup Global Store
document.addEventListener('alpine:init', () => {
  Alpine.store('app', {
    // Navigation & Drawers
    mobileNavOpen: false,
    bagOpen: false,
    
    // Toast Notification
    toast: {
      visible: false,
      message: '',
      timeout: null
    },

    showToast(msg) {
      if (this.toast.timeout) clearTimeout(this.toast.timeout);
      this.toast.message = msg;
      this.toast.visible = true;
      this.toast.timeout = setTimeout(() => {
        this.toast.visible = false;
      }, 3500);
    },

    // Mobile Navigation Modal
    openMobileNav() {
      this.mobileNavOpen = true;
      this.setModalBackgroundInert(true);
      this.$nextTick(() => {
        const closeBtn = document.getElementById('btn-close-mobile-nav');
        if (closeBtn) closeBtn.focus();
      });
    },

    closeMobileNav() {
      this.mobileNavOpen = false;
      this.setModalBackgroundInert(false);
      const toggleBtn = document.getElementById('btn-menu-toggle');
      if (toggleBtn) toggleBtn.focus();
    },

    // Shopping Bag Modal Drawer
    openBag() {
      this.bagOpen = true;
      this.setModalBackgroundInert(true);
      this.$nextTick(() => {
        const closeBtn = document.getElementById('btn-close-bag');
        if (closeBtn) closeBtn.focus();
      });
    },

    closeBag() {
      this.bagOpen = false;
      this.setModalBackgroundInert(false);
      const openBtn = document.getElementById('btn-open-bag');
      if (openBtn) openBtn.focus();
    },

    // Background Inertness Helper
    setModalBackgroundInert(active) {
      const regions = [
        document.querySelector('.site-header'),
        document.querySelector('main'),
        document.querySelector('.site-footer'),
        document.querySelector('.demo-ribbon'),
        document.querySelector('.skip-link')
      ];
      regions.forEach(el => {
        if (!el) return;
        if (active) {
          el.setAttribute('inert', '');
          el.setAttribute('aria-hidden', 'true');
        } else {
          el.removeAttribute('inert');
          el.removeAttribute('aria-hidden');
        }
      });
    },

    // ======================================================================
    // Cart — server-authoritative
    // ======================================================================
    //
    // Everything about price, stock and availability is decided by the server
    // (CartService/CheckoutService). The browser holds no prices of its own: it
    // sends an item id and receives a fresh summary containing the priced
    // lines. Previously this store held a hardcoded two-item demo bag with
    // fabricated prices (review P0-1), which meant a visitor could "buy"
    // merchandise at prices the server never agreed to.
    //
    // Shape of `cart` after a refresh:
    //   { items: [], item_count, subtotal_cents, has_physical, currency }

    cart: { items: [], item_count: 0, subtotal_cents: 0, has_physical: false, currency: 'USD' },
    cartLoading: false,
    cartError: '',

    get cartItems() {
      return this.cart.items || [];
    },

    get cartCount() {
      return this.cart.item_count || 0;
    },

    get cartSubtotalCents() {
      return this.cart.subtotal_cents || 0;
    },

    get cartSubtotalFormatted() {
      return '$' + (this.cartSubtotalCents / 100).toFixed(2);
    },

    get cartIsEmpty() {
      return this.cartCount === 0;
    },

    /** CSRF token from the layout meta tag. */
    csrfToken() {
      return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    },

    /** Single wrapper for all cart/checkout calls: JSON in, JSON out. */
    async apiFetch(method, url, body = null) {
      const options = {
        method,
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': this.csrfToken()
        },
        credentials: 'same-origin'
      };
      if (body !== null) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
      }

      const response = await fetch(url, options);
      let payload = {};
      try {
        payload = await response.json();
      } catch (e) {
        payload = {};
      }

      if (!response.ok) {
        // 419 = expired session/CSRF; recover by reloading once rather than
        // leaving the visitor with a permanently failing basket.
        if (response.status === 419) {
          window.location.reload();
          return { success: false, message: 'Your session expired. Reloading…' };
        }
        const message = payload.message
          || (payload.errors ? Object.values(payload.errors).flat()[0] : null)
          || 'Something went wrong. Please try again.';
        return { success: false, message, status: response.status, payload };
      }

      return { success: true, payload };
    },

    /** Apply a server cart summary to local state. */
    applyCart(summary) {
      if (!summary) return;
      this.cart = {
        items: summary.items || [],
        item_count: summary.item_count || 0,
        subtotal_cents: summary.subtotal_cents || 0,
        has_physical: !!summary.has_physical,
        currency: summary.currency || 'USD'
      };
    },

    /** Load the current session cart. Called on every page load. */
    async loadCart() {
      this.cartLoading = true;
      const result = await this.apiFetch('GET', '/api/cart');
      this.cartLoading = false;
      if (result.success) this.applyCart(result.payload);
    },

    /**
     * Add a merchandise variant by its database id.
     * Returns true on success so the caller can decide whether to open the bag.
     */
    async addVariantToCart(variantId, quantity = 1) {
      const result = await this.apiFetch('POST', '/api/cart/items', {
        type: 'variant',
        id: variantId,
        quantity: quantity
      });

      if (!result.success) {
        this.showToast(result.message);
        return false;
      }

      this.applyCart(result.payload.cart);
      this.showToast('Added to your shopping bag.');
      return true;
    },

    /** Add a digital song by its database id. */
    async addSongToCart(songId) {
      const result = await this.apiFetch('POST', '/api/cart/items', {
        type: 'song',
        id: songId,
        quantity: 1
      });

      if (!result.success) {
        this.showToast(result.message);
        return false;
      }

      this.applyCart(result.payload.cart);
      this.showToast('Added to your shopping bag.');
      return true;
    },

    /** Change quantity of a cart line. */
    async setItemQuantity(item, quantity) {
      const result = await this.apiFetch('PATCH', '/api/cart/items', {
        type: item.type,
        id: item.id,
        quantity: quantity
      });

      if (!result.success) {
        this.showToast(result.message);
        return false;
      }

      this.applyCart(result.payload.cart);
      return true;
    },

    /** Decrease quantity by one, removing the line at zero. */
    async decrementItem(item) {
      return this.setItemQuantity(item, Math.max(0, (item.quantity || 1) - 1));
    },

    /** Increase quantity by one. */
    async incrementItem(item) {
      return this.setItemQuantity(item, Math.min(10, (item.quantity || 1) + 1));
    },

    /** Remove a cart line. */
    async removeItem(item) {
      const result = await this.apiFetch('DELETE', '/api/cart/items', {
        type: item.type,
        id: item.id
      });

      if (!result.success) {
        this.showToast(result.message);
        return false;
      }

      this.applyCart(result.payload.cart);
      this.showToast(item.title + ' removed from your shopping bag.');
      return true;
    },

    /** Empty the bag. */
    async clearCart() {
      const result = await this.apiFetch('DELETE', '/api/cart');
      if (result.success) {
        this.applyCart(result.payload.cart);
      }
      return result.success;
    },

    // Audio Player Bar & Real Web Audio Playback
    player: {
      active: false,
      playing: false,
      trackTitle: 'Purify Me',
      trackSubtitle: 'Prince Byron • 24-bit Studio Preview',
      progress: 0,
      interval: null
    },

    playTrack(title, priceCents, kind = 'single') {
      this.player.trackTitle = title;
      this.player.trackSubtitle = 'Prince Byron • Ambient Acoustic Demo Preview (Official Master Pending)';
      this.player.active = true;
      this.player.playing = true;
      this.player.progress = 0;
      this.showToast('Playing acoustic demo preview: ' + title);
      playAcousticChords();
      this.startScrubber();
    },

    togglePlay() {
      this.player.playing = !this.player.playing;
      if (this.player.playing) {
        playAcousticChords();
        this.startScrubber();
        this.showToast('Resumed: ' + this.player.trackTitle);
      } else {
        stopAcousticAudio();
        if (this.player.interval) clearInterval(this.player.interval);
        this.showToast('Paused: ' + this.player.trackTitle);
      }
    },

    closePlayer() {
      this.player.active = false;
      this.player.playing = false;
      stopAcousticAudio();
      if (this.player.interval) clearInterval(this.player.interval);
    },

    startScrubber() {
      if (this.player.interval) clearInterval(this.player.interval);
      this.player.interval = setInterval(() => {
        if (!this.player.playing) return;
        this.player.progress += (100 / 30);
        if (this.player.progress >= 100) {
          this.player.progress = 0;
        }
      }, 1000);
    }
  });
});

// ==========================================================================
// Web Audio Ambient Synthesizer (Zero-Latency Harmonic Gospel Chords)
// ==========================================================================
let audioCtx = null;
let currentSynthGain = null;
let synthOscillators = [];
let chordTimer = null;

function getAudioContext() {
  if (!audioCtx) {
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (AudioContextClass) {
      audioCtx = new AudioContextClass();
    }
  }
  if (audioCtx && audioCtx.state === 'suspended') {
    audioCtx.resume();
  }
  return audioCtx;
}

// Gospel chord progression frequencies (Ebmaj7, Cm7, Fm7, Bb7)
const gospelProgressions = [
  [155.56, 196.00, 233.08, 293.66], // Ebmaj7
  [130.81, 155.56, 196.00, 233.08], // Cm7
  [174.61, 207.65, 261.63, 311.13], // Fm7
  [116.54, 174.61, 207.65, 293.66]  // Bb9sus4
];

function playAcousticChords() {
  stopAcousticAudio();
  const ctx = getAudioContext();
  if (!ctx) return;

  const masterGain = ctx.createGain();
  masterGain.gain.setValueAtTime(0.16, ctx.currentTime);
  masterGain.connect(ctx.destination);
  currentSynthGain = masterGain;

  let chordIndex = 0;
  function triggerChord() {
    if (!ctx || ctx.state === 'closed') return;
    const chord = gospelProgressions[chordIndex % gospelProgressions.length];
    chordIndex++;

    chord.forEach((freq, idx) => {
      try {
        const osc = ctx.createOscillator();
        const noteGain = ctx.createGain();
        const filter = ctx.createBiquadFilter();

        filter.type = 'lowpass';
        filter.frequency.setValueAtTime(900, ctx.currentTime);

        osc.type = idx % 2 === 0 ? 'sine' : 'triangle';
        osc.frequency.setValueAtTime(freq, ctx.currentTime);

        const now = ctx.currentTime;
        const delay = idx * 0.06;
        noteGain.gain.setValueAtTime(0.0001, now);
        noteGain.gain.exponentialRampToValueAtTime(0.09 / chord.length, now + 0.12 + delay);
        noteGain.gain.exponentialRampToValueAtTime(0.0001, now + 2.7);

        osc.connect(filter);
        filter.connect(noteGain);
        noteGain.connect(masterGain);

        osc.start(now + delay);
        osc.stop(now + 2.8);
        synthOscillators.push(osc);
      } catch (e) {
        // Safe graceful fallback if audio context is blocked
      }
    });
  }

  triggerChord();
  chordTimer = setInterval(() => {
    triggerChord();
  }, 2600);
}

function stopAcousticAudio() {
  if (chordTimer) {
    clearInterval(chordTimer);
    chordTimer = null;
  }
  if (currentSynthGain && audioCtx && audioCtx.state === 'running') {
    try {
      currentSynthGain.gain.linearRampToValueAtTime(0.0001, audioCtx.currentTime + 0.2);
    } catch (e) {}
  }
  synthOscillators.forEach(osc => {
    try { osc.stop(); } catch (e) {}
  });
  synthOscillators = [];
}

// Modal Focus Trapping Keydown Handler
window.addEventListener('keydown', (e) => {
  const store = window.Alpine?.store('app');
  if (!store) return;

  if (store.mobileNavOpen) {
    if (e.key === 'Escape') {
      store.closeMobileNav();
      return;
    }
    if (e.key === 'Tab') {
      const modal = document.getElementById('mobile-nav-overlay');
      if (modal) trapFocusInDialog(modal, e);
      return;
    }
  }

  if (store.bagOpen) {
    if (e.key === 'Escape') {
      store.closeBag();
      return;
    }
    if (e.key === 'Tab') {
      const drawer = document.getElementById('bag-drawer');
      if (drawer) trapFocusInDialog(drawer, e);
      return;
    }
  }
});

function trapFocusInDialog(container, e) {
  if (e.key !== 'Tab') return;
  const focusables = Array.from(container.querySelectorAll(
    'button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
  )).filter(el => !el.hasAttribute('disabled') && el.offsetParent !== null);

  if (focusables.length === 0) return;
  const first = focusables[0];
  const last = focusables[focusables.length - 1];

  if (e.shiftKey) {
    if (document.activeElement === first || !container.contains(document.activeElement)) {
      e.preventDefault();
      last.focus();
    }
  } else {
    if (document.activeElement === last || !container.contains(document.activeElement)) {
      e.preventDefault();
      first.focus();
    }
  }
}

window.Alpine = Alpine;
Alpine.start();

// Hydrate the bag from the server on every page load. The browser never holds
// a cart that the server does not know about, so a stale tab cannot show a
// price the server would disagree with at checkout.
document.addEventListener('alpine:initialized', () => {
  const store = window.Alpine?.store('app');
  if (store) store.loadCart();
});
