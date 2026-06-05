<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\ProductReferenceProfile;
use App\Services\Conformance\ProfileConformanceChecker;
use PHPUnit\Framework\TestCase;

/**
 * Testet die deterministische Regel-Engine (evaluateRule) ohne DB.
 */
class ProfileConformanceCheckerTest extends TestCase
{
    // ─── required / not_empty ────────────────────────────────────

    public function test_required_fails_on_null(): void
    {
        $rule = ['attribute' => 'torque', 'check' => 'required', 'severity' => 'error'];
        $deviation = ProfileConformanceChecker::evaluateRule($rule, null);

        $this->assertNotNull($deviation);
        $this->assertSame('torque', $deviation['attribute']);
        $this->assertSame('error', $deviation['severity']);
    }

    public function test_required_fails_on_empty_string(): void
    {
        $rule = ['attribute' => 'torque', 'check' => 'required'];
        $this->assertNotNull(ProfileConformanceChecker::evaluateRule($rule, ''));
    }

    public function test_required_passes_on_value(): void
    {
        $rule = ['attribute' => 'torque', 'check' => 'required'];
        $this->assertNull(ProfileConformanceChecker::evaluateRule($rule, 42.0));
    }

    // ─── between ─────────────────────────────────────────────────

    public function test_between_passes_in_range(): void
    {
        $rule = ['attribute' => 'voltage', 'check' => 'between', 'min' => 10.8, 'max' => 18];
        $this->assertNull(ProfileConformanceChecker::evaluateRule($rule, 14.4));
    }

    public function test_between_fails_below_min(): void
    {
        $rule = ['attribute' => 'voltage', 'check' => 'between', 'min' => 10.8, 'max' => 18];
        $this->assertNotNull(ProfileConformanceChecker::evaluateRule($rule, 3.6));
    }

    public function test_between_skips_empty_value(): void
    {
        // Leere Werte werden nur von "required" gemeldet, nicht von Bereichsregeln
        $rule = ['attribute' => 'voltage', 'check' => 'between', 'min' => 10.8, 'max' => 18];
        $this->assertNull(ProfileConformanceChecker::evaluateRule($rule, null));
    }

    // ─── min / max ───────────────────────────────────────────────

    public function test_max_fails_above_limit(): void
    {
        $rule = ['attribute' => 'weight', 'check' => 'max', 'value' => 3];
        $this->assertNotNull(ProfileConformanceChecker::evaluateRule($rule, 4.5));
    }

    public function test_min_passes_at_boundary(): void
    {
        $rule = ['attribute' => 'weight', 'check' => 'min', 'value' => 1];
        $this->assertNull(ProfileConformanceChecker::evaluateRule($rule, 1.0));
    }

    // ─── in_list ─────────────────────────────────────────────────

    public function test_in_list_passes_for_allowed_value(): void
    {
        $rule = ['attribute' => 'battery', 'check' => 'in_list', 'values' => ['li-ion', 'nimh']];
        $this->assertNull(ProfileConformanceChecker::evaluateRule($rule, 'li-ion'));
    }

    public function test_in_list_fails_for_unknown_value(): void
    {
        $rule = ['attribute' => 'battery', 'check' => 'in_list', 'values' => ['li-ion', 'nimh']];
        $this->assertNotNull(ProfileConformanceChecker::evaluateRule($rule, 'lead'));
    }

    // ─── max_length / regex ──────────────────────────────────────

    public function test_max_length_fails_when_too_long(): void
    {
        $rule = ['attribute' => 'name', 'check' => 'max_length', 'value' => 5];
        $this->assertNotNull(ProfileConformanceChecker::evaluateRule($rule, 'too-long'));
    }

    public function test_regex_passes_on_match(): void
    {
        $rule = ['attribute' => 'ean', 'check' => 'regex', 'pattern' => '/^\d{13}$/'];
        $this->assertNull(ProfileConformanceChecker::evaluateRule($rule, '4012345678901'));
    }

    public function test_regex_fails_on_mismatch(): void
    {
        $rule = ['attribute' => 'ean', 'check' => 'regex', 'pattern' => '/^\d{13}$/'];
        $this->assertNotNull(ProfileConformanceChecker::evaluateRule($rule, 'abc'));
    }

    // ─── unbekannter Check ───────────────────────────────────────

    public function test_unknown_check_is_ignored(): void
    {
        $rule = ['attribute' => 'x', 'check' => 'does_not_exist'];
        $this->assertNull(ProfileConformanceChecker::evaluateRule($rule, 'whatever'));
    }

    // ─── effectiveRules: Vererbung (DB-frei über setRelation) ────

    /**
     * Baut ein Profil mit optionalem Eltern-Profil ohne DB-Zugriff.
     *
     * @param array<int, array<string, mixed>> $rules
     */
    private function makeProfile(string $id, array $rules, ?ProductReferenceProfile $parent = null): ProductReferenceProfile
    {
        $profile = new ProductReferenceProfile(['rules' => $rules]);
        $profile->id = $id;
        // Relation manuell setzen → kein DB-Zugriff in effectiveRules()
        $profile->setRelation('parentProfile', $parent);

        return $profile;
    }

    public function test_effective_rules_single_profile(): void
    {
        $profile = $this->makeProfile('a', [
            ['attribute' => 'torque', 'check' => 'required', 'severity' => 'error'],
        ]);

        $rules = (new ProfileConformanceChecker())->effectiveRules($profile);

        $this->assertCount(1, $rules);
        $this->assertSame('torque', $rules[0]['attribute']);
    }

    public function test_effective_rules_child_inherits_and_adds(): void
    {
        $parent = $this->makeProfile('parent', [
            ['attribute' => 'voltage', 'check' => 'required', 'severity' => 'error'],
        ]);
        $child = $this->makeProfile('child', [
            ['attribute' => 'torque', 'check' => 'required', 'severity' => 'error'],
        ], $parent);

        $rules = (new ProfileConformanceChecker())->effectiveRules($child);

        // Eltern-Regel (voltage) + eigene Regel (torque)
        $attrs = array_column($rules, 'attribute');
        $this->assertContains('voltage', $attrs);
        $this->assertContains('torque', $attrs);
        $this->assertCount(2, $rules);
    }

    public function test_effective_rules_child_overrides_same_key(): void
    {
        $parent = $this->makeProfile('parent', [
            ['attribute' => 'voltage', 'check' => 'between', 'min' => 10, 'max' => 20, 'severity' => 'warning'],
        ]);
        $child = $this->makeProfile('child', [
            ['attribute' => 'voltage', 'check' => 'between', 'min' => 12, 'max' => 18, 'severity' => 'error'],
        ], $parent);

        $rules = (new ProfileConformanceChecker())->effectiveRules($child);

        // Gleicher Schlüssel (voltage|between) → Kind gewinnt
        $this->assertCount(1, $rules);
        $this->assertSame(12, $rules[0]['min']);
        $this->assertSame('error', $rules[0]['severity']);
    }

    public function test_effective_rules_handles_cycle_without_infinite_loop(): void
    {
        $a = $this->makeProfile('a', [['attribute' => 'x', 'check' => 'required']]);
        $b = $this->makeProfile('b', [['attribute' => 'y', 'check' => 'required']], $a);
        // Zyklus: a zeigt zurück auf b
        $a->setRelation('parentProfile', $b);

        $rules = (new ProfileConformanceChecker())->effectiveRules($b);

        // Kein Endlos-Loop, beide Regeln vorhanden
        $attrs = array_column($rules, 'attribute');
        $this->assertContains('x', $attrs);
        $this->assertContains('y', $attrs);
    }
}
