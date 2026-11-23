<?php

namespace App\Filament\Resources\Settings\Pages;

use App\Filament\Resources\Settings\SettingResource;
use App\Models\Setting;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Crypt;

class CreateSetting extends CreateRecord
{
    protected static string $resource = SettingResource::class;

    /**
     * Encrypt a new secret before the record is created.
     *
     * Note where this lives: `mutateFormDataBeforeCreate()` is a **protected
     * instance method on the page**, not a static method on the resource. An
     * earlier version of this change put it on SettingResource, where nothing
     * would ever have called it — dead code that looked like a safeguard, with a
     * test that happily asserted on it. That is worse than having no guard,
     * because it converts "untested" into "falsely tested".
     *
     * `Setting::saving()` also enforces this at the model layer, which is the
     * real guarantee (seeders and console commands never touch this page). This
     * method exists so the form path is explicit and independently testable.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['type'] ?? null) === Setting::TYPE_ENCRYPTED && filled($data['value'] ?? null)) {
            $data['value'] = Crypt::encryptString((string) $data['value']);
        }

        return $data;
    }
}
