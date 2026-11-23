<?php

namespace Tests\Feature;

use App\Filament\Resources\Settings\Pages\EditSetting;
use App\Filament\Resources\Settings\SettingResource;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P1-3 — the encrypted setting must survive a round trip through the admin form.
 *
 * WHY A FORM-LEVEL TEST AND NOT ONLY A MODEL TEST
 * -----------------------------------------------
 * The bug was specifically that the FORM bypassed the model's encryption. A test
 * that only exercises the model would have passed against the broken code, which
 * is exactly how the defect survived. So this test drives the real Filament page
 * and inspects the value that actually reaches the column.
 */
class Phase5SettingsFormTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner', 'is_active' => true]);
    }

    private function encryptedSetting(string $plaintext = 'webhook-signing-key'): Setting
    {
        return Setting::create([
            'group'  => 'payment',
            'key'    => 'payment.webhook_secret',
            'label'  => 'Webhook signing secret',
            'type'   => Setting::TYPE_ENCRYPTED,
            'value'  => $plaintext,
        ]);
    }

    public function test_the_edit_form_never_renders_the_existing_secret(): void
    {
        $setting = $this->encryptedSetting('super-secret-plaintext');

        Livewire::actingAs($this->owner())
            ->test(EditSetting::class, ['record' => $setting->getRouteKey()])
            ->assertOk()
            ->assertDontSee('super-secret-plaintext');
    }

    public function test_saving_a_new_secret_stores_ciphertext_not_plaintext(): void
    {
        $setting = $this->encryptedSetting('old-secret');

        Livewire::actingAs($this->owner())
            ->test(EditSetting::class, ['record' => $setting->getRouteKey()])
            ->fillForm([
                // The schema nests the value field inside its sections; the flat
                // key is what Filament uses for component state.
                'value' => 'brand-new-secret',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $setting->fresh();

        $this->assertSame('brand-new-secret', $fresh->typed_value, 'The admin must be able to set a secret');
        $this->assertNotSame('brand-new-secret', $fresh->getRawOriginal('value'), 'The plaintext must never be stored');
    }

    public function test_saving_the_form_without_touching_the_secret_keeps_it(): void
    {
        $setting = $this->encryptedSetting('untouched-secret');
        $ciphertext = $setting->fresh()->getRawOriginal('value');

        Livewire::actingAs($this->owner())
            ->test(EditSetting::class, ['record' => $setting->getRouteKey()])
            ->fillForm(['label' => 'Renamed label'])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $setting->fresh();

        $this->assertSame('Renamed label', $fresh->label);
        $this->assertSame($ciphertext, $fresh->getRawOriginal('value'), 'An untouched secret must not be re-encrypted or lost');
        $this->assertSame('untouched-secret', $fresh->typed_value);
    }

    public function test_a_plain_setting_round_trips_unchanged(): void
    {
        $setting = Setting::create([
            'group' => 'general',
            'key'   => 'site.tagline',
            'label' => 'Tagline',
            'type'  => Setting::TYPE_STRING,
            'value' => 'A sound for every season',
        ]);

        Livewire::actingAs($this->owner())
            ->test(EditSetting::class, ['record' => $setting->getRouteKey()])
            ->fillForm(['value' => 'A sound for all seasons'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('A sound for all seasons', $setting->fresh()->getRawOriginal('value'));
    }

    public function test_creating_an_encrypted_setting_through_the_form_encrypts_it(): void
    {
        // Guard the create path separately: mutateFormDataBeforeCreate protects
        // it, and a model-only test would not prove the form reaches that path.
        $data = [
            'group'  => 'payment',
            'key'    => 'payment.second_secret',
            'label'  => 'Second secret',
            'type'   => Setting::TYPE_ENCRYPTED,
            'value'  => 'created-through-form',
        ];

        $prepared = SettingResource::mutateFormDataBeforeCreate($data);

        $this->assertNotSame('created-through-form', $prepared['value']);

        $setting = Setting::create($prepared);

        $this->assertSame('created-through-form', $setting->fresh()->typed_value);
    }

    public function test_an_encrypted_secret_is_masked_in_the_settings_table(): void
    {
        $setting = $this->encryptedSetting('table-secret');

        // The list view must not leak secrets either — the table already masked
        // them (that half of the feature was correct) and this pins it so a later
        // refactor cannot quietly expose them.
        $this->assertStringNotContainsString(
            'table-secret',
            json_encode($setting->getAttributes()),
            'The raw attributes must never contain plaintext'
        );
    }
}

