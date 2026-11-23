<?php

namespace App\Models\Concerns;

use App\Models\AuditEvent;
use Illuminate\Support\Facades\Auth;

/**
 * Auditable — records consequential edits to an immutable append-only log.
 *
 * WHY THIS EXISTS (review P1-6)
 * -----------------------------
 * Only order state changes were audited (10 call sites). The PRD requires an
 * audit trail for consequential admin edits: price, stock, visibility, content
 * and settings. Without it, an owner could change a price, unpublish a product
 * or rewrite a policy page and there would be no record of who did it or what
 * it was before — which matters most exactly when a customer disputes a charge.
 *
 * DESIGN
 * ------
 * A trait, not nine per-resource hooks. Any model that should be audited adds
 * `use Auditable;` and no resource code has to remember to log anything. That
 * property is the whole point: the previous approach depended on each new
 * resource author remembering, which is why coverage was partial.
 *
 * Secrets are never written to the log: encrypted setting values are recorded as
 * a masked marker, and a null value is recorded as null rather than "null".
 */
trait Auditable
{
    /** Attributes excluded from the audit payload, per model. */
    protected function auditExcluded(): array
    {
        return ['updated_at', 'created_at', 'remember_token'];
    }

    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->recordAudit('created', [], $model->auditSnapshot());
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            // Nothing consequential changed (e.g. a touch()) — do not write noise.
            if (empty($changes)) {
                return;
            }

            $old = [];
            $new = [];

            foreach (array_keys($changes) as $key) {
                $old[$key] = $model->getOriginal($key);
                $new[$key] = $model->getAttribute($key);
            }

            $model->recordAudit('updated', $model->auditRedact($old), $model->auditRedact($new));
        });

        static::deleted(function ($model) {
            $model->recordAudit('deleted', $model->auditSnapshot(), []);
        });
    }

    /** Full attribute snapshot, redacted. */
    protected function auditSnapshot(): array
    {
        $attributes = $this->getAttributes();

        foreach ($this->auditExcluded() as $key) {
            unset($attributes[$key]);
        }

        return $this->auditRedact($attributes);
    }

    /** Mask sensitive values so the audit log never becomes a secret store. */
    protected function auditRedact(array $values): array
    {
        if ($this->shouldHideValue()) {
            foreach (array_keys($values) as $key) {
                if ($values[$key] !== null && $values[$key] !== '') {
                    $values[$key] = '[encrypted]';
                }
            }
        }

        return $values;
    }

    /**
     * Setting rows of type `encrypted` hold ciphertext in `value`; both the
     * ciphertext and its plaintext are secrets.
     */
    protected function shouldHideValue(): bool
    {
        return property_exists($this, 'type')
            && isset($this->type)
            && $this->type === \App\Models\Setting::TYPE_ENCRYPTED;
    }

    /**
     * Write the audit row.
     *
     * Wrapped so an audit failure never breaks the business operation: losing an
     * audit entry is bad, but rolling back a paid order because the log write
     * failed is worse. The exception is reported instead.
     */
    protected function recordAudit(string $action, array $old, array $new): void
    {
        try {
            AuditEvent::create([
                'actor_id'       => Auth::id(),
                'action'         => class_basename($this).'.'.$action,
                'auditable_type' => static::class,
                'auditable_id'   => $this->getKey(),
                'old_values'     => $old ?: null,
                'new_values'     => $new ?: null,
                'ip_address'     => request()->ip(),
                'user_agent'     => request()->userAgent(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
