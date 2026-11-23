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
        foreach ($this->auditSensitiveAttributes() as $key) {
            if (array_key_exists($key, $values) && $values[$key] !== null && $values[$key] !== '') {
                $values[$key] = '[redacted]';
            }
        }

        return $values;
    }

    /**
     * Attributes whose value must never be written to the log.
     *
     * Redaction is per-attribute rather than whole-row on purpose: an auditor
     * needs to know that `payment.webhook_secret` was changed and by whom; they
     * do not need its value. Hiding the entire row would make the trail useless
     * exactly where it matters most.
     *
     * Setting rows of type `encrypted` hold ciphertext in `value`; both the
     * ciphertext and its plaintext are secrets. NB: `property_exists($this,
     * 'type')` is FALSE for an Eloquent attribute — `type` is not a declared
     * property, it lives in $attributes behind __get(). An earlier version used
     * property_exists and therefore redacted nothing, writing the ciphertext to
     * the audit log; read it through the model instead.
     */
    protected function auditSensitiveAttributes(): array
    {
        return $this->getAttribute('type') === \App\Models\Setting::TYPE_ENCRYPTED
            ? ['value']
            : [];
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
