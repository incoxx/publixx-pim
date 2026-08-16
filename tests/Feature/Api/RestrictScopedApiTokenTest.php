<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Absicherung von App\Http\Middleware\RestrictScopedApiToken.
 *
 * Die Middleware ist die Zusicherung, dass ein Sanctum-Token ohne die
 * '*'-Faehigkeit — etwa der Embed-Token, der im Quelltext einer fremden Website
 * landet — ausschliesslich die oeffentliche Katalog-API erreicht, unabhaengig
 * von der PIM-Rolle des zugehoerigen Benutzers.
 *
 * Nebeneffekt, den diese Tests festhalten: Sanctum::actingAs() ohne zweites
 * Argument erzeugt einen Token, dessen can('*') false liefert. Wer das in einem
 * Test vergisst, bekommt auf allen Nicht-Katalog-Routen ein 403 und sucht den
 * Fehler faelschlich bei den Berechtigungen.
 */
class RestrictScopedApiTokenTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->user = User::factory()->create();
        $this->user->assignRole(Role::findOrCreate('Admin', 'sanctum'));
        $this->user->unsetRelation('roles');
    }

    public function test_eingeschraenkter_token_wird_ausserhalb_des_katalogs_abgewiesen(): void
    {
        Sanctum::actingAs($this->user, ['catalog:read']);

        $this->getJson('/api/v1/unit-groups')
            ->assertForbidden()
            ->assertJsonPath('message', 'Dieser Token ist auf den Katalog-Lesezugriff beschränkt.');
    }

    public function test_eingeschraenkter_token_darf_den_katalog_lesen(): void
    {
        Sanctum::actingAs($this->user, ['catalog:read']);

        // Der Endpunkt selbst ist oeffentlich; entscheidend ist, dass die
        // Middleware hier nicht dazwischengeht.
        $this->getJson('/api/v1/catalog/settings')->assertOk();
    }

    public function test_token_mit_stern_darf_ueberall_hin(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->getJson('/api/v1/unit-groups')->assertOk();
    }

    /**
     * Normale Logins haben gar keinen Access-Token; die Middleware laesst sie
     * unangetastet durch (currentAccessToken() ist null).
     */
    public function test_login_ohne_token_ist_nicht_betroffen(): void
    {
        $this->actingAs($this->user);

        $this->getJson('/api/v1/unit-groups')->assertOk();
    }
}
