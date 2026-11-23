@extends('layouts.app')

@section('content')
<!-- Booking Hero Stage -->
<section class="hero-stage">
  <div class="hero-inner">
    <span class="hero-eyebrow">Congregational Ministry &amp; Event Invitations</span>
    <h1 class="hero-title">
      Make room<br>
      <em>for worship.</em>
    </h1>
    <p class="hero-lead">
      We consider every invitation a sacred trust. Whether you are preparing a Sunday revival, worship conference, or community evening of praise, share your vision below so our team can prayerfully align on logistics and tailored ministry requirements.
    </p>
  </div>
</section>

<!-- Booking Enquiry Process & Form Section -->
<section 
  id="booking-section"
  class="section-editorial" 
  style="background: var(--color-paper);"
  x-data="{
    step: 1,
    submitting: false,
    errorMessage: '',
    bookingSubmitted: false,
    bookingRef: '',

    form: {
      event_type: 'Worship service',
      event_name: '',
      event_date: '',
      expected_audience: 250,
      event_city: '',
      event_description: '',
      requirements: 'Full Live Band Setup (Drums, Bass, Keys, Guitars, In-Ears)',
      contact_name: '',
      contact_email: '',
      contact_phone: '',
      organisation: ''
    },

    nextStep() {
      this.errorMessage = '';
      if (this.step === 1) {
        if (!this.form.event_name.trim()) {
          this.errorMessage = 'Please provide a gathering or event title.';
          return;
        }
        this.step = 2;
        window.scrollTo({ top: 350, behavior: 'smooth' });
      } else if (this.step === 2) {
        if (!this.form.event_description.trim()) {
          this.errorMessage = 'Please share a brief note about the ministry vision or programme details.';
          return;
        }
        this.step = 3;
        window.scrollTo({ top: 350, behavior: 'smooth' });
      }
    },

    prevStep() {
      this.errorMessage = '';
      if (this.step > 1) {
        this.step--;
        window.scrollTo({ top: 350, behavior: 'smooth' });
      }
    },

    async submitBooking() {
      this.errorMessage = '';
      if (!this.form.contact_name.trim() || !this.form.contact_email.trim()) {
        this.errorMessage = 'Please provide your full name and a valid email address.';
        return;
      }

      this.submitting = true;
      try {
        const token = document.querySelector('meta[name=\'csrf-token\']')?.getAttribute('content');
        const res = await fetch('{{ route('booking.submit') }}', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': token || ''
          },
          body: JSON.stringify(this.form)
        });

        const data = await res.json();

        if (res.ok && data.success) {
          this.bookingRef = data.reference;
          this.bookingSubmitted = true;
          $store.app.showToast('Ministry Inquiry ' + data.reference + ' received.');
          window.scrollTo({ top: 200, behavior: 'smooth' });
        } else {
          if (data.errors) {
            const firstErr = Object.values(data.errors)[0];
            this.errorMessage = Array.isArray(firstErr) ? firstErr[0] : 'Please verify all required details.';
          } else {
            this.errorMessage = data.message || 'Submission could not be completed. Please try again.';
          }
        }
      } catch (err) {
        this.errorMessage = 'Network connection error. Please try again shortly.';
      } finally {
        this.submitting = false;
      }
    }
  }"
