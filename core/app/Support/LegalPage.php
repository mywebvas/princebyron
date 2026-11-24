<?php

namespace App\Support;

use App\Models\ContentSection;

/**
 * Legal page resolution (Phase 6).
 *
 * WHY A CLASS AND NOT JUST A VIEW
 * -------------------------------
 * Three separate questions have to be answered consistently, and a Blade
 * template cannot answer any of them:
 *
 *   1. Does the owner have real content?  → render it, index it.
 *   2. Is there only a template?          → render the template, mark it clearly,
 *                                           and make it `noindex` so a search
 *                                           engine does not publish an unfinished
 *                                           refund policy as this store's policy.
 *   3. Is the page disabled entirely?     → 404, so the footer link is honest.
 *
 * The dangerous failure here is a template that looks like a real policy. A
 * customer could rely on it. Every placeholder therefore carries a visible
 * banner and a `noindex`, and `PublishingReadiness` reports them as blockers
 * until the owner replaces them.
 */
class LegalPage
{
    /**
     * The pages a store must have before taking payments, with the section_key
     * the owner edits in the admin panel.
     *
     * `required` marks the ones a card processor will ask for during review —
     * those are the ones PublishingReadiness treats as launch blockers.
     */
    public const PAGES = [
        'refund-policy' => [
            'key' => 'legal_refund_policy',
            'title' => 'Refund & Returns Policy',
            'description' => 'How refunds, returns and digital-goods cancellations are handled.',
            'required' => true,
        ],
        'privacy' => [
            'key' => 'legal_privacy',
            'title' => 'Privacy Policy',
            'description' => 'What personal data is collected, why, how long it is kept, and how to have it removed.',
            'required' => true,
        ],
        'terms' => [
            'key' => 'legal_terms',
            'title' => 'Terms of Sale',
            'description' => 'The contract formed when an order is placed, and the terms that apply to it.',
            'required' => true,
        ],
        'shipping' => [
            'key' => 'legal_shipping',
            'title' => 'Shipping & Delivery',
            'description' => 'Processing times, destinations, and how digital downloads are delivered.',
            'required' => true,
        ],
    ];

    public function __construct(
        private readonly string $slug,
        private readonly array $definition,
        private readonly ?ContentSection $section,
    ) {}

    public static function find(string $slug): ?self
    {
        $definition = self::PAGES[$slug] ?? null;

        if ($definition === null) {
            return null;
        }

        $section = ContentSection::where('section_key', $definition['key'])->first();

        // A deliberately disabled section means the owner does not want this page
        // published at all. 404 is the honest answer; a blank template is not.
        if ($section !== null && ! $section->is_visible) {
            return null;
        }

        return new self($slug, $definition, $section);
    }

    public static function forSlug(string $slug): ?self
    {
        return self::find($slug);
    }

    /** @return array<int, string> */
    public static function slugs(): array
    {
        return array_keys(self::PAGES);
    }

    /** @return array<int, string> */
    public static function requiredKeys(): array
    {
        return array_values(array_map(
            fn (array $p) => $p['key'],
            array_filter(self::PAGES, fn (array $p) => $p['required'])
        ));
    }

    public function title(): string
    {
        return $this->section?->title ?: $this->definition['title'];
    }

    public function description(): string
    {
        return $this->definition['description'];
    }

    /**
     * The owner's own text, or null when nothing has been written.
     *
     * `body` is stored as plain text by the admin form, so it is escaped on
     * output. That is deliberate: a legal page is the last place that should
     * accept pasted HTML.
     */
    public function body(): ?string
    {
        $body = trim((string) ($this->section?->body ?? ''));

        return $body === '' ? null : $body;
    }

    /** True when this page is still showing template text. */
    public function isPlaceholder(): bool
    {
        return $this->body() === null;
    }

    public function isLegallyReviewed(): bool
    {
        return (bool) ($this->section?->metadata['legally_reviewed'] ?? false);
    }

    public function reviewedOn(): ?string
    {
        return $this->section?->metadata['reviewed_on'] ?? null;
    }

    /**
     * Template text shown until the owner writes their own.
     *
     * Written to be useful as a starting point and impossible to mistake for
     * finished advice: it states what the page must cover and leaves the
     * decisions to the owner and their advisor.
     */
    public function template(): string
    {
        return match ($this->slug) {
            'refund-policy' => <<<'TXT'
                This page must state your refund and returns policy before you accept payments.

                It needs to answer, at minimum:

                • Digital downloads — are they refundable, and if so under what conditions? Many stores state that digital goods are non-refundable once the file has been downloaded, because the product cannot be returned. Check whether that is lawful where you trade; in the EU and UK, consumers generally lose the right to cancel digital content once download has begun only if they gave express consent and acknowledged the loss.
                • Physical items — the return window, who pays return postage, the condition required, and how long a refund takes to appear.
                • Faulty or incorrect items — your obligations, which in most jurisdictions you cannot limit.
                • How to start a refund — the exact address or form a customer uses.
                • How refunds are paid — the original payment method, and any timing commitment you can actually meet.

                Replace this text with your policy. Have it reviewed by someone qualified in the jurisdiction you sell into before publishing.
                TXT,
            'privacy' => <<<'TXT'
                This page must state what personal data you collect and what you do with it.

                It needs to answer, at minimum:

                • What is collected — name, email, postal address, order history, and for downloads the files accessed and when. Also state whether payment card details are stored (they are not: payment is handled by your processor).
                • Why — to fulfil orders, to provide download access, to answer enquiries, to meet tax and accounting obligations.
                • Who it is shared with — your payment processor, your email provider, your host.
                • How long it is kept — orders are financial records and must be retained for the period your tax authority requires.
                • The customer's rights — access, correction, deletion, and portability where they apply, plus how to exercise them. This site supports anonymisation of a customer account on request; deletion of an order is refused because it is a financial record.
                • Cookies — what is set and why. The storefront session cookie is strictly necessary.

                Replace this text with your policy. Have it reviewed by someone qualified in the jurisdiction you sell into before publishing.
                TXT,
            'terms' => <<<'TXT'
                This page must state the terms on which you sell.

                It needs to answer, at minimum:

                • Who you are — the legal entity selling, and a contact address.
                • What is being sold — physical goods and digital downloads, and that prices are in US dollars unless stated otherwise.
                • How a contract is formed — when the order is accepted, and what happens if an item is unavailable.
                • Payment — what methods are accepted and when payment is taken.
                • Delivery — a cross-reference to the Shipping & Delivery page.
                • Liability — what you are and are not responsible for, within the limits your jurisdiction allows.
                • Governing law — which jurisdiction's law applies to the contract.
                • Changes — how and when these terms may change.

                Replace this text with your policy. Have it reviewed by someone qualified in the jurisdiction you sell into before publishing.
                TXT,
            'shipping' => <<<'TXT'
                This page must state how orders reach customers.

                It needs to answer, at minimum:

                • Physical goods — processing time before dispatch (distinct from delivery time), the destinations you ship to, carrier options, whether tracking is included, and what happens if a parcel is lost.
                • Shipping cost — how it is calculated. This store charges shipping configured in the admin panel; state whether that covers all destinations or only some.
                • Digital downloads — that access is immediate after payment, that files are available from the customer's account, and how long access lasts. Download entitlements can expire; state the period you actually configure.
                • Order tracking — where a customer finds status.

                Replace this text with your policy. Have it reviewed by someone qualified in the jurisdiction you sell into before publishing.
                TXT,
            default => 'This page has no template yet.',
        };
    }
}
