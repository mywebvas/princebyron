@extends('layouts.app')

@section('content')
@auth
  <!-- SaaS Customer Supporter Portal Architecture -->
  <div class="saas-portal" x-data="{ 
    mobileSidebarOpen: false, 
    userDropdownOpen: false, 
    searchQuery: '', 
    ordersOpen: (window.location.hash === '#orders'),
    merchOpen: false,
    helpOpen: (window.location.hash === '#support')
  }">
    
    <!-- 1. Left SaaS Navigation Rail (Desktop Fixed + Mobile Off-Canvas Drawer) -->
    <aside class="saas-sidebar" :class="{ 'open': mobileSidebarOpen }" id="saas-sidebar" aria-label="Customer Portal Navigation">
      
      <!-- Brand & Supporter Portal Header -->
      <div class="saas-sidebar-brand">
        <div class="saas-sidebar-logo" aria-hidden="true">PB</div>
        <div class="saas-sidebar-brand-text">
          <span class="saas-sidebar-title">Prince Byron</span>
          <span class="saas-sidebar-badge">Supporter Portal</span>
        </div>
      </div>

      <!-- Navigation Links -->
      <nav class="saas-sidebar-nav" aria-label="Portal Navigation Links">
        <div class="saas-nav-section-title">My Collection</div>
        
        <a href="#library" class="saas-nav-item active" @click="mobileSidebarOpen = false">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9 18V5l12-2v13"></path>
            <circle cx="6" cy="18" r="3"></circle>
            <circle cx="18" cy="16" r="3"></circle>
          </svg>
          <span>Music Library</span>
          <span class="saas-nav-count">{{ $entitlements->count() }}</span>
        </a>

        <a href="#orders" class="saas-nav-item" @click="mobileSidebarOpen = false; ordersOpen = true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <polyline points="10 9 9 9 8 9"></polyline>
          </svg>
          <span>Past Purchases</span>
          <span class="saas-nav-count">{{ $orders->count() }}</span>
        </a>

        <div class="saas-nav-section-title">Ministry</div>

        <a href="{{ route('booking') }}" class="saas-nav-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="16" y1="2" x2="16" y2="6"></line>
            <line x1="8" y1="2" x2="8" y2="6"></line>
            <line x1="3" y1="10" x2="21" y2="10"></line>
          </svg>
          <span>Ministry Bookings</span>
          <span class="saas-nav-count" style="font-size: 0.65rem; padding: 2px 6px;">RFQ</span>
        </a>

        <div class="saas-nav-section-title">Website</div>

        <a href="{{ url('/') }}" class="saas-nav-item" style="color: var(--color-sand-muted);">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
          </svg>
          <span>Back to Website</span>
        </a>
      </nav>

      <!-- Sidebar User Profile Footer -->
      <div class="saas-sidebar-footer">
        <div class="saas-sidebar-user">
          <div class="saas-sidebar-avatar" aria-hidden="true">
            {{ strtoupper(substr(auth()->user()->name ?: auth()->user()->email, 0, 1)) }}
          </div>
          <div class="saas-sidebar-user-info">
            <span class="saas-sidebar-user-name">{{ auth()->user()->name ?: 'Supporter' }}</span>
            <span class="saas-sidebar-user-role">Verified Supporter</span>
          </div>
          <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
            @csrf
            <button 
              type="submit" 
              title="Sign Out" 
              aria-label="Sign out of account"
              style="background: none; border: none; color: var(--color-sand-muted); cursor: pointer; padding: 6px; border-radius: 4px; display: flex; align-items: center; justify-content: center;"
            >
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
              </svg>
            </button>
          </form>
        </div>
      </div>
    </aside>

    <!-- Mobile Drawer Backdrop -->
    <div 
      class="saas-sidebar-backdrop" 
      x-show="mobileSidebarOpen" 
      @click="mobileSidebarOpen = false" 
      x-cloak 
      aria-hidden="true"
    ></div>

    <!-- 2. Main Portal Container (TopBar + Content) -->
    <div class="saas-portal-main">
      
      <!-- Frictionless SaaS Top Bar -->
      <header class="saas-topbar" aria-label="Portal Header">
        
        <!-- Left: Mobile Menu Toggle + Breadcrumbs -->
        <div class="saas-topbar-left">
          <button 
            type="button" 
            class="saas-menu-toggle" 
            @click="mobileSidebarOpen = !mobileSidebarOpen"
            aria-label="Toggle navigation menu"
            :aria-expanded="mobileSidebarOpen.toString()"
          >
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <line x1="3" y1="12" x2="21" y2="12"></line>
              <line x1="3" y1="6" x2="21" y2="6"></line>
              <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
          </button>
          
          <div class="saas-breadcrumbs" aria-label="Breadcrumb">
            <span>Portal</span>
            <span aria-hidden="true" style="color: var(--text-muted);">&rsaquo;</span>
            <strong>{{ isset($selectedOrder) ? 'Order Receipt #' . $selectedOrder->reference : 'My Library & Orders' }}</strong>
          </div>
        </div>

        <!-- Center: Quick Search Bar with Live Alpine Filtering & ⌘K Hint -->
        <div class="saas-search-box">
          <svg class="saas-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input 
            type="search" 
            x-model="searchQuery" 
            placeholder="Search your songs, orders, or receipts..." 
            class="saas-search-input"
            aria-label="Search your library and orders"
          >
          <span class="saas-search-kbd" aria-hidden="true">&#8984;K</span>
        </div>

        <!-- Right: Actions & User Profile Dropdown -->
        <div class="saas-topbar-right">
          
          <!-- Quick Ministry RFQ Action -->
          <a 
            href="{{ route('booking') }}" 
            class="btn-secondary-sm" 
            style="padding: 7px 14px; font-size: 0.8rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;"
          >
            <span>Invite Prince Byron</span>
          </a>

          <!-- Shopping Bag Trigger -->
          <button 
            type="button" 
            class="btn-bag" 
            id="btn-portal-bag" 
            aria-label="Open Shopping Bag"
            @click="$store.app.openBag()"
            style="margin: 0; padding: 6px 12px;"
          >
            <span>Bag</span>
            <div class="badge-count" x-text="$store.app.cartCount"></div>
          </button>

          <!-- User Profile Dropdown Menu -->
          <div class="saas-profile-dropdown">
            <button 
              type="button" 
              class="saas-profile-trigger" 
              @click="userDropdownOpen = !userDropdownOpen"
              @click.outside="userDropdownOpen = false"
              :aria-expanded="userDropdownOpen.toString()"
              aria-haspopup="true"
              aria-label="User profile and account settings"
            >
              <div class="saas-profile-avatar" aria-hidden="true">
                {{ strtoupper(substr(auth()->user()->name ?: auth()->user()->email, 0, 1)) }}
              </div>
              <span class="saas-profile-name">{{ explode(' ', trim(auth()->user()->name ?: 'Supporter'))[0] }}</span>
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color: var(--text-muted);">
                <polyline points="6 9 12 15 18 9"></polyline>
              </svg>
            </button>

            <!-- Dropdown Popover Menu -->
            <div 
              class="saas-profile-menu" 
              x-show="userDropdownOpen" 
              x-transition:enter="transition ease-out duration-100"
              x-transition:enter-start="transform opacity-0 scale-95"
              x-transition:enter-end="transform opacity-100 scale-100"
              x-transition:leave="transition ease-in duration-75"
              x-transition:leave-start="transform opacity-100 scale-100"
              x-transition:leave-end="transform opacity-0 scale-95"
              x-cloak
              role="menu"
            >
              <div class="saas-menu-header">
                <div class="saas-menu-header-name">{{ auth()->user()->name ?: 'Fan Supporter' }}</div>
                <div class="saas-menu-header-email">{{ auth()->user()->email }}</div>
                <div style="margin-top: 6px;">
                  <span class="badge" style="background: var(--status-success-bg); color: var(--status-success-text); border: 1px solid var(--status-success-border); font-size: 0.7rem; padding: 2px 8px; border-radius: var(--radius-full); font-weight: 700;">
                    &check; Verified Supporter
                  </span>
                </div>
              </div>

              <a href="#library" class="saas-menu-link" @click="userDropdownOpen = false" role="menuitem">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M9 18V5l12-2v13"></path>
                  <circle cx="6" cy="18" r="3"></circle>
                  <circle cx="18" cy="16" r="3"></circle>
                </svg>
                <span>My Music Library</span>
              </a>

              <a href="#orders" class="saas-menu-link" @click="userDropdownOpen = false; ordersOpen = true" role="menuitem">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                  <polyline points="14 2 14 8 20 8"></polyline>
                </svg>
                <span>Past Purchases &amp; Receipts</span>
              </a>

              <a href="{{ route('booking') }}" class="saas-menu-link" role="menuitem">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                  <line x1="16" y1="2" x2="16" y2="6"></line>
                  <line x1="8" y1="2" x2="8" y2="6"></line>
                </svg>
                <span>Ministry Booking Request</span>
              </a>

              <a href="{{ route('store') }}" class="saas-menu-link" role="menuitem">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                </svg>
                <span>Visit Official Store</span>
              </a>

              <!-- Support & Ministry Contact in Dropdown (Moved from Left Panel) -->
              <a href="#support" class="saas-menu-link" @click="userDropdownOpen = false; helpOpen = true" role="menuitem">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <span>Help &amp; Ministry Contact</span>
              </a>

              <div class="saas-menu-divider"></div>

              <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
                @csrf
                <button type="submit" class="saas-menu-logout" role="menuitem">
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                  </svg>
                  <span>Sign Out</span>
                </button>
              </form>
            </div>
          </div>

        </div>
      </header>

      <!-- 3. Expansive Main Workspace Area -->
      <main class="saas-portal-content" id="portal-content">
        
        <!-- Welcome Greeting Banner -->
        <div style="margin-bottom: 24px;">
          <span class="section-eyebrow">Supporter Portal</span>
          <h1 class="section-title" style="margin-bottom: 8px;">Welcome back, {{ auth()->user()->name ?: 'Supporter' }}</h1>
          <p class="section-desc" style="margin-bottom: 0;">
            Stream your gospel music releases, download your studio audio master files, and check on your orders and ministry invitations.
          </p>
        </div>

        <!-- 3 KPI Cards: Card 1 (40% Dark Standout), Card 2 (30%), Card 3 (30%) -->
        <div class="portal-kpi-grid">
          
          <!-- Card 1: Music Library (40% width, Dark Scheme VIP Standout) -->
          <div style="background: var(--surface-dark-card); border: 1.5px solid var(--border-gold); border-radius: var(--radius-md); padding: 22px 24px; box-shadow: var(--shadow-md); color: var(--color-linen); display: flex; flex-direction: column; justify-content: space-between;">
            <div>
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                <span style="font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-gold); font-weight: 700;">
                  Music Library
                </span>
                <span class="badge" style="background: var(--surface-gold-glow); color: var(--color-gold); border: 1px solid var(--border-gold); font-size: 0.68rem; padding: 2px 8px; border-radius: var(--radius-full); font-weight: 700;">
                  VIP Collection
                </span>
              </div>
              <div style="font-family: var(--font-display); font-size: 2.1rem; font-weight: 700; color: var(--color-linen); line-height: 1.1; margin-top: 4px;">
                {{ str_pad($entitlements->count(), 2, '0', STR_PAD_LEFT) }} <span style="font-size: 0.95rem; font-family: var(--font-sans); color: var(--color-sand-soft); font-weight: normal;">Songs in Library</span>
              </div>
            </div>
            <div style="font-size: 0.82rem; color: var(--color-sand-soft); margin-top: 10px; display: flex; align-items: center; gap: 6px;">
              <span style="color: var(--color-gold);">&#9658;</span> Available to stream or download master audio
            </div>
          </div>

          <!-- Card 2: Past Purchases (30% width, Light Scheme, Clickable to Toggle Accordion) -->
          <div 
            style="background: var(--surface-card); border: 1.5px solid var(--border-default); border-radius: var(--radius-md); padding: 22px 24px; box-shadow: var(--shadow-sm); cursor: pointer; display: flex; flex-direction: column; justify-content: space-between;"
            @click="ordersOpen = !ordersOpen"
          >
            <div>
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                <span style="font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); font-weight: 700;">
                  Past Purchases
                </span>
                <span style="font-size: 0.72rem; color: var(--color-clay); font-weight: 600;">
                  <span x-text="ordersOpen ? 'Collapse &uarr;' : 'View &darr;'">View &darr;</span>
                </span>
              </div>
              <div style="font-family: var(--font-display); font-size: 1.9rem; font-weight: 700; color: var(--text-primary); line-height: 1.1; margin-top: 4px;">
                {{ str_pad($orders->count(), 2, '0', STR_PAD_LEFT) }} <span style="font-size: 0.9rem; font-family: var(--font-sans); color: var(--text-muted); font-weight: normal;">Orders</span>
              </div>
            </div>
            <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 10px;">
              Receipts and order confirmations
            </div>
          </div>

          <!-- Card 3: Ministry & Events (30% width, Light Scheme) -->
          <div style="background: var(--surface-card); border: 1.5px solid var(--border-default); border-radius: var(--radius-md); padding: 22px 24px; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; justify-content: space-between;">
            <div>
              <div style="font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); font-weight: 700; margin-bottom: 6px;">
                Ministry &amp; Events
              </div>
              <div style="font-family: var(--font-display); font-size: 1.35rem; font-weight: 700; color: var(--text-primary); line-height: 1.2; margin-top: 4px;">
                Planning an Event?
              </div>
            </div>
            <div style="font-size: 0.82rem; color: var(--color-clay); margin-top: 10px; font-weight: 600;">
              <a href="{{ route('booking') }}" style="color: var(--color-clay); text-decoration: underline;">Invite Prince Byron for ministry &rarr;</a>
            </div>
          </div>

        </div>

        @if(isset($selectedOrder))
          <!-- Itemized Order Detail Receipt Card -->
          <div class="account-card" id="order-receipt-card" style="border: 1.5px solid var(--border-default); box-shadow: var(--shadow-md); margin-bottom: 36px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 20px; flex-wrap: wrap; gap: 16px;">
              <div>
                <span class="section-eyebrow">Customer Order Receipt</span>
                <h2 style="font-family: var(--font-display); font-size: 1.65rem; color: var(--text-primary); margin: 0 0 6px;">
                  Order #{{ $selectedOrder->reference }}
                </h2>
                <div style="font-size: 0.86rem; color: var(--text-muted); line-height: 1.5;">
                  Placed on {{ $selectedOrder->created_at ? $selectedOrder->created_at->format('M d, Y \\a\\t h:i A') : 'Nov 17, 2026' }} &bull; Payment Method: <strong>{{ ucfirst(str_replace('_', ' ', $selectedOrder->payment?->provider ?? 'Card / Online')) }}</strong>
                </div>
              </div>
              <div>
                @if($selectedOrder->status === 'paid')
                  <span class="badge" style="background: var(--status-success-bg); color: var(--status-success-text); border: 1.5px solid var(--status-success-border); font-size: 0.82rem; padding: 6px 14px; border-radius: var(--radius-full); font-weight: 700;">
                    &check; Payment Confirmed
                  </span>
                @elseif($selectedOrder->status === 'awaiting_bank_verification')
                  <span class="badge" style="background: var(--status-warning-bg); color: var(--status-warning-text); border: 1.5px solid var(--status-warning-border); font-size: 0.82rem; padding: 6px 14px; border-radius: var(--radius-full); font-weight: 700;">
                    &#9203; Bank Transfer Pending Verification
                  </span>
                @else
                  <span class="badge" style="background: var(--border-subtle); color: var(--text-primary); border: 1.5px solid var(--border-default); font-size: 0.82rem; padding: 6px 14px; border-radius: var(--radius-full); font-weight: 700;">
                    {{ ucfirst(str_replace('_', ' ', $selectedOrder->status)) }}
                  </span>
                @endif
              </div>
            </div>

            @if($selectedOrder->status === 'awaiting_bank_verification')
              <div style="background: var(--status-warning-bg); border: 1px solid var(--status-warning-border); border-radius: var(--radius-sm); padding: 16px 20px; margin-bottom: 24px; color: var(--status-warning-text); font-size: 0.88rem; line-height: 1.5;">
                <strong>Bank Transfer Received:</strong> Your transfer reference has been recorded. Once your funds arrive and are verified, your order status will be updated to confirmed.
              </div>
            @endif

            <!-- Itemized Table (Desktop) -->
            <div class="cart-table-wrap table-desktop" style="box-shadow: none; margin-bottom: 24px;">
              <table class="cart-table" aria-label="Itemized order items">
                <thead>
                  <tr>
                    <th scope="col">Item Description</th>
                    <th scope="col">Type</th>
                    <th scope="col">Unit Price</th>
                    <th scope="col">Qty</th>
                    <th scope="col" style="text-align: right;">Total</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($selectedOrder->items as $item)
                    <tr>
                      <td>
                        <strong style="color: var(--text-primary); font-size: 0.95rem;">{{ $item->name }}</strong>
                      </td>
                      <td>
                        <span style="font-family: var(--font-mono); font-size: 0.74rem; background: var(--surface-paper); padding: 3px 8px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                          {{ strtoupper(str_replace('_', ' ', $item->item_type)) }}
                        </span>
                      </td>
                      <td style="font-size: 0.9rem; color: var(--text-muted);">
                        ${{ number_format($item->unit_price_cents / 100, 2) }}
                      </td>
                      <td style="font-size: 0.9rem; color: var(--text-primary); font-weight: 600;">
                        {{ $item->quantity }}
                      </td>
                      <td style="text-align: right; font-weight: 700; color: var(--text-primary); font-size: 0.95rem;">
                        ${{ number_format($item->total_cents / 100, 2) }}
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>

            <!-- Itemized Cards (Mobile/Tablet) -->
            <div class="dashboard-cards" style="margin-bottom: 24px;" aria-label="Order items mobile cards">
              @foreach($selectedOrder->items as $item)
                <div class="dashboard-card">
                  <div class="dashboard-card-header">
                    <div>
                      <h3 class="dashboard-card-title">{{ $item->name }}</h3>
                      <div class="dashboard-card-meta">{{ strtoupper(str_replace('_', ' ', $item->item_type)) }}</div>
                    </div>
                    <div style="font-weight: 700; color: var(--text-primary); font-size: 1rem;">
                      ${{ number_format($item->total_cents / 100, 2) }}
                    </div>
                  </div>
                  <div class="dashboard-card-body">
                    <div class="dashboard-card-row">
                      <span style="color: var(--text-muted);">Quantity:</span>
                      <strong>{{ $item->quantity }} &times; ${{ number_format($item->unit_price_cents / 100, 2) }}</strong>
                    </div>
                  </div>
                </div>
              @endforeach
            </div>

            <!-- Receipt Financial Totals -->
            <div style="max-width: 380px; margin-left: auto; background: var(--surface-paper); border: 1px solid var(--border-default); border-radius: var(--radius-md); padding: 20px 24px;">
              <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.9rem; color: var(--text-muted);">
                <span>Subtotal:</span>
                <span style="color: var(--text-primary); font-weight: 600;">${{ number_format($selectedOrder->subtotal_cents / 100, 2) }}</span>
              </div>
              <div style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 0.9rem; color: var(--text-muted);">
                <span>Shipping:</span>
                <span style="color: var(--text-primary); font-weight: 600;">${{ number_format($selectedOrder->shipping_cents / 100, 2) }}</span>
              </div>
              <div style="display: flex; justify-content: space-between; border-top: 2px solid var(--border-default); padding-top: 10px; font-size: 1.2rem; color: var(--color-clay);">
                <span>Total (USD):</span>
                <strong>${{ number_format($selectedOrder->total_cents / 100, 2) }}</strong>
              </div>
            </div>

            <div style="margin-top: 28px; border-top: 1px solid var(--border-subtle); padding-top: 20px;">
              <a href="{{ route('account') }}" class="btn-secondary-sm" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                &larr; Back to My Library
              </a>
            </div>
          </div>
        @endif

        <!-- Two-Column Primary Workspace Grid -->
        <div class="account-grid">
          
          <!-- Left / Primary Column -->
          <div>
            
            <!-- 1. Active Music Collection Section (Always Prominently Visible) -->
            <div class="account-card" id="library" style="border: 1.5px solid var(--border-default); margin-bottom: 24px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                <div>
                  <h2 style="font-family: var(--font-display); font-size: 1.35rem; color: var(--text-primary); margin: 0 0 4px;">
                    Active Digital Entitlements ({{ str_pad($entitlements->count(), 2, '0', STR_PAD_LEFT) }} Tracks)
                  </h2>
                  <p style="font-size: 0.86rem; color: var(--text-muted); margin: 0; display:flex; align-items:center; gap:6px; word-break:break-word;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 18V5l12-2v13"></path>
                        <circle cx="6" cy="18" r="3"></circle>
                        <circle cx="18" cy="16" r="3"></circle>
                    </svg>
                    Listen or download your tracks.
                  </p>
                </div>
                <span style="font-size: 0.78rem; color: var(--color-clay); background: var(--surface-paper); padding: 4px 10px; border-radius: var(--radius-full); border: 1px solid var(--border-subtle); font-weight: 600;">
                  Studio Master Audio
                </span>
              </div>

              <!-- Desktop Table View -->
              <div class="cart-table-wrap table-desktop" style="box-shadow: none; margin-bottom: 0;">
                <table class="cart-table" aria-label="Customer digital audio entitlements">
                  <thead>
                    <tr>
                      <th scope="col">Song &amp; Release</th>
                      <th scope="col">Format</th>
                      <th scope="col">Downloads</th>
                      <th scope="col">Access</th>
                      <th scope="col" style="text-align: right;">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse($entitlements as $entitlement)
                      <tr x-show="!searchQuery || '{{ strtolower($entitlement->song->title) }}'.includes(searchQuery.toLowerCase())">
                        <td>
                          <strong style="color: var(--text-primary); font-size: 0.95rem; display: block;">{{ $entitlement->song->title }}</strong>
                          <span style="font-size: 0.78rem; color: var(--text-muted); display: block; margin-top: 2px;">Prince Byron &bull; {{ $entitlement->song->is_free ? 'Free Ministry Song' : 'Official Single Release' }}</span>
                        </td>
                        <td>
                          <span style="font-family: var(--font-mono); font-size: 0.78rem; background: var(--surface-paper); padding: 3px 8px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                            {{ strtoupper($entitlement->format) }} Master
                          </span>
                        </td>
                        <td style="font-size: 0.85rem; color: var(--text-muted);">
                          Downloaded {{ $entitlement->download_count }} times
                        </td>
                        <td>
                          <span class="badge" style="background: var(--status-success-bg); color: var(--status-success-text); border: 1px solid var(--status-success-border); font-size: 0.72rem; padding: 4px 10px; border-radius: var(--radius-full); font-weight: 700;">
                            <span aria-hidden="true">&check;</span> Ready to Listen
                          </span>
                        </td>
                        <td style="text-align: right;">
                          <div style="display: inline-flex; gap: 8px;">
                            <button 
                              type="button" 
                              class="btn-secondary-sm" 
                              @click="$store.app.playTrack('{{ $entitlement->song->title }}', {{ $entitlement->song->price_cents ?? 0 }})"
                              aria-label="Play {{ $entitlement->song->title }}"
                              style="padding: 6px 12px; font-size: 0.8rem; cursor: pointer;"
                            >&#9658; Play Song</button>
                            <a 
                              href="{{ route('downloads.file', $entitlement->id) }}" 
                              class="btn-primary-sm" 
                              style="text-decoration: none; display: inline-flex; align-items: center; padding: 6px 12px; font-size: 0.8rem;"
                              aria-label="Download master for {{ $entitlement->song->title }}"
                            >Download Audio &darr;</a>
                          </div>
                        </td>
                      </tr>
                    @empty
                      <tr>
                        <td colspan="5" style="text-align: center; padding: 36px; color: var(--text-muted);">
                          You don't have any songs in your library yet. Visit the <a href="{{ url('/music') }}" style="color: var(--color-clay); font-weight: 700; text-decoration: underline;">Music Catalog</a> to listen and add songs to your collection.
                        </td>
                      </tr>
                    @endforelse
                  </tbody>
                </table>
              </div>

              <!-- Mobile Responsive Cards -->
              <div class="dashboard-cards" aria-label="Customer digital audio entitlements mobile cards">
                @forelse($entitlements as $entitlement)
                  <div class="dashboard-card" x-show="!searchQuery || '{{ strtolower($entitlement->song->title) }}'.includes(searchQuery.toLowerCase())">
                    <div class="dashboard-card-header">
                      <div>
                        <h3 class="dashboard-card-title">{{ $entitlement->song->title }}</h3>
                        <div class="dashboard-card-meta">Prince Byron &bull; {{ $entitlement->song->is_free ? 'Free Ministry Song' : 'Official Single' }}</div>
                      </div>
                      <span class="badge" style="background: var(--status-success-bg); color: var(--status-success-text); border: 1px solid var(--status-success-border); font-size: 0.72rem; padding: 3px 8px; border-radius: var(--radius-full); font-weight: 700;">
                        &check; Ready
                      </span>
                    </div>
                    <div class="dashboard-card-body">
                      <div class="dashboard-card-row">
                        <span style="color: var(--text-muted);">Format:</span>
                        <span style="font-family: var(--font-mono); font-size: 0.8rem;">{{ strtoupper($entitlement->format) }} Studio Master</span>
                      </div>
                      <div class="dashboard-card-row">
                        <span style="color: var(--text-muted);">Downloads:</span>
                        <span>{{ $entitlement->download_count }} times downloaded</span>
                      </div>
                    </div>
                    <div class="dashboard-card-actions" style="display: flex; gap: 8px;">
                      <button 
                        type="button" 
                        class="btn-secondary-sm" 
                        @click="$store.app.playTrack('{{ $entitlement->song->title }}', {{ $entitlement->song->price_cents ?? 0 }})"
                        style="flex: 1; justify-content: center; padding: 8px; font-size: 0.8rem; cursor: pointer;"
                      >&#9658; Play Song</button>
                      <a 
                        href="{{ route('downloads.file', $entitlement->id) }}" 
                        class="btn-primary-sm" 
                        style="flex: 1; justify-content: center; text-decoration: none; padding: 8px; font-size: 0.8rem;"
                      >Download &darr;</a>
                    </div>
                  </div>
                @empty
                  <div style="text-align: center; padding: 28px; color: var(--text-muted); background: var(--surface-card-subtle); border-radius: var(--radius-md);">
                    You don't have any songs in your library yet. Visit the <a href="{{ url('/music') }}" style="color: var(--color-clay); font-weight: 700; text-decoration: underline;">Music Catalog</a>.
                  </div>
                @endforelse
              </div>
            </div>

            <!-- 2. Recent Orders & Purchases under Collapsible Accordion Card -->
            <div class="accordion-card" :class="{ 'open': ordersOpen }" id="orders">
              
              <!-- Accordion Toggle Header -->
              <button 
                type="button" 
                class="accordion-toggle-btn"
                @click="ordersOpen = !ordersOpen"
                :aria-expanded="ordersOpen.toString()"
                aria-controls="orders-accordion-content"
              >
                <div>
                  <div style="display: flex; align-items: center; gap: 10px;">
                    <h2 style="font-family: var(--font-display); font-size: 1.3rem; color: var(--text-primary); margin: 0;">
                      Recent Orders ({{ str_pad($orders->count(), 2, '0', STR_PAD_LEFT) }} Purchases)
                    </h2>
                    <span class="badge" style="background: var(--surface-paper); color: var(--text-muted); border: 1px solid var(--border-default); font-size: 0.72rem; padding: 2px 8px; border-radius: var(--radius-full);">
                      {{ $orders->count() }} Records
                    </span>
                  </div>
                  <p style="font-size: 0.84rem; color: var(--text-muted); margin: 4px 0 0;">
                    Click to <span x-text="ordersOpen ? 'hide' : 'view'">view</span> your receipts, order confirmations, and delivery status.
                  </p>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                  <span style="font-size: 0.8rem; font-weight: 600; color: var(--color-clay);" x-text="ordersOpen ? 'Collapse &uarr;' : 'Expand &darr;'">
                    Expand &darr;
                  </span>
                  <svg class="accordion-toggle-chevron" :class="{ 'open': ordersOpen }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="6 9 12 15 18 9"></polyline>
                  </svg>
                </div>
              </button>

              <!-- Collapsed Summary Preview (When closed) -->
              <div x-show="!ordersOpen" style="padding: 0 24px 20px;">
                <div style="background: var(--surface-paper); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; font-size: 0.86rem;">
                  <span style="color: var(--text-muted);">
                    {{ $orders->count() }} verified purchases &bull; Latest: <strong style="color: var(--text-primary);">{{ $orders->first()?->reference ?? 'PB-202611-002' }}</strong> ({{ ucfirst(str_replace('_', ' ', $orders->first()?->status ?? 'Pending')) }})
                  </span>
                  <button type="button" @click="ordersOpen = true" class="btn-secondary-sm" style="padding: 4px 10px; font-size: 0.78rem; cursor: pointer;">
                    Show Order Details &rarr;
                  </button>
                </div>
              </div>

              <!-- Accordion Body Content (When open) -->
              <div class="accordion-body" id="orders-accordion-content" x-show="ordersOpen" x-cloak>
                
                <!-- Desktop Orders Table -->
                <div class="cart-table-wrap table-desktop" style="box-shadow: none; margin-top: 16px; margin-bottom: 0;">
                  <table class="cart-table" aria-label="Customer order history">
                    <thead>
                      <tr>
                        <th scope="col">Order Reference</th>
                        <th scope="col">Date Placed</th>
                        <th scope="col">Payment Method</th>
                        <th scope="col">Total</th>
                        <th scope="col">Order Status</th>
                        <th scope="col" style="text-align: right;">Receipt</th>
                      </tr>
                    </thead>
                    <tbody>
                      @forelse($orders as $order)
                        <tr x-show="!searchQuery || '{{ strtolower($order->reference) }}'.includes(searchQuery.toLowerCase())">
                          <td>
                            <a href="{{ route('account.order', $order->reference) }}" style="color: var(--color-clay); font-weight: 700; text-decoration: underline; font-family: var(--font-mono); font-size: 0.88rem;">
                              {{ $order->reference }} &rarr;
                            </a>
                          </td>
                          <td style="font-size: 0.85rem; color: var(--text-muted);">
                            {{ $order->created_at ? $order->created_at->format('M d, Y') : 'Nov 17, 2026' }}
                          </td>
                          <td style="font-size: 0.85rem; color: var(--text-primary);">
                            {{ ucfirst(str_replace('_', ' ', $order->payment?->provider ?? 'Card / Online')) }}
                          </td>
                          <td style="font-size: 0.95rem; font-weight: 700; color: var(--text-primary);">
                            ${{ number_format($order->total_cents / 100, 2) }} <span style="font-size: 0.75rem; font-weight: normal; color: var(--text-muted);">{{ $order->currency }}</span>
                          </td>
                          <td>
                            @if($order->status === 'paid')
                              <span class="badge" style="background: var(--status-success-bg); color: var(--status-success-text); border: 1px solid var(--status-success-border); font-size: 0.72rem; padding: 4px 10px; border-radius: var(--radius-full); font-weight: 700;">
                                &check; Payment Confirmed
                              </span>
                            @elseif($order->status === 'awaiting_bank_verification')
                              <span class="badge" style="background: var(--status-warning-bg); color: var(--status-warning-text); border: 1px solid var(--status-warning-border); font-size: 0.72rem; padding: 4px 10px; border-radius: var(--radius-full); font-weight: 700;" title="We've received your transfer details and will verify once funds clear">
                                &#9203; Bank Transfer Pending
                              </span>
                            @else
                              <span class="badge" style="background: var(--border-subtle); color: var(--text-primary); border: 1px solid var(--border-default); font-size: 0.72rem; padding: 4px 10px; border-radius: var(--radius-full); font-weight: 700;">
                                {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                              </span>
                            @endif
                          </td>
                          <td style="text-align: right;">
                            <a href="{{ route('account.order', $order->reference) }}" class="btn-secondary-sm" style="padding: 5px 12px; font-size: 0.8rem; text-decoration: none;">
                              View Receipt &rarr;
                            </a>
                          </td>
                        </tr>
                      @empty
                        <tr>
                          <td colspan="6" style="text-align: center; padding: 36px; color: var(--text-muted);">
                            No orders placed yet.
                          </td>
                        </tr>
                      @endforelse
                    </tbody>
                  </table>
                </div>

                <!-- Mobile Orders Cards -->
                <div class="dashboard-cards" style="margin-top: 16px;" aria-label="Customer order history mobile cards">
                  @forelse($orders as $order)
                    <div class="dashboard-card" x-show="!searchQuery || '{{ strtolower($order->reference) }}'.includes(searchQuery.toLowerCase())">
                      <div class="dashboard-card-header">
                        <div>
                          <h3 class="dashboard-card-title">{{ $order->reference }}</h3>
                          <div class="dashboard-card-meta">{{ $order->created_at ? $order->created_at->format('M d, Y') : 'Nov 17, 2026' }}</div>
                        </div>
                        <div>
                          @if($order->status === 'paid')
                            <span class="badge" style="background: var(--status-success-bg); color: var(--status-success-text); border: 1px solid var(--status-success-border); font-size: 0.72rem; padding: 4px 8px; border-radius: var(--radius-full); font-weight: 700;">
                              &check; Confirmed
                            </span>
                          @elseif($order->status === 'awaiting_bank_verification')
                            <span class="badge" style="background: var(--status-warning-bg); color: var(--status-warning-text); border: 1px solid var(--status-warning-border); font-size: 0.72rem; padding: 4px 8px; border-radius: var(--radius-full); font-weight: 700;">
                              &#9203; Pending Wire
                            </span>
                          @else
                            <span class="badge" style="background: var(--border-subtle); color: var(--text-primary); border: 1px solid var(--border-default); font-size: 0.72rem; padding: 4px 8px; border-radius: var(--radius-full); font-weight: 700;">
                              {{ ucfirst($order->status) }}
                            </span>
                          @endif
                        </div>
                      </div>
                      <div class="dashboard-card-body">
                        <div class="dashboard-card-row">
                          <span style="color: var(--text-muted);">Payment:</span>
                          <strong>{{ ucfirst(str_replace('_', ' ', $order->payment?->provider ?? 'Card / Online')) }}</strong>
                        </div>
                        <div class="dashboard-card-row" style="font-size: 1.05rem; color: var(--color-clay); font-weight: 700;">
                          <span>Order Total:</span>
                          <span>${{ number_format($order->total_cents / 100, 2) }} {{ $order->currency }}</span>
                        </div>
                      </div>
                      <div class="dashboard-card-actions">
                        <a href="{{ route('account.order', $order->reference) }}" class="btn-primary-sm" style="text-decoration: none; width: 100%; text-align: center;">
                          View Itemized Receipt &rarr;
                        </a>
                      </div>
                    </div>
                  @empty
                    <div style="text-align: center; padding: 28px; color: var(--text-muted); background: var(--surface-card-subtle); border-radius: var(--radius-md);">
                      No orders placed yet.
                    </div>
                  @endforelse
                </div>

              </div>
            </div>

          </div>

          <!-- Right / Utility Rail Column -->
          <div>
            
            <!-- 1. Ministry Invitation Card (Always Prominently Visible) -->
            <div class="account-card" id="ministry" style="border: 1.5px solid var(--border-default); margin-bottom: 24px; padding: 24px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); font-weight: 700;">
                  Church &amp; Worship Invitations
                </span>
                <span class="badge" style="background: var(--status-info-bg); color: var(--status-info-text); border: 1px solid var(--status-info-border); font-size: 0.7rem; padding: 2px 8px; border-radius: var(--radius-full); font-weight: 700;">
                  Booking RFQ
                </span>
              </div>
              <h3 style="font-family: var(--font-display); font-size: 1.15rem; color: var(--text-primary); margin: 0 0 6px;">
                Invite Prince Byron for Ministry
              </h3>
              <p style="font-size: 0.82rem; color: var(--text-muted); line-height: 1.45; margin: 0;">
                Prince Byron welcomes invitations for church worship services, revival nights, and sacred music concerts.
              </p>

              <!-- Warm Progress Stepper -->
              <ul class="stepper-list">
                <li class="stepper-item">
                  <span class="stepper-num">1</span>
                  <div class="stepper-content">
                    <strong>1. Submit Your Request</strong>
                    Share your event details, church location, and preferred dates.
                  </div>
                </li>
                <li class="stepper-item">
                  <span class="stepper-num">2</span>
                  <div class="stepper-content">
                    <strong>2. Calendar Review</strong>
                    Our ministry coordinator reviews schedule and travel logistics.
                  </div>
                </li>
                <li class="stepper-item">
                  <span class="stepper-num">3</span>
                  <div class="stepper-content">
                    <strong>3. Custom Proposal</strong>
                    We provide an honorarium proposal and event details privately.
                  </div>
                </li>
                <li class="stepper-item">
                  <span class="stepper-num">4</span>
                  <div class="stepper-content">
                    <strong>4. Ministry Confirmed</strong>
                    We pray together, agree on the arrangements, and finalize the date.
                  </div>
                </li>
              </ul>

              <a href="{{ route('booking') }}" class="btn-primary-sm" style="width: 100%; justify-content: center; text-decoration: none; font-size: 0.82rem; padding: 10px 14px;">
                Request Ministry Appearance &rarr;
              </a>
            </div>

            <!-- 2. Official Merchandise Card under Collapsible Accordion -->
            <div class="accordion-card" :class="{ 'open': merchOpen }">
              <button 
                type="button" 
                class="accordion-toggle-btn"
                @click="merchOpen = !merchOpen"
                :aria-expanded="merchOpen.toString()"
                style="padding: 18px 20px;"
              >
                <div>
                  <div style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); font-weight: 700; margin-bottom: 2px;">
                    Official Merchandise
                  </div>
                  <h3 style="font-family: var(--font-display); font-size: 1.05rem; color: var(--text-primary); margin: 0;">
                    Prince Byron Apparel &amp; Store
                  </h3>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                  <span style="font-size: 0.78rem; font-weight: 600; color: var(--color-clay);" x-text="merchOpen ? 'Hide &uarr;' : 'Store &darr;'">Store &darr;</span>
                  <svg class="accordion-toggle-chevron" :class="{ 'open': merchOpen }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="6 9 12 15 18 9"></polyline>
                  </svg>
                </div>
              </button>

              <div class="accordion-body" x-show="merchOpen" x-cloak style="padding: 0 20px 20px;">
                <p style="font-size: 0.82rem; color: var(--text-muted); line-height: 1.45; margin: 12px 0 16px;">
                  Wear the message. High-craft gospel apparel and accessories supporting the ongoing music ministry.
                </p>
                <a href="{{ route('store') }}" class="btn-secondary-sm" style="width: 100%; justify-content: center; text-decoration: none; font-size: 0.82rem; padding: 9px 14px;">
                  Browse Store Catalog &rarr;
                </a>
              </div>
            </div>

            <!-- 3. We're Here to Help (Ministry Support Team) under Collapsible Accordion Card -->
            <div class="accordion-card" :class="{ 'open': helpOpen }" id="support">
              <button 
                type="button" 
                class="accordion-toggle-btn"
                @click="helpOpen = !helpOpen"
                :aria-expanded="helpOpen.toString()"
                style="padding: 18px 20px;"
              >
                <div>
                  <div style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); font-weight: 700; margin-bottom: 2px;">
                    We're Here to Help
                  </div>
                  <h3 style="font-family: var(--font-display); font-size: 1.05rem; color: var(--text-primary); margin: 0;">
                    Ministry Support Team
                  </h3>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                  <span style="font-size: 0.78rem; font-weight: 600; color: var(--color-clay);" x-text="helpOpen ? 'Hide &uarr;' : 'Contact &darr;'">Contact &darr;</span>
                  <svg class="accordion-toggle-chevron" :class="{ 'open': helpOpen }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="6 9 12 15 18 9"></polyline>
                  </svg>
                </div>
              </button>

              <div class="accordion-body" x-show="helpOpen" x-cloak style="padding: 0 20px 20px;">
                <p style="font-size: 0.82rem; color: var(--text-muted); line-height: 1.45; margin: 12px 0 14px;">
                  If you have any difficulty downloading your audio files, need an updated receipt, or have questions about a booking, please reach out.
                </p>
                <div style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 14px;">
                  &bull; We usually reply within a few hours on weekdays.<br>
                  &bull; Email: <strong style="color: var(--text-primary);">ministry@princebyron.local</strong>
                </div>
                <a href="mailto:ministry@princebyron.local" class="btn-secondary-sm" style="width: 100%; justify-content: center; text-decoration: none; font-size: 0.82rem; padding: 9px 14px;">
                  Email Our Team &rarr;
                </a>
              </div>
            </div>

          </div>
        </div>

      </main>
    </div>

  </div>

