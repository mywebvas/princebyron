<?php

namespace Tests\Feature;

use App\Filament\Widgets\RevenueChartWidget;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase2AdminFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.test',
            'password' => 'StrongOwnerPass123!',
            'role' => User::ROLE_OWNER,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    public function test_revenue_chart_supports_each_period_group_on_the_active_database(): void
    {
        $widget = new RevenueChartWidget();

        foreach ([
            'day' => 30,
            'week' => 12,
            'month' => 12,
        ] as $group => $periods) {
            $widget->group = $group;
            $data = $widget->chartData();
            $svg = $widget->renderSvg($data);

            $this->assertSame($group, $data['group']);
            $this->assertCount($periods, $data['labels']);
            $this->assertCount($periods, $data['values']);
            $this->assertStringContainsString('<svg', $svg);
            $this->assertStringContainsString('Revenue trend', $svg);
        }
    }

    public function test_owner_can_open_phase_two_admin_pages_and_download_csv(): void
    {
        $this->actingAs($this->owner)
            ->get('/admin/exports')
            ->assertOk();

        $this->actingAs($this->owner)
            ->get('/admin/staff-invitations')
            ->assertOk();

        $download = $this->actingAs($this->owner)->get('/admin/exports/users');
        $download->assertOk();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $download->streamedContent());
        $this->assertStringContainsString('Email', $download->streamedContent());
    }

    public function test_customer_cannot_use_owner_invitation_or_export_routes(): void
    {
        $customer = User::create([
            'name' => 'Customer',
            'email' => 'customer@example.test',
            'password' => 'StrongCustomerPass123!',
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($customer)->get('/admin/staff/invitations')->assertForbidden();
        $this->actingAs($customer)->get('/admin/exports/users')->assertForbidden();
    }

    public function test_invitation_acceptance_creates_the_invited_staff_account(): void
    {
        $token = str_repeat('a', 64);
        StaffInvitation::create([
            'email' => 'staff@example.test',
            'token' => $token,
            'level' => User::STAFF_LEVEL_VIEWER,
            'permissions' => [User::PERM_VIEW_REPORTS],
            'invited_by' => $this->owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->postJson("/admin/staff/invitations/{$token}/accept", [
            'name' => 'Invited Staff',
            'password' => 'StrongStaffPass123!',
            'password_confirmation' => 'StrongStaffPass123!',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', [
            'email' => 'staff@example.test',
            'role' => User::ROLE_STAFF,
            'staff_level' => User::STAFF_LEVEL_VIEWER,
        ]);
        $this->assertNotNull(StaffInvitation::where('token', $token)->value('accepted_at'));
    }
}