>
  <div class="section-container" style="max-width: 860px;">
    
    <!-- Submission Success Confirmation Card -->
    <div x-show="bookingSubmitted" style="background: var(--surface-card); border: 1px solid var(--border-light); border-radius: var(--radius-md); padding: 56px 36px; text-align: center; box-shadow: var(--shadow-sm);" x-cloak>
      <div style="width: 64px; height: 64px; background: var(--status-success-bg); color: var(--status-success-text); border: 1px solid var(--status-success-border); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 20px;">
        &#10003;
      </div>
      <span class="section-eyebrow" style="color: var(--status-success-text);">Inquiry Received</span>
      <h2 style="font-family: var(--font-display); font-size: 2rem; color: var(--text-primary); margin-bottom: 12px;">Booking Inquiry Received</h2>
      <p style="color: var(--text-muted); max-width: 560px; margin: 0 auto 24px;">
        Thank you, <strong x-text="form.contact_name"></strong>. Your invitation for <strong x-text="form.organisation || form.event_name"></strong> has been safely recorded under reference <strong style="color: var(--color-clay);" x-text="bookingRef"></strong>. Prince Byron's ministry management will review calendar availability and respond promptly via email.
      </p>

      <div style="background: var(--color-cream); border: 1px solid var(--border-light); border-radius: var(--radius-sm); padding: 20px; max-width: 500px; margin: 0 auto 32px; text-align: left; font-size: 0.9rem; color: var(--text-muted);">
        <div style="margin-bottom: 8px;"><strong>Event Type:</strong> <span x-text="form.event_type"></span></div>
        <div style="margin-bottom: 8px;"><strong>Gathering Title:</strong> <span x-text="form.event_name"></span></div>
        <div style="margin-bottom: 8px;" x-show="form.event_city"><strong>Location:</strong> <span x-text="form.event_city"></span></div>
        <div x-show="form.event_date"><strong>Preferred Date:</strong> <span x-text="form.event_date"></span></div>
      </div>

      <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
        <a href="{{ url('/') }}" class="btn-primary">&larr; Return to Home</a>
        <a href="{{ url('/music') }}" class="btn-secondary">Explore Music Catalog</a>
      </div>
    </div>

    <!-- Active Multi-Step Booking Card -->
    <div x-show="!bookingSubmitted" class="account-card" style="padding: 40px;">
      
      <!-- Stepper Header -->
      <div style="border-bottom: 1px solid var(--border-light); padding-bottom: 24px; margin-bottom: 32px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 16px;">
        <div>
          <span class="section-eyebrow">Ministry Invitation &amp; Quotation Request</span>
          <h2 style="font-family: var(--font-display); font-size: 1.8rem; color: var(--color-obsidian); margin: 4px 0 0;">
            Tell us about your gathering.
          </h2>
        </div>
        <div style="font-family: var(--font-sans); font-size: 0.85rem; font-weight: 700; color: var(--color-clay); letter-spacing: 0.05em;">
          STEP <span x-text="step"></span> OF 3
        </div>
      </div>

      <!-- Step Indicators -->
      <div class="booking-stepper" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 36px; padding: 0 10px;">
        <div class="booking-step-item" :class="{ 'active': step === 1 }" style="display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 0.88rem; color: step === 1 ? var(--color-clay) : var(--text-muted);">
          <span style="width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; font-size: 0.8rem; font-weight: 700;" :style="step === 1 ? 'background: var(--color-clay); color: var(--color-white);' : (step > 1 ? 'background: var(--color-gold); color: var(--color-obsidian);' : 'background: var(--border-light); color: var(--text-muted);')">
            1
          </span>
          <span>Event Context</span>
        </div>
        <span style="color: var(--border-medium);">&rarr;</span>
        <div class="booking-step-item" :class="{ 'active': step === 2 }" style="display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 0.88rem; color: step === 2 ? var(--color-clay) : var(--text-muted);">
          <span style="width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; font-size: 0.8rem; font-weight: 700;" :style="step === 2 ? 'background: var(--color-clay); color: var(--color-white);' : (step > 2 ? 'background: var(--color-gold); color: var(--color-obsidian);' : 'background: var(--border-light); color: var(--text-muted);')">
            2
          </span>
          <span>Details &amp; Vision</span>
        </div>
        <span style="color: var(--border-medium);">&rarr;</span>
        <div class="booking-step-item" :class="{ 'active': step === 3 }" style="display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 0.88rem; color: step === 3 ? var(--color-clay) : var(--text-muted);">
          <span style="width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; font-size: 0.8rem; font-weight: 700;" :style="step === 3 ? 'background: var(--color-clay); color: var(--color-white);' : 'background: var(--border-light); color: var(--text-muted);'">
            3
          </span>
          <span>Your Contact</span>
        </div>
      </div>

      <!-- Error Announcement Alert -->
      <div x-show="errorMessage" style="background: var(--status-error-bg); border: 1px solid var(--status-error-border); color: var(--status-error-text); padding: 12px 18px; border-radius: var(--radius-sm); margin-bottom: 24px; font-size: 0.88rem;" x-cloak>
        <span x-text="errorMessage"></span>
      </div>

      <form @submit.prevent="if (step < 3) nextStep(); else submitBooking();">
        
        <!-- Step 1: Gathering Context -->
        <div x-show="step === 1">
          <h3 style="font-family: var(--font-display); font-size: 1.3rem; color: var(--color-obsidian); margin-bottom: 18px;">
            Step 1: Gathering Context
          </h3>

          <div class="form-group" style="margin-bottom: 20px;">
            <label for="rfq-event-type">Type of Ministry Gathering:</label>
            <select id="rfq-event-type" class="form-control" x-model="form.event_type">
              <option value="Worship service">Sunday Worship Service</option>
              <option value="Conference or concert">Praise Concert / Ministry Conference</option>
              <option value="Community gathering">Community Gathering / Revival Night</option>
              <option value="Youth & young adult revival">Youth &amp; Young Adult Gathering</option>
              <option value="Other ministry invitation">Other Ministry Invitation</option>
            </select>
          </div>

          <div class="form-group" style="margin-bottom: 20px;">
            <label for="rfq-event-name">Event or Gathering Title: <span style="color: var(--color-clay);">*</span></label>
            <input 
              type="text" 
              id="rfq-event-name" 
              class="form-control" 
              x-model="form.event_name" 
              placeholder="e.g. Annual Faith Revival or Sunday Morning Worship" 
              required
            >
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="rfq-date">Anticipated Date:</label>
              <input type="date" id="rfq-date" class="form-control" x-model="form.event_date">
            </div>
            <div class="form-group">
              <label for="rfq-attendance">Estimated Attendance:</label>
              <select id="rfq-attendance" class="form-control" x-model.number="form.expected_audience">
                <option :value="100">Under 150 Attendees</option>
                <option :value="300">150 &ndash; 500 Attendees</option>
                <option :value="800">500 &ndash; 1,000 Attendees</option>
                <option :value="2000">1,000+ Attendees</option>
              </select>
            </div>
          </div>

          <div style="margin-top: 36px; display: flex; justify-content: flex-end;">
            <button type="button" class="btn-primary" @click="nextStep()">
              Continue to Details &rarr;
            </button>
          </div>
        </div>

        <!-- Step 2: Details & Vision -->
        <div x-show="step === 2" x-cloak>
          <h3 style="font-family: var(--font-display); font-size: 1.3rem; color: var(--color-obsidian); margin-bottom: 18px;">
            Step 2: Location &amp; Ministry Vision
          </h3>

          <div class="form-group" style="margin-bottom: 20px;">
            <label for="rfq-city">City, State / Region:</label>
            <input 
              type="text" 
              id="rfq-city" 
              class="form-control" 
              x-model="form.event_city" 
              placeholder="e.g. Atlanta, GA or Dallas, TX"
            >
          </div>

          <div class="form-group" style="margin-bottom: 20px;">
            <label for="rfq-desc">Ministry Vision &amp; Gathering Details: <span style="color: var(--color-clay);">*</span></label>
            <textarea 
              id="rfq-desc" 
              class="form-control" 
              rows="4" 
              x-model="form.event_description" 
              placeholder="Share what you hope to experience, the flow of the gathering, or any specific themes."
              required
            ></textarea>
          </div>

          <div class="form-group" style="margin-bottom: 28px;">
            <label for="rfq-req">Audio Setup &amp; Technical Rider:</label>
            <input 
              type="text" 
              id="rfq-req" 
              class="form-control" 
              x-model="form.requirements" 
              placeholder="e.g. Live band setup, acoustic set, or host musicians"
            >
          </div>

          <div style="display: flex; justify-content: space-between; align-items: center;">
            <button type="button" class="btn-secondary" @click="prevStep()">&larr; Back</button>
            <button type="button" class="btn-primary" @click="nextStep()">Continue to Contact &rarr;</button>
          </div>
        </div>

        <!-- Step 3: Contact & Submission -->
        <div x-show="step === 3" x-cloak>
          <h3 style="font-family: var(--font-display); font-size: 1.3rem; color: var(--color-obsidian); margin-bottom: 18px;">
            Step 3: Church &amp; Contact Details
          </h3>

          <div class="form-group" style="margin-bottom: 20px;">
            <label for="rfq-org">Church or Organization Name:</label>
            <input 
              type="text" 
              id="rfq-org" 
              class="form-control" 
              x-model="form.organisation" 
              placeholder="e.g. Grace Fellowship Church"
            >
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="rfq-name">Your Full Name: <span style="color: var(--color-clay);">*</span></label>
              <input 
                type="text" 
                id="rfq-name" 
                class="form-control" 
                x-model="form.contact_name" 
                placeholder="Full Name" 
                required
              >
            </div>
            <div class="form-group">
              <label for="rfq-email">Email Address: <span style="color: var(--color-clay);">*</span></label>
              <input 
                type="email" 
                id="rfq-email" 
                class="form-control" 
                x-model="form.contact_email" 
                placeholder="you@example.com" 
                required
              >
            </div>
          </div>

          <div class="form-group" style="margin-bottom: 24px;">
            <label for="rfq-phone">Contact Telephone (Optional):</label>
            <input 
              type="tel" 
              id="rfq-phone" 
              class="form-control" 
              x-model="form.contact_phone" 
              placeholder="+1 (555) 000-0000"
            >
          </div>

          <div style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 28px; line-height: 1.5;">
            &bull; No public booking fees are published. Quotations and logistics are prepared individually following review.
          </div>

          <div style="display: flex; justify-content: space-between; align-items: center;">
            <button type="button" class="btn-secondary" @click="prevStep()" :disabled="submitting">&larr; Back</button>
            <button 
              type="submit" 
              class="btn-primary" 
              style="padding: 16px 36px;"
              :disabled="submitting"
            >
              <span x-show="!submitting">Submit Ministry Request &rarr;</span>
              <span x-show="submitting" x-cloak>Submitting...</span>
            </button>
          </div>
        </div>

      </form>
    </div>

  </div>
</section>
@endsection