@else
  <!-- Guest / Unauthenticated Supporter View -->
  <section class="section-editorial" style="background: var(--surface-paper); min-height: 80vh; padding: 60px 20px;">
    <div class="section-container" style="max-width: 600px; margin: 0 auto;" x-data="{ tab: '{{ ($authMode ?? 'login') === 'register' ? 'register' : 'login' }}' }">
      
      <div style="text-align: center; margin-bottom: 28px;">
        <span class="section-eyebrow">Customer Fan Vault</span>
        <h1 class="section-title" style="margin-bottom: 8px;">Welcome to Your Library</h1>
        <p class="section-desc" style="margin-bottom: 0;">
          Sign in to listen to your music releases, download your studio audio master files, and view your receipts.
        </p>
      </div>

      <!-- Local Walkthrough Demo Credentials Banner -->
      <div style="background: var(--surface-dark-card); border: 1.5px solid var(--border-gold); border-radius: var(--radius-md); padding: 22px 24px; margin-bottom: 28px; color: var(--color-linen); box-shadow: var(--shadow-md);">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
          <span style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 1.5px; color: var(--color-gold); font-weight: 700;">
            Local Demo Access &bull; Verified Credentials
          </span>
          <span style="font-size: 0.72rem; background: var(--surface-gold-glow); color: var(--color-gold); padding: 3px 10px; border-radius: var(--radius-sm); font-weight: 700;">
            LOCAL WALKTHROUGH
          </span>
        </div>
        <div style="font-size: 0.9rem; line-height: 1.6; color: var(--color-linen);">
          <strong>Customer Login:</strong> <code style="background: var(--surface-dark-glass); border: 1px solid var(--border-dark-subtle); padding: 2px 8px; border-radius: 4px; color: var(--color-gold);">fan@princebyron.local</code> (or <code>fan@example.com</code>)<br>
          <strong>Password:</strong> <code style="background: var(--surface-dark-glass); border: 1px solid var(--border-dark-subtle); padding: 2px 8px; border-radius: 4px; color: var(--color-gold);">TemporaryFanPass2026!</code>
        </div>
        <div style="margin-top: 12px; font-size: 0.8rem; color: var(--color-sand-soft); border-top: 1px solid var(--border-dark-subtle); padding-top: 10px; line-height: 1.5;">
          This demo account includes Active Digital Entitlements for <strong>Purify Me (Single Master)</strong> and <strong>Free (Lead Track)</strong>, Recent Orders including sample paid order PB-202611-001, pending bank transfer PB-202611-002, and active download entitlements for testing.
        </div>
      </div>

      @if ($errors->any())
        <div style="background: var(--status-error-bg); border: 1.5px solid var(--status-error-border); color: var(--status-error-text); padding: 14px 18px; border-radius: var(--radius-sm); margin-bottom: 24px; font-size: 0.9rem; line-height: 1.5;">
          <div style="font-weight: 700; margin-bottom: 4px;">Please review the following:</div>
          @foreach ($errors->all() as $error)
            <div>&bull; {{ $error }}</div>
          @endforeach
        </div>
      @endif

      <div style="background: var(--surface-card); border: 1.5px solid var(--border-default); border-radius: var(--radius-md); padding: 36px 32px; box-shadow: var(--shadow-sm);">
        
        <!-- Auth Mode Toggle Tabs -->
        <div style="display: flex; border-bottom: 1.5px solid var(--border-default); margin-bottom: 28px;">
          <button 
            type="button" 
            @click="tab = 'login'" 
            :style="tab === 'login' ? 'border-bottom: 2.5px solid var(--color-clay); color: var(--color-clay); font-weight: 700;' : 'color: var(--text-muted);'"
            style="flex: 1; padding: 12px; background: none; border: none; font-size: 0.98rem; cursor: pointer; transition: all 0.2s;"
          >
            Sign In to My Library
          </button>
          <button 
            type="button" 
            @click="tab = 'register'" 
            :style="tab === 'register' ? 'border-bottom: 2.5px solid var(--color-clay); color: var(--color-clay); font-weight: 700;' : 'color: var(--text-muted);'"
            style="flex: 1; padding: 12px; background: none; border: none; font-size: 0.98rem; cursor: pointer; transition: all 0.2s;"
          >
            Create Supporter Account
          </button>
        </div>

        <!-- Sign In Form -->
        <form method="POST" action="{{ route('login') }}" x-show="tab === 'login'">
          @csrf
          <div class="form-group">
            <label for="login-email">Email Address</label>
            <input 
              type="email" 
              id="login-email" 
              name="email" 
              class="form-control"
              value="{{ old('email', 'fan@princebyron.local') }}" 
              required 
              autocomplete="username"
            >
            <div class="form-helper">The email address associated with your digital orders or supporter account.</div>
          </div>

          <div class="form-group">
            <label for="login-password">Password</label>
            <input 
              type="password" 
              id="login-password" 
              name="password" 
              class="form-control"
              value="TemporaryFanPass2026!" 
              required 
              autocomplete="current-password"
            >
          </div>

          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <label style="font-size: 0.88rem; color: var(--text-muted); display: flex; align-items: center; gap: 8px; cursor: pointer;">
              <input type="checkbox" name="remember" checked style="width: 16px; height: 16px; accent-color: var(--color-clay);"> Remember this device
            </label>
          </div>

          <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 14px; font-size: 1rem;">
            Sign In to My Library &rarr;
          </button>
        </form>

        <!-- Register Form -->
        <form method="POST" action="{{ route('register') }}" x-show="tab === 'register'" style="display: none;">
          @csrf
          <div class="form-group">
            <label for="reg-name">Full Name <span style="color: var(--color-clay);">*</span></label>
            <input 
              type="text" 
              id="reg-name" 
              name="name" 
              class="form-control"
              value="{{ old('name') }}" 
              required 
              placeholder="Sister Grace / Brother Paul" 
              autocomplete="name"
            >
          </div>

          <div class="form-group">
            <label for="reg-email">Email Address <span style="color: var(--color-clay);">*</span></label>
            <input 
              type="email" 
              id="reg-email" 
              name="email" 
              class="form-control"
              value="{{ old('email') }}" 
              required 
              placeholder="supporter@example.com" 
              autocomplete="email"
            >
            <div class="form-helper">Your order receipts and download links will be sent here.</div>
          </div>

          <div class="form-group">
            <label for="reg-password">Password (Minimum 8 Characters) <span style="color: var(--color-clay);">*</span></label>
            <input 
              type="password" 
              id="reg-password" 
              name="password" 
              class="form-control" 
              required 
              autocomplete="new-password"
            >
          </div>

          <div class="form-group">
            <label for="reg-password-confirm">Confirm Password <span style="color: var(--color-clay);">*</span></label>
            <input 
              type="password" 
              id="reg-password-confirm" 
              name="password_confirmation" 
              class="form-control" 
              required 
              autocomplete="new-password"
            >
          </div>

          <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 14px; font-size: 1rem;">
            Create Supporter Account &rarr;
          </button>
        </form>

      </div>
    </div>
  </section>
@endauth
@endsection
