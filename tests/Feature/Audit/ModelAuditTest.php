<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Jobs\WriteAuditLog;
use App\Models\Attribute;
use App\Models\HierarchyNode;
use App\Models\Product;
use App\Models\User;
use App\Support\AuditContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Prüft den zentralen Audit-Hook (App\Models\Concerns\Auditable) für die
 * produktbezogenen Models Product, HierarchyNode und Attribute.
 *
 * Es wird der dispatchte WriteAuditLog-Job geprüft (Queue::fake), da der Job
 * mit ->afterCommit() läuft und unter RefreshDatabase sonst nicht ausgeführt
 * würde.
 */
class ModelAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Der Hook liest den Akteur aus request()->user(); im Nicht-HTTP-Test
        // explizit setzen, damit das Verhalten dem Request-Kontext entspricht.
        request()->setUserResolver(fn () => $this->user);
    }

    public function test_product_create_update_delete_are_audited(): void
    {
        Queue::fake();

        $product = Product::factory()->create();

        Queue::assertPushed(
            WriteAuditLog::class,
            fn (WriteAuditLog $job) => $job->auditableType === 'Product'
                && $job->auditableId === $product->id
                && $job->action === 'created'
                && $job->userId === $this->user->id
                && $job->newValues !== null
        );

        $product->update(['name' => 'Geänderter Name']);

        Queue::assertPushed(
            WriteAuditLog::class,
            fn (WriteAuditLog $job) => $job->auditableType === 'Product'
                && $job->action === 'updated'
                && ($job->newValues['name'] ?? null) === 'Geänderter Name'
                && array_key_exists('name', $job->oldValues ?? [])
        );

        $productId = $product->id;
        $product->delete();

        Queue::assertPushed(
            WriteAuditLog::class,
            fn (WriteAuditLog $job) => $job->auditableId === $productId
                && $job->action === 'deleted'
                && $job->oldValues !== null
        );
    }

    public function test_hierarchy_node_and_attribute_are_audited(): void
    {
        Queue::fake();

        $node = HierarchyNode::factory()->create();
        $attribute = Attribute::factory()->create();

        Queue::assertPushed(
            WriteAuditLog::class,
            fn (WriteAuditLog $job) => $job->auditableType === 'HierarchyNode'
                && $job->auditableId === $node->id
                && $job->action === 'created'
        );

        Queue::assertPushed(
            WriteAuditLog::class,
            fn (WriteAuditLog $job) => $job->auditableType === 'Attribute'
                && $job->auditableId === $attribute->id
                && $job->action === 'created'
        );
    }

    public function test_updates_without_relevant_changes_are_not_audited(): void
    {
        Queue::fake();

        $product = Product::factory()->create();

        // Speichern ohne Änderung darf keinen (leeren) Update-Eintrag erzeugen.
        $product->save();

        Queue::assertNotPushed(
            WriteAuditLog::class,
            fn (WriteAuditLog $job) => $job->action === 'updated'
        );
    }

    public function test_no_audit_without_authenticated_user(): void
    {
        Queue::fake();
        request()->setUserResolver(fn () => null);

        Product::factory()->create();

        Queue::assertNotPushed(WriteAuditLog::class);
    }

    public function test_without_auditing_suppresses_audit(): void
    {
        Queue::fake();

        AuditContext::withoutAuditing(fn () => Product::factory()->create());

        Queue::assertNotPushed(WriteAuditLog::class);
    }
}
