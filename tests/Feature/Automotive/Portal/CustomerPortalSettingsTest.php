<?php

namespace Tests\Feature\Automotive\Portal;

use App\Models\CustomerOnboardingProfile;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerPortalSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_settings_page_renders_portal_owned_account_sections(): void
    {
        [$user, $tenant] = $this->createPortalWorkspaceUser();

        $response = $this->actingAs($user, 'web')
            ->get(route('automotive.portal.settings'));

        $response->assertOk();
        $response->assertSee('Account &amp; Settings', false);
        $response->assertSee('Tenant Account Profile', false);
        $response->assertSee('Credential Controls', false);
        $response->assertSee('Workspace Directory', false);
        $response->assertSee($tenant->id, false);
    }

    public function test_portal_profile_update_syncs_user_profile_and_linked_tenant_snapshot(): void
    {
        [$user, $tenant] = $this->createPortalWorkspaceUser();

        $response = $this->actingAs($user, 'web')
            ->withSession(['_token' => 'profile-token'])
            ->put(route('automotive.portal.settings.profile.update'), [
                '_token' => 'profile-token',
                'name' => 'Updated Portal User',
                'email' => 'updated-portal-user@example.test',
                'company_name' => 'Updated Portal Company',
            ]);

        $response->assertRedirect(route('automotive.portal.settings'));
        $response->assertSessionHas('success', 'Your portal profile and workspace information were updated.');

        $user->refresh();
        $profile = CustomerOnboardingProfile::query()->where('user_id', $user->id)->firstOrFail();
        $tenant->refresh();

        $this->assertSame('Updated Portal User', $user->name);
        $this->assertSame('updated-portal-user@example.test', $user->email);
        $this->assertSame('Updated Portal Company', $profile->company_name);
        $this->assertSame('Updated Portal Company', $tenant->company_name);
        $this->assertSame('updated-portal-user@example.test', $tenant->owner_email);
    }

    public function test_portal_security_form_updates_password(): void
    {
        [$user] = $this->createPortalWorkspaceUser();

        $response = $this->actingAs($user, 'web')
            ->withSession(['_token' => 'security-token'])
            ->put(route('automotive.portal.settings.security.update'), [
                '_token' => 'security-token',
                'current_password' => 'password123',
                'password' => 'new-password-456',
                'password_confirmation' => 'new-password-456',
            ]);

        $response->assertRedirect(route('automotive.portal.settings'));
        $response->assertSessionHas('success', 'Your portal password was updated.');

        $user->refresh();

        $this->assertTrue(Hash::check('new-password-456', $user->password));
    }

    protected function createPortalWorkspaceUser(): array
    {
        $user = User::query()->create([
            'name' => 'Portal Settings User',
            'email' => 'portal-settings-' . uniqid() . '@example.test',
            'password' => Hash::make('password123'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Settings Company',
            'subdomain' => 'portal-settings-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-portal-settings-' . uniqid(),
            'data' => [
                'company_name' => 'Portal Settings Company',
                'owner_email' => $user->email,
            ],
        ]);

        DB::table('tenant_users')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $tenant];
    }
}
