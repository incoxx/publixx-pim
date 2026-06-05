<?php

declare(strict_types=1);

namespace Tests\Feature\Conformance;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductConformanceResult;
use App\Models\ProductReferenceProfile;
use App\Services\Conformance\ProfileConformanceChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integrationstest des Konformitäts-Checkers gegen echte DB.
 *
 * Benötigt eine Test-Datenbank (RefreshDatabase) und läuft daher in CI,
 * nicht im DB-losen Entwicklungscontainer.
 */
class ProfileConformanceCheckerTest extends TestCase
{
    use RefreshDatabase;

    private function checker(): ProfileConformanceChecker
    {
        return app(ProfileConformanceChecker::class);
    }

    public function test_missing_required_attribute_is_flagged_as_fail(): void
    {
        $profile = ProductReferenceProfile::create([
            'technical_name' => 'akkubohrschrauber',
            'name' => 'Akkubohrschrauber',
            'is_active' => true,
            'rules' => [
                ['attribute' => 'torque', 'check' => 'required', 'severity' => 'error'],
            ],
        ]);

        $product = Product::factory()->create([
            'reference_profile_id' => $profile->id,
            'status' => 'active',
        ]);

        $result = $this->checker()->check($product, 'manual');

        $this->assertNotNull($result);
        $this->assertSame(ProductConformanceResult::STATUS_FAIL, $result->status);
        $this->assertSame(1, $result->error_count);
        $this->assertSame('torque', $result->deviations[0]['attribute']);
    }

    public function test_value_in_range_passes(): void
    {
        $voltage = Attribute::factory()->create([
            'technical_name' => 'voltage',
            'data_type' => 'Number',
        ]);

        $profile = ProductReferenceProfile::create([
            'technical_name' => 'akkubohrschrauber',
            'name' => 'Akkubohrschrauber',
            'is_active' => true,
            'rules' => [
                ['attribute' => 'voltage', 'check' => 'between', 'min' => 10.8, 'max' => 18, 'severity' => 'warning'],
            ],
        ]);

        $product = Product::factory()->create([
            'reference_profile_id' => $profile->id,
            'status' => 'active',
        ]);

        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $voltage->id,
            'value_string' => null,
            'value_number' => 14.4,
        ]);

        $result = $this->checker()->check($product, 'manual');

        $this->assertSame(ProductConformanceResult::STATUS_PASS, $result->status);
        $this->assertSame(100, $result->score);
    }

    public function test_inherited_rule_from_parent_is_applied(): void
    {
        $parent = ProductReferenceProfile::create([
            'technical_name' => 'elektrowerkzeug',
            'name' => 'Elektrowerkzeug',
            'is_abstract' => true,
            'rules' => [
                ['attribute' => 'voltage', 'check' => 'required', 'severity' => 'error'],
            ],
        ]);
        $child = ProductReferenceProfile::create([
            'technical_name' => 'akkubohrschrauber',
            'name' => 'Akkubohrschrauber',
            'parent_profile_id' => $parent->id,
            'rules' => [
                ['attribute' => 'torque', 'check' => 'required', 'severity' => 'error'],
            ],
        ]);

        $voltage = Attribute::factory()->create([
            'technical_name' => 'voltage',
            'data_type' => 'Number',
        ]);

        $product = Product::factory()->create([
            'reference_profile_id' => $child->id,
            'status' => 'active',
        ]);

        // voltage gepflegt, torque fehlt → genau eine Abweichung (torque)
        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $voltage->id,
            'value_string' => null,
            'value_number' => 18,
        ]);

        $result = $this->checker()->check($product, 'manual');

        $this->assertSame(ProductConformanceResult::STATUS_FAIL, $result->status);
        $this->assertSame(1, $result->deviation_count);
        $this->assertSame('torque', $result->deviations[0]['attribute']);
    }

    public function test_removing_profile_deletes_result(): void
    {
        $profile = ProductReferenceProfile::create([
            'technical_name' => 'akkubohrschrauber',
            'name' => 'Akkubohrschrauber',
            'rules' => [['attribute' => 'torque', 'check' => 'required', 'severity' => 'error']],
        ]);
        $product = Product::factory()->create([
            'reference_profile_id' => $profile->id,
            'status' => 'active',
        ]);

        $this->checker()->check($product, 'manual');
        $this->assertDatabaseHas('product_conformance_results', ['product_id' => $product->id]);

        // Profil entfernen → Check löscht das Ergebnis
        $product->update(['reference_profile_id' => null]);
        $this->checker()->check($product->fresh(), 'manual');

        $this->assertDatabaseMissing('product_conformance_results', ['product_id' => $product->id]);
    }
}
