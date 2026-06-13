<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Role;
use App\Models\User;
use App\Models\ValueList;
use App\Models\ValueListEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests für ValueListController (Selection Lists) und ValueListEntryController.
 *
 * Routen:
 *   GET/POST   /api/v1/value-lists
 *   GET/PUT/DELETE /api/v1/value-lists/{value_list}
 *   GET        /api/v1/value-lists/{value_list}/dependencies
 *   GET/POST   /api/v1/value-lists/{value_list}/entries
 *   GET/PUT/DELETE /api/v1/entries/{entry}   (shallow)
 *   GET        /api/v1/entries/{entry}/dependencies
 */
class SelectionListControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->user = User::factory()->create();
        $role = Role::findOrCreate('Admin', 'sanctum');
        $this->user->assignRole($role);
        $this->actingAs($this->user);
    }

    public function test_index_gibt_paginierte_value_lists_zurueck(): void
    {
        ValueList::factory()->count(4)->create();

        $response = $this->getJson('/api/v1/value-lists');

        $response->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_store_erstellt_value_list(): void
    {
        $response = $this->postJson('/api/v1/value-lists', [
            'technical_name' => 'farb-liste',
            'name_de'        => 'Farbliste',
            'name_en'        => 'Color List',
            'value_data_type' => 'String',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.technical_name', 'farb-liste');

        $this->assertDatabaseHas('value_lists', ['technical_name' => 'farb-liste']);
    }

    public function test_store_validiert_duplikaten_technical_name(): void
    {
        ValueList::factory()->create(['technical_name' => 'farb-liste']);

        $response = $this->postJson('/api/v1/value-lists', [
            'technical_name' => 'farb-liste',
            'name_de'        => 'Andere Farbliste',
            'value_data_type' => 'String',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['technical_name']);
    }

    public function test_store_validiert_pflichtfelder(): void
    {
        $response = $this->postJson('/api/v1/value-lists', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['technical_name', 'name_de']);
    }

    public function test_show_gibt_value_list_zurueck(): void
    {
        $list = ValueList::factory()->create();

        $response = $this->getJson("/api/v1/value-lists/{$list->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $list->id);
    }

    public function test_show_laedt_entries_include(): void
    {
        $list = ValueList::factory()->create();
        ValueListEntry::factory()->create(['value_list_id' => $list->id]);

        $response = $this->getJson("/api/v1/value-lists/{$list->id}?include=entries");

        $response->assertOk()
            ->assertJsonCount(1, 'data.entries');
    }

    public function test_update_aendert_value_list(): void
    {
        $list = ValueList::factory()->create(['name_de' => 'Alt']);

        $response = $this->putJson("/api/v1/value-lists/{$list->id}", [
            'name_de' => 'Neu',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name_de', 'Neu');
    }

    public function test_destroy_loescht_value_list(): void
    {
        $list = ValueList::factory()->create();

        $response = $this->deleteJson("/api/v1/value-lists/{$list->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('value_lists', ['id' => $list->id]);
    }

    public function test_destroy_mit_dependencies_gibt_409(): void
    {
        $list = ValueList::factory()->create();
        ValueListEntry::factory()->create(['value_list_id' => $list->id]);

        $response = $this->deleteJson("/api/v1/value-lists/{$list->id}");

        $response->assertStatus(409)
            ->assertJsonPath('type', 'deletion_constraint');

        $this->assertDatabaseHas('value_lists', ['id' => $list->id]);
    }

    public function test_destroy_force_loescht_trotz_dependencies(): void
    {
        $list = ValueList::factory()->create();
        ValueListEntry::factory()->create(['value_list_id' => $list->id]);

        $response = $this->deleteJson("/api/v1/value-lists/{$list->id}?force=true");

        $response->assertNoContent();
        $this->assertDatabaseMissing('value_lists', ['id' => $list->id]);
    }

    public function test_dependencies_gibt_constraint_info_zurueck(): void
    {
        $list = ValueList::factory()->create();

        $response = $this->getJson("/api/v1/value-lists/{$list->id}/dependencies");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['has_dependencies', 'total_count', 'dependencies']]);
    }

    public function test_entries_index_gibt_eintraege_zurueck(): void
    {
        $list = ValueList::factory()->create();
        ValueListEntry::factory()->count(3)->create(['value_list_id' => $list->id]);

        $response = $this->getJson("/api/v1/value-lists/{$list->id}/entries");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_entries_store_erstellt_eintrag(): void
    {
        $list = ValueList::factory()->create();

        $response = $this->postJson("/api/v1/value-lists/{$list->id}/entries", [
            'technical_name'   => 'rot',
            'display_value_de' => 'Rot',
            'display_value_en' => 'Red',
            'sort_order'       => 1,
            'is_active'        => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.technical_name', 'rot');

        $this->assertDatabaseHas('value_list_entries', [
            'value_list_id'  => $list->id,
            'technical_name' => 'rot',
        ]);
    }

    public function test_entries_update_aendert_eintrag(): void
    {
        $list  = ValueList::factory()->create();
        $entry = ValueListEntry::factory()->create([
            'value_list_id'    => $list->id,
            'display_value_de' => 'Alt',
        ]);

        $response = $this->putJson("/api/v1/entries/{$entry->id}", [
            'display_value_de' => 'Neu',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.display_value_de', 'Neu');
    }

    public function test_entries_destroy_loescht_eintrag(): void
    {
        $list  = ValueList::factory()->create();
        $entry = ValueListEntry::factory()->create(['value_list_id' => $list->id]);

        $response = $this->deleteJson("/api/v1/entries/{$entry->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('value_list_entries', ['id' => $entry->id]);
    }

    public function test_nicht_admin_hat_keinen_zugriff_auf_store(): void
    {
        // Benutzer ohne Admin-Rolle erstellen
        $keinAdmin = User::factory()->create();
        $this->actingAs($keinAdmin);

        $response = $this->postJson('/api/v1/value-lists', [
            'technical_name'  => 'test-liste',
            'name_de'         => 'Testliste',
            'value_data_type' => 'String',
        ]);

        $response->assertForbidden();
    }
}
