@extends('layouts.app')

@section('content')
<section class="hero-stage">
  <div class="hero-inner">
    <span class="hero-eyebrow">Policies &amp; Terms</span>
    <h1 class="hero-title">
      {{ $page->title() }}
    </h1>
    <p class="hero-lead">
      {{ $page->description() }}
    </p>
  </div>
</section>

<section class="section-editorial">
  <div class="section-container legal-doc">

    @if ($page->isPlaceholder())
      {{--
        A template must never read like a finished policy. A customer could rely
        on it, and a card processor could approve the store on its strength. The
        banner is deliberately not subtle, and the page is served `noindex` so a
        search engine does not publish unfinished terms as this store's policy.
      --}}
      <div class="legal-notice" role="note">
        <strong>This page is a template, not a policy.</strong>
        It lists what the page must cover. Replace it with your own wording, reviewed by
        someone qualified in the jurisdiction you sell into, before you take payments.
      </div>
    @elseif (! $page->isLegallyReviewed())
      <div class="legal-notice legal-notice--soft" role="note">
        <strong>Awaiting legal review.</strong>
        This policy was written by the store owner but has not yet been marked as
        reviewed by a qualified advisor.
      </div>
    @endif

    <div class="legal-body">
      @if ($page->isPlaceholder())
        @foreach (preg_split('/\n\s*\n/', trim($page->template())) as $paragraph)
          <p>{{ $paragraph }}</p>
        @endforeach
      @else
        {{-- Owner text is escaped, never rendered as HTML: a legal page is the
             last place that should accept pasted markup. --}}
        @foreach (preg_split('/\n\s*\n/', $page->body()) as $paragraph)
          <p>{!! nl2br(e($paragraph)) !!}</p>
        @endforeach
      @endif
    </div>

    @if (! $page->isPlaceholder() && $page->reviewedOn())
      <p class="legal-reviewed">Last reviewed: {{ $page->reviewedOn() }}</p>
    @endif

    <p class="legal-contact">
      Questions about this policy?
      <a href="{{ url('/booking') }}">Contact the ministry</a>.
    </p>

    <nav class="legal-others" aria-label="Other policies">
      @foreach (\App\Support\LegalPage::slugs() as $otherSlug)
        @continue($otherSlug === $slug)
        @php($other = \App\Support\LegalPage::find($otherSlug))
        @if ($other)
          <a href="{{ url('/'.$otherSlug) }}">{{ $other->title() }}</a>
        @endif
      @endforeach
    </nav>

  </div>
</section>
@endsection
