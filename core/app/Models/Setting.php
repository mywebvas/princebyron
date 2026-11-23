<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class Setting extends Model
{
    use Auditable;

    const TYPE_STRING    = 'string';
    const TYPE_BOOLEAN   = 'boolean';
    const TYPE_INTEGER   = 'integer';
    const TYPE_JSON      = 'json';
    const TYPE_ENCRYPTED = 'encrypted';

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'label',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    /**
     |--------------------------------------------------------------------------
     | Typed Value Accessor / Mutator
     |--------------------------------------------------------------------------
     */

    public function getTypedValueAttribute(): mixed
    {
        if ($this->value === null) {
            return null;
        }

        return match ($this->type) {
            self::TYPE_BOOLEAN   => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            self::TYPE_INTEGER   => (int) $this->value,
            self::TYPE_JSON      => json_decode($this->value, true),
            self::TYPE_ENCRYPTED => $this->decryptValue($this->value),
            default              => $this->value,
        };
    }

    public function setTypedValue(mixed $val): void
    {
        if ($val === null) {
            $this->value = null;
            return;
        }

        $this->value = match ($this->type) {
            self::TYPE_BOOLEAN   => $val ? '1' : '0',
            self::TYPE_INTEGER   => (string) ((int) $val),
            self::TYPE_JSON      => is_string($val) ? $val : json_encode($val),
            self::TYPE_ENCRYPTED => Crypt::encryptString((string) $val),
            default              => (string) $val,
        };
    }

    /**
     * Defence in depth for P1-3.
     *
     * The admin form now encrypts, but the form is not the only writer: seeders,
     * console commands and future code all reach this model. Rather than trust
     * every caller, encrypt on save whenever the type is `encrypted` and the
     * value is not already a valid payload. Config values like APP_KEY are safe
     * to pass through so the platform's own bootstrap settings are not mangled.
     */
    protected static function booted(): void
    {
        static::saving(function (Setting $setting) {
            if ($setting->type !== self::TYPE_ENCRYPTED || $setting->value === null) {
                return;
            }

            $value = (string) $setting->value;

            // Already encrypted by this application? Leave it alone.
            try {
                \Illuminate\Support\Facades\Crypt::decryptString($value);
                return;
            } catch (\Throwable) {
                // Not a decryptable payload — fall through and encrypt it.
            }

            $setting->value = \Illuminate\Support\Facades\Crypt::encryptString($value);
        });
    }

    protected function decryptValue(string $encrypted): ?string
    {
        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable $e) {
            Log::warning("Failed to decrypt setting value for key '{$this->key}'");
            return null;
        }
    }

    /**
     |--------------------------------------------------------------------------
     | Static Helpers
     |--------------------------------------------------------------------------
     */

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        if (! $setting) {
            return $default;
        }

        return $setting->typed_value ?? $default;
    }

    public static function set(
        string $key,
        mixed $value,
        string $group = 'general',
        string $type = self::TYPE_STRING,
        ?string $label = null,
        bool $isPublic = false
    ): self {
        $setting = static::firstOrNew(['key' => $key]);
        $setting->group = $group;
        $setting->type = $type;
        if ($label !== null) {
            $setting->label = $label;
        }
        $setting->is_public = $isPublic;
        $setting->setTypedValue($value);
        $setting->save();

        return $setting;
    }
}
