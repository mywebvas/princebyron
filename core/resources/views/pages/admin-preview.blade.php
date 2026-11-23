@extends('layouts.app')

@section('content')
<section 
  class="section-editorial" 
  style="background: var(--surface-paper); padding: 0 0 80px; min-height: 85vh; color: var(--text-primary);"
  x-data="{
    tab: 'music'
  }"
>
  <div class="admin-preview-wrapper" style="background: var(--surface-paper);">
    
    <!-- Top Admin Security & Context Banner -->
    <div class="admin-notice-bar" role="region" aria-label="Demo Notice" style="background: var(--surface-dark-card); border-bottom: 2px solid var(--color-gold); padding: 16px 28px;">
      <div style="max-width: 1360px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div class="admin-notice-content" style="max-width: 900px;">
          <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
            <span class="admin-role-badge" style="background: var(--surface-gold-glow); color: var(--color-gold); border: 1px solid var(--border-gold); padding: 2px 10px; border-radius: var(--radius-sm); font-size: 0.76rem; font-weight: 700; text-transform: uppercase;">
              Public Demonstration Preview
            </span>
            <span style="font-size: 0.8rem; color: var(--color-sand-soft); font-weight: 600;">
              Read-Only Theme &amp; Layout Mockup
            </span>
          </div>
          <p style="font-size: 0.85rem; color: var(--color-linen); margin: 0; line-height: 1.5;">
            <strong>Public Demonstration Mode (Read-Only Preview):</strong> This screen illustrates the visual theme and task layout of the planned owner workspace using static sample fixtures. It requires no authentication, loads no live operational data, and provides no actions that modify database records. Live operational queues and record actions require authenticated owner access on the server at <a href="{{ url('/admin') }}" style="color: var(--color-gold); font-weight: 700; text-decoration: underline;">Live Owner Filament CMS (/admin)</a>.
          </p>
        </div>
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
          <a href="{{ url('/admin') }}" class="btn-primary-sm" style="background: var(--color-gold); color: var(--color-obsidian); text-decoration: none; font-weight: 700;">
            Owner Admin (/admin) &rarr;
          </a>
          <div class="admin-session-pill" style="font-size: 0.78rem; color: var(--color-sand-soft); background: var(--surface-dark-glass); padding: 6px 12px; border-radius: var(--radius-full); border: 1px solid var(--border-dark-subtle);">
            <span class="admin-session-dot" aria-hidden="true" style="color: var(--color-gold);">&#9679;</span> Non-Actionable Preview
          </div>
        </div>
      </div>
    </div>

    <div class="admin-container" style="max-width: 1360px; margin: 0 auto; padding: 36px 28px;">
      
      <!-- Admin Header Row -->
      <div class="admin-header-row" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px; flex-wrap: wrap; gap: 20px;">
        <div>
          <nav class="admin-breadcrumb" aria-label="Admin Navigation Breadcrumb" style="font-size: 0.86rem; color: var(--text-muted); margin-bottom: 8px;">
            <a href="{{ url('/') }}" style="color: var(--color-clay); text-decoration: none; font-weight: 600;">&larr; Return to Public Site</a>
            <span aria-hidden="true" style="margin: 0 8px; color: var(--border-strong);">/</span>
            <strong>Owner Backoffice Workspace (Public Preview)</strong>
          </nav>
          <h1 style="font-family: var(--font-display); font-size: 2.2rem; color: var(--text-primary); margin: 0; line-height: 1.15;">
            Ministry &amp; Commerce Management
          </h1>
          <p style="font-size: 0.9rem; color: var(--text-muted); margin: 6px 0 0;">
            Visual demonstration of the owner interface layout and task grouping using static sample data.
          </p>
        </div>
        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
          <a href="{{ url('/admin') }}" class="btn-primary" style="text-decoration: none; font-size: 0.9rem; padding: 10px 18px;">
            Live Owner CMS (/admin) &rarr;
          </a>
          <a href="{{ url('/admin/audit-events') }}" class="btn-secondary" style="font-size: 0.9rem; padding: 10px 18px; text-decoration: none;">
            Live Audit Events (/admin) &rarr;
          </a>
        </div>
      </div>

      <!-- Sample Preview Queues (Read-Only Demonstration) -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 32px;">
        <!-- Queue 1: Sample Bank Reconciliation -->
        <div style="background: var(--surface-card); border: 1.5px solid var(--border-default); border-radius: var(--radius-md); padding: 20px; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; justify-content: space-between;">
          <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
              <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.08em;">
                Sample Bank Wire Queue
              </span>
              <span class="badge" style="background: var(--status-warning-bg); color: var(--status-warning-text); border: 1px solid var(--status-warning-border); font-size: 0.72rem; padding: 3px 8px; border-radius: var(--radius-full); font-weight: 700;">
                1 Sample Pending
              </span>
            </div>
            <div style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">
              Order PB-202611-002 ($93.00)
            </div>
            <div style="font-size: 0.82rem; color: var(--text-muted); line-height: 1.4;">
              Sample fixture illustrating pending wire verification before physical order dispatch.
            </div>
          </div>
          <div style="margin-top: 14px;">
            <a href="{{ url('/admin/orders') }}" class="btn-secondary-sm" style="width: 100%; justify-content: center; text-decoration: none;">
              Reconcile in Live CMS (/admin) &rarr;
            </a>
            <span style="font-size: 0.74rem; color: var(--text-muted); display: block; text-align: center; margin-top: 6px;">
              Live actions require owner authentication
            </span>
          </div>
        </div>

        <!-- Queue 2: Sample Ministry Booking Invitations -->
        <div style="background: var(--surface-card); border: 1.5px solid var(--border-default); border-radius: var(--radius-md); padding: 20px; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; justify-content: space-between;">
          <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
              <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.08em;">
                Sample Ministry Bookings
              </span>
              <span class="badge" style="background: var(--status-info-bg); color: var(--status-info-text); border: 1px solid var(--status-info-border); font-size: 0.72rem; padding: 3px 8px; border-radius: var(--radius-full); font-weight: 700;">
                2 Sample RFQs
              </span>
            </div>
            <div style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">
              Ministry Invitations
            </div>
            <div style="font-size: 0.82rem; color: var(--text-muted); line-height: 1.4;">
              Sample requests: Sunday service (Dallas, TX) &amp; worship night (Atlanta, GA).
            </div>
          </div>
          <div style="margin-top: 14px;">
            <a href="{{ url('/admin/booking-requests') }}" class="btn-secondary-sm" style="width: 100%; justify-content: center; text-decoration: none;">
              Manage RFQs in Live CMS &rarr;
            </a>
            <span style="font-size: 0.74rem; color: var(--text-muted); display: block; text-align: center; margin-top: 6px;">
              Server-gated owner access
            </span>
          </div>
        </div>

        <!-- Queue 3: Sample Shipping Rates Setup Status -->
        <div style="background: var(--surface-card); border: 1.5px solid var(--border-default); border-radius: var(--radius-md); padding: 20px; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; justify-content: space-between;">
          <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
              <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.08em;">
                Shipping Rates &bull; Demo
              </span>
              <span class="badge" style="background: var(--surface-paper); color: var(--text-muted); border: 1px solid var(--border-default); font-size: 0.72rem; padding: 3px 8px; border-radius: var(--radius-full); font-weight: 700;">
                Owner Setup Pending
              </span>
            </div>
            <div style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">
              Destination Zones &amp; Rates
            </div>
            <div style="font-size: 0.82rem; color: var(--text-muted); line-height: 1.4;">
              Demonstration rates. Require owner configuration in live backoffice before production shipping.
            </div>
          </div>
          <div style="margin-top: 14px;">
            <a href="{{ url('/admin/shipping-rates') }}" class="btn-secondary-sm" style="width: 100%; justify-content: center; text-decoration: none;">
              Configure in Live CMS &rarr;
            </a>
            <span style="font-size: 0.74rem; color: var(--text-muted); display: block; text-align: center; margin-top: 6px;">
              Server-gated owner access
            </span>
          </div>
        </div>
      </div>

      <!-- Admin Navigation Tabs (Light-First Design) -->
      <div class="admin-tabs-nav" role="tablist" aria-label="Admin Management Tabs" style="display: flex; gap: 8px; border-bottom: 2px solid var(--border-default); margin-bottom: 28px; overflow-x: auto;">
        <button 
          type="button" 
          role="tab" 
          class="admin-tab" 
          :class="{ 'active': tab === 'music' }" 
          @click="tab = 'music'"
          :style="tab === 'music' ? 'color: var(--color-clay); border-bottom: 2.5px solid var(--color-clay); font-weight: 700;' : 'color: var(--text-muted); font-weight: 600; border-bottom: 2.5px solid transparent;'"
          style="padding: 12px 18px; background: none; border-top: none; border-left: none; border-right: none; font-size: 0.95rem; cursor: pointer; transition: all 0.2s; white-space: nowrap;"
        >&#9835; Music Catalog &amp; Audio</button>
        <button 
          type="button" 
          role="tab" 
          class="admin-tab" 
          :class="{ 'active': tab === 'products' }" 
          @click="tab = 'products'"
          :style="tab === 'products' ? 'color: var(--color-clay); border-bottom: 2.5px solid var(--color-clay); font-weight: 700;' : 'color: var(--text-muted); font-weight: 600; border-bottom: 2.5px solid transparent;'"
          style="padding: 12px 18px; background: none; border-top: none; border-left: none; border-right: none; font-size: 0.95rem; cursor: pointer; transition: all 0.2s; white-space: nowrap;"
        >&#128085; Products &amp; Variants</button>
        <button 
          type="button" 
          role="tab" 
          class="admin-tab" 
          :class="{ 'active': tab === 'orders' }" 
          @click="tab = 'orders'"
          :style="tab === 'orders' ? 'color: var(--color-clay); border-bottom: 2.5px solid var(--color-clay); font-weight: 700;' : 'color: var(--text-muted); font-weight: 600; border-bottom: 2.5px solid transparent;'"
          style="padding: 12px 18px; background: none; border-top: none; border-left: none; border-right: none; font-size: 0.95rem; cursor: pointer; transition: all 0.2s; white-space: nowrap;"
        >&#128179; Orders &amp; Bank Payments</button>
        <button 
          type="button" 
          role="tab" 
          class="admin-tab" 
          :class="{ 'active': tab === 'bookings' }" 
          @click="tab = 'bookings'"
          :style="tab === 'bookings' ? 'color: var(--color-clay); border-bottom: 2.5px solid var(--color-clay); font-weight: 700;' : 'color: var(--text-muted); font-weight: 600; border-bottom: 2.5px solid transparent;'"
          style="padding: 12px 18px; background: none; border-top: none; border-left: none; border-right: none; font-size: 0.95rem; cursor: pointer; transition: all 0.2s; white-space: nowrap;"
        >&#128197; Ministry Bookings</button>
        <button 
          type="button" 
          role="tab" 
          class="admin-tab" 
          :class="{ 'active': tab === 'cms' }" 
          @click="tab = 'cms'"
          :style="tab === 'cms' ? 'color: var(--color-clay); border-bottom: 2.5px solid var(--color-clay); font-weight: 700;' : 'color: var(--text-muted); font-weight: 600; border-bottom: 2.5px solid transparent;'"
          style="padding: 12px 18px; background: none; border-top: none; border-left: none; border-right: none; font-size: 0.95rem; cursor: pointer; transition: all 0.2s; white-space: nowrap;"
        >&#9881; Brand CMS Settings</button>
      </div>

      <!-- Tab 1: Music Catalog & Audio -->
      <div x-show="tab === 'music'" style="background: var(--surface-card); border: 1.5px solid var(--border-default); border-radius: var(--radius-md); padding: 28px; box-shadow: var(--shadow-sm);">
        <div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
          <div>
            <h3 style="font-family: var(--font-display); font-size: 1.35rem; color: var(--text-primary); margin: 0 0 4px;">
              Five Approved Songs &bull; Entitlements &amp; Pricing (Sample Fixture)
            </h3>
            <p style="font-size: 0.88rem; color: var(--text-muted); margin: 0;">
              Sample view illustrating catalog structure. Live pricing and file storage are managed in the authenticated owner backoffice.
            </p>
          </div>
          <a href="{{ url('/admin/songs') }}" class="btn-secondary-sm" style="text-decoration: none;">
            Manage Songs in Live CMS &rarr;
          </a>
        </div>

        <div class="cart-table-wrap" style="box-shadow: none; border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); overflow-x: auto;">
          <table class="cart-table" aria-label="Music pricing and catalog settings" style="background: var(--surface-card);">
            <thead>
              <tr style="background: var(--surface-sunken);">
                <th scope="col">Track #</th>
                <th scope="col">Title</th>
                <th scope="col">Pricing Mode</th>
                <th scope="col">Price</th>
                <th scope="col">Private Storage Location</th>
                <th scope="col">Entitlements (Sample)</th>
                <th scope="col" style="text-align: right;">Live Management</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><strong>01</strong></td>
                <td><strong style="color: var(--text-primary);">Free</strong> (Lead Track)</td>
                <td><span class="badge" style="background: var(--status-success-bg); color: var(--status-success-text); border: 1px solid var(--status-success-border); padding: 4px 8px; border-radius: var(--radius-sm); font-size: 0.74rem; font-weight: 700;">Free Download</span></td>
                <td><code style="background: var(--surface-paper); padding: 2px 6px; border-radius: 4px;">$0.00 USD</code></td>
                <td><code class="code-path" style="background: var(--surface-paper); color: var(--text-primary); border: 1px solid var(--border-subtle);">core/storage/app/private/audio/free-master.mp3</code></td>
                <td>1,420 Active</td>
                <td style="text-align: right;"><a href="{{ url('/admin/songs') }}" style="font-size: 0.8rem; color: var(--color-clay); font-weight: 600; text-decoration: underline;">Configure</a></td>
              </tr>
              <tr>
                <td><strong>02</strong></td>
                <td><strong style="color: var(--text-primary);">Purify Me</strong></td>
                <td>
                  <span class="badge" style="background: var(--status-warning-bg); color: var(--status-warning-text); border: 1px solid var(--status-warning-border); padding: 4px 8px; border-radius: var(--radius-sm); font-size: 0.74rem; font-weight: 700;">Paid Release</span>
                </td>
                <td><code style="background: var(--surface-paper); padding: 2px 6px; border-radius: 4px;">$1.99 USD</code></td>
                <td><code class="code-path" style="background: var(--surface-paper); color: var(--text-primary); border: 1px solid var(--border-subtle);">core/storage/app/private/audio/purify-me-master.wav</code></td>
                <td>480 Active</td>
                <td style="text-align: right;"><a href="{{ url('/admin/songs') }}" style="font-size: 0.8rem; color: var(--color-clay); font-weight: 600; text-decoration: underline;">Configure</a></td>
              </tr>
              <tr>
                <td><strong>03</strong></td>
                <td><strong style="color: var(--text-primary);">I Always Win</strong></td>
                <td><span class="badge" style="background: var(--status-warning-bg); color: var(--status-warning-text); border: 1px solid var(--status-warning-border); padding: 4px 8px; border-radius: var(--radius-sm); font-size: 0.74rem; font-weight: 700;">Paid Release</span></td>
                <td><code style="background: var(--surface-paper); padding: 2px 6px; border-radius: 4px;">$1.99 USD</code></td>
                <td><code class="code-path" style="background: var(--surface-paper); color: var(--text-primary); border: 1px solid var(--border-subtle);">core/storage/app/private/audio/i-always-win-master.wav</code></td>
                <td>310 Active</td>
                <td style="text-align: right;"><a href="{{ url('/admin/songs') }}" style="font-size: 0.8rem; color: var(--color-clay); font-weight: 600; text-decoration: underline;">Configure</a></td>
              </tr>
              <tr>
                <td><strong>04</strong></td>
                <td><strong style="color: var(--text-primary);">Toduyun</strong></td>
                <td><span class="badge" style="background: var(--status-warning-bg); color: var(--status-warning-text); border: 1px solid var(--status-warning-border); padding: 4px 8px; border-radius: var(--radius-sm); font-size: 0.74rem; font-weight: 700;">Paid Release</span></td>
                <td><code style="background: var(--surface-paper); padding: 2px 6px; border-radius: 4px;">$1.99 USD</code></td>
                <td><code class="code-path" style="background: var(--surface-paper); color: var(--text-primary); border: 1px solid var(--border-subtle);">core/storage/app/private/audio/toduyun-master.wav</code></td>
                <td>290 Active</td>
                <td style="text-align: right;"><a href="{{ url('/admin/songs') }}" style="font-size: 0.8rem; color: var(--color-clay); font-weight: 600; text-decoration: underline;">Configure</a></td>
              </tr>
              <tr>
                <td><strong>05</strong></td>
                <td><strong style="color: var(--text-primary);">Follow Me</strong></td>
                <td><span class="badge" style="background: var(--status-warning-bg); color: var(--status-warning-text); border: 1px solid var(--status-warning-border); padding: 4px 8px; border-radius: var(--radius-sm); font-size: 0.74rem; font-weight: 700;">Paid Release</span></td>
                <td><code style="background: var(--surface-paper); padding: 2px 6px; border-radius: 4px;">$1.99 USD</code></td>
                <td><code class="code-path" style="background: var(--surface-paper); color: var(--text-primary); border: 1px solid var(--border-subtle);">core/storage/app/private/audio/follow-me-master.wav</code></td>
                <td>260 Active</td>
                <td style="text-align: right;"><a href="{{ url('/admin/songs') }}" style="font-size: 0.8rem; color: var(--color-clay); font-weight: 600; text-decoration: underline;">Configure</a></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tab 2: Products & Variants -->
      <div x-show="tab === 'products'" style="background: var(--surface-card); border: 1.5px solid var(--border-default); border-radius: var(--radius-md); padding: 28px; box-shadow: var(--shadow-sm);" x-cloak>
        <div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
          <div>
            <h3 style="font-family: var(--font-display); font-size: 1.35rem; color: var(--text-primary); margin: 0 0 4px;">
              Apparel &amp; Merchandise Inventory (Sample Fixture)
            </h3>
            <p style="font-size: 0.88rem; color: var(--text-muted); margin: 0;">
              Stock counts and catalog details are sample placeholders. Live inventory is maintained in the authenticated owner backoffice.
            </p>
          </div>
          <a href="{{ url('/admin/products') }}" class="btn-secondary-sm" style="text-decoration: none;">
            Manage Products in Live CMS &rarr;
          </a>
        </div>

        <div class="cart-table-wrap" style="box-shadow: none; border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); overflow-x: auto;">
          <table class="cart-table" aria-label="Merchandise inventory and variants" style="background: var(--surface-card);">
            <thead>
              <tr style="background: var(--surface-sunken);">
                <th scope="col">SKU</th>
                <th scope="col">Product Title</th>
                <th scope="col">Category</th>
                <th scope="col">Price</th>
                <th scope="col">Stock Status</th>
                <th scope="col">Inventory Count</th>
                <th scope="col" style="text-align: right;">Live Management</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><code>PB-TEE-001</code></td>
                <td><strong style="color: var(--text-primary);">Prince Byron Signature Tee</strong></td>
                <td>Apparel</td>
                <td>$32.00 USD</td>
                <td>
                  <span class="badge" style="background: var(--status-success-bg); color: var(--status-success-text); border: 1px solid var(--status-success-border); padding: 4px 8px; border-radius: var(--radius-sm); font-size: 0.74rem; font-weight: 700;">In Stock</span>
                </td>
                <td>18 units (Sample)</td>
                <td style="text-align: right;">
                  <a href="{{ url('/admin/products') }}" style="font-size: 0.8rem; color: var(--color-clay); font-weight: 600; text-decoration: underline;">Configure</a>
                </td>
              </tr>
              <tr>
                <td><code>PB-HD-002</code></td>
                <td><strong style="color: var(--text-primary);">Abide Arch Pullover Hoodie</strong></td>
                <td>Apparel</td>
                <td>$68.00 USD</td>
                <td><span class="badge" style="background: var(--status-success-bg); color: var(--status-success-text); border: 1px solid var(--status-success-border); padding: 4px 8px; border-radius: var(--radius-sm); font-size: 0.74rem; font-weight: 700;">In Stock</span></td>
                <td>12 units (Sample)</td>
                <td style="text-align: right;"><a href="{{ url('/admin/products') }}" style="font-size: 0.8rem; color: var(--color-clay); font-weight: 600; text-decoration: underline;">Configure</a></td>
              </tr>
              <tr>
                <td><code>PB-CAP-003</code></td>
                <td><strong style="color: var(--text-primary);">PB Monogram Curved Brim Cap</strong></td>
                <td>Accessories</td>
                <td>$28.00 USD</td>
                <td><span class="badge" style="background: var(--status-success-bg); color: var(--status-success-text); border: 1px solid var(--status-success-border); padding: 4px 8px; border-radius: var(--radius-sm); font-size: 0.74rem; font-weight: 700;">In Stock</span></td>
                <td>24 units (Sample)</td>
                <td style="text-align: right;"><a href="{{ url('/admin/products') }}" style="font-size: 0.8rem; color: var(--color-clay); font-weight: 600; text-decoration: underline;">Configure</a></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tab 3: Orders & Bank Payments -->
      <div x-show="tab === 'orders'" style="background: var(--surface-card); border: 1.5px solid var(--border-default); border-radius: var(--radius-md); padding: 28px; box-shadow: var(--shadow-sm);" x-cloak>
        <div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
          <div>
            <h3 style="font-family: var(--font-display); font-size: 1.35rem; color: var(--text-primary); margin: 0 0 4px;">
              Orders &amp; Bank Payment Workflow (Sample Fixture)
            </h3>
            <p style="font-size: 0.88rem; color: var(--text-muted); margin: 0;">
              Sample view illustrating order states. Real bank transfers require owner reconciliation inside the authenticated owner backoffice.
            </p>
          </div>
          <a href="{{ url('/admin/orders') }}" class="btn-secondary-sm" style="text-decoration: none;">
            Manage Orders in Live CMS &rarr;
          </a>
        </div>

        <div class="cart-table-wrap" style="box-shadow: none; border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); overflow-x: auto; margin-bottom: 28px;">
          <table class="cart-table" aria-label="Customer orders and verification states" style="background: var(--surface-card);">
            <thead>
              <tr style="background: var(--surface-sunken);">
                <th scope="col">Order Ref</th>
                <th scope="col">Customer (Sample)</th>
                <th scope="col">Items</th>
                <th scope="col">Amount</th>
                <th scope="col">Gateway</th>
                <th scope="col">Status</th>
                <th scope="col" style="text-align: right;">Live Admin Access</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><strong style="color: var(--color-clay);">PB-202611-001</strong></td>
                <td>Sample Fan (fan@princebyron.local)</td>
                <td>1x Purify Me (Master WAV)</td>
                <td>$1.99 USD</td>
                <td>Stripe Card</td>
                <td><span class="badge" style="background: var(--status-success-bg); color: var(--status-success-text); border: 1px solid var(--status-success-border); padding: 4px 8px; border-radius: var(--radius-sm); font-size: 0.74rem; font-weight: 700;">Paid &bull; Settled</span></td>
                <td style="text-align: right;"><a href="{{ url('/admin/orders') }}" style="font-size: 0.8rem; color: var(--color-clay); font-weight: 600; text-decoration: underline;">View in CMS</a></td>
              </tr>
              <tr style="background: var(--surface-paper);">
                <td><strong style="color: var(--color-clay);">PB-202611-002</strong></td>
                <td>Sample Fellowship (pastor@sample.org)</td>
                <td>1x Arch Hoodie (L) + 1x Cap</td>
                <td>$93.00 USD</td>
                <td><strong>Manual Bank Transfer</strong></td>
                <td>
                  <span class="badge" style="background: var(--status-warning-bg); color: var(--status-warning-text); border: 1px solid var(--status-warning-border); padding: 4px 8px; border-radius: var(--radius-sm); font-size: 0.74rem; font-weight: 700;">
                    Awaiting Bank Verification
                  </span>
                </td>
                <td style="text-align: right;">
                  <a href="{{ url('/admin/orders') }}" class="btn-secondary-sm" style="font-size: 0.78rem; padding: 4px 10px; text-decoration: none;">
                    Reconcile in Live CMS &rarr;
                  </a>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Simulated Audit Trail Preview -->
        <div>
          <h4 style="font-family: var(--font-display); font-size: 1.15rem; color: var(--text-primary); margin: 0 0 12px;">
            Sample Audit Trail Format (Demonstration)
          </h4>
          <div style="background: var(--surface-paper); border: 1.5px solid var(--border-default); border-radius: var(--radius-sm); padding: 18px; font-family: var(--font-mono); font-size: 0.82rem; color: var(--text-primary); line-height: 1.7;">
            <div>[Sample] Order PB-202611-001: Stripe webhook signed. Transaction #ch_sample settled. Entitlement granted.</div>
            <div>[Sample] Order PB-202611-002: Customer selected Manual Bank Transfer. Order state set to awaiting_bank_verification.</div>
            <div style="color: var(--text-muted);">[Sample] Live immutable audit entries are stored in the database and visible in /admin/audit-events.</div>
          </div>
        </div>
      </div>

      <!-- Tab 4: Ministry Bookings -->
      <div x-show="tab === 'bookings'" style="background: var(--surface-card); border: 1.5px solid var(--border-default); border-radius: var(--radius-md); padding: 28px; box-shadow: var(--shadow-sm);" x-cloak>
        <div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
          <div>
            <h3 style="font-family: var(--font-display); font-size: 1.35rem; color: var(--text-primary); margin: 0 0 4px;">
              Ministry Booking RFQ Queue (Sample Fixture)
            </h3>
            <p style="font-size: 0.88rem; color: var(--text-muted); margin: 0;">
              Sample incoming ministry requests. Honoraria and arrangements are reviewed in the authenticated owner backoffice.
            </p>
          </div>
          <a href="{{ url('/admin/booking-requests') }}" class="btn-secondary-sm" style="text-decoration: none;">
            Manage RFQs in Live CMS &rarr;
          </a>
        </div>

        <div class="cart-table-wrap" style="box-shadow: none; border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); overflow-x: auto;">
          <table class="cart-table" aria-label="Ministry appearance queue" style="background: var(--surface-card);">
            <thead>
              <tr style="background: var(--surface-sunken);">
                <th scope="col">RFQ Ref</th>
                <th scope="col">Organization</th>
                <th scope="col">Event Type</th>
                <th scope="col">Requested Date</th>
                <th scope="col">Location</th>
                <th scope="col">Status</th>
                <th scope="col" style="text-align: right;">Live Admin Access</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><code>PB-BK-202611-9402</code></td>
                <td><strong style="color: var(--text-primary);">Sample Church Fellowship</strong></td>
                <td>Sunday Worship Service</td>
                <td>Dec 12, 2026</td>
                <td>Dallas, TX</td>
                <td><span class="badge" style="background: var(--status-warning-bg); color: var(--status-warning-text); border: 1px solid var(--status-warning-border); padding: 4px 8px; border-radius: var(--radius-sm); font-size: 0.74rem; font-weight: 700;">Under Review</span></td>
                <td style="text-align: right;"><a href="{{ url('/admin/booking-requests') }}" class="btn-secondary-sm" style="font-size: 0.78rem; padding: 4px 10px; text-decoration: none;">Review in CMS &rarr;</a></td>
              </tr>
              <tr>
                <td><code>PB-BK-202611-8104</code></td>
                <td><strong style="color: var(--text-primary);">Sample Worship Collective</strong></td>
                <td>Worship Night / Concert</td>
                <td>Jan 24, 2027</td>
                <td>Atlanta, GA</td>
                <td><span class="badge" style="background: var(--status-success-bg); color: var(--status-success-text); border: 1px solid var(--status-success-border); padding: 4px 8px; border-radius: var(--radius-sm); font-size: 0.74rem; font-weight: 700;">Quoted</span></td>
                <td style="text-align: right;"><a href="{{ url('/admin/booking-requests') }}" class="btn-secondary-sm" style="font-size: 0.78rem; padding: 4px 10px; text-decoration: none;">Review in CMS &rarr;</a></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tab 5: Brand CMS Settings -->
      <div x-show="tab === 'cms'" style="background: var(--surface-card); border: 1.5px solid var(--border-default); border-radius: var(--radius-md); padding: 28px; box-shadow: var(--shadow-sm);" x-cloak>
        <div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
          <div>
            <h3 style="font-family: var(--font-display); font-size: 1.35rem; color: var(--text-primary); margin: 0 0 4px;">
              Controlled Page Content &amp; Brand Palette (Sample Mockup)
            </h3>
            <p style="font-size: 0.88rem; color: var(--text-muted); margin: 0;">
              Preview of structured field editor. Live content updates are managed and persisted in the authenticated owner backoffice.
            </p>
          </div>
          <a href="{{ url('/admin/content-sections') }}" class="btn-secondary-sm" style="text-decoration: none;">
            Manage Content in Live CMS &rarr;
          </a>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 32px;">
          <div>
            <h4 style="color: var(--text-primary); margin: 0 0 16px; font-size: 1.05rem; font-weight: 700;">Structured Content Fields (Sample)</h4>
            <div class="form-group">
              <label for="cms-eyebrow">Hero Eyebrow Text:</label>
              <input type="text" id="cms-eyebrow" class="form-control" value="Official Worship &amp; Music Ministry" readonly style="background: var(--surface-sunken);">
            </div>
            <div class="form-group">
              <label for="cms-headline">Main Stage Headline:</label>
              <input type="text" id="cms-headline" class="form-control" value="A sound for every season." readonly style="background: var(--surface-sunken);">
            </div>
            <div class="form-group">
              <label for="cms-quote">Bio Pullquote:</label>
              <textarea id="cms-quote" class="form-control" rows="3" readonly style="background: var(--surface-sunken);">Music is not an escape from reality; it is the sanctuary where we meet God face-to-face and find the grace to carry forward.</textarea>
            </div>
          </div>

          <div>
            <h4 style="color: var(--text-primary); margin: 0 0 16px; font-size: 1.05rem; font-weight: 700;">Approved Non-Arbitrary Palette Swatches</h4>
            <div style="display: grid; gap: 12px;">
              <div style="display: flex; align-items: center; gap: 14px; background: var(--surface-paper); padding: 12px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                <div style="width: 34px; height: 34px; background: var(--color-obsidian); border-radius: var(--radius-sm); border: 1px solid var(--border-strong);"></div>
                <div><strong style="color: var(--text-primary); font-size: 0.9rem;">Obsidian Dark</strong><div style="font-size: 0.78rem; color: var(--text-muted);">Primary Sanctuary Stage</div></div>
              </div>
              <div style="display: flex; align-items: center; gap: 14px; background: var(--surface-paper); padding: 12px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                <div style="width: 34px; height: 34px; background: var(--color-gold); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);"></div>
                <div><strong style="color: var(--text-primary); font-size: 0.9rem;">Luminous Gold</strong><div style="font-size: 0.78rem; color: var(--text-muted);">Primary Brand Accent &amp; Focus</div></div>
              </div>
              <div style="display: flex; align-items: center; gap: 14px; background: var(--surface-paper); padding: 12px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                <div style="width: 34px; height: 34px; background: var(--color-clay); border-radius: var(--radius-sm);"></div>
                <div><strong style="color: var(--text-primary); font-size: 0.9rem;">Terracotta Clay</strong><div style="font-size: 0.78rem; color: var(--text-muted);">Tactile Earth Tone</div></div>
              </div>
              <div style="display: flex; align-items: center; gap: 14px; background: var(--surface-paper); padding: 12px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                <div style="width: 34px; height: 34px; background: var(--surface-paper); border: 1px solid var(--border-strong); border-radius: var(--radius-sm);"></div>
                <div><strong style="color: var(--text-primary); font-size: 0.9rem;">Linen Paper</strong><div style="font-size: 0.78rem; color: var(--text-muted);">Editorial Light Surface</div></div>
              </div>
            </div>
          </div>
        </div>

        <div style="display: flex; justify-content: flex-end;">
          <a href="{{ url('/admin/content-sections') }}" class="btn-primary" style="padding: 10px 20px; text-decoration: none;">
            Manage Content in Live CMS (/admin) &rarr;
          </a>
        </div>
      </div>

    </div>
  </div>
</section>
@endsection
