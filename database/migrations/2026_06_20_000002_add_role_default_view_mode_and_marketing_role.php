<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleTabPermission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 2 — Rollenabhängige Cockpits.
 *
 *  1. Spalte roles.default_view_mode ('cockpit' | 'gui') — Standard-Ansicht je Rolle.
 *     Die persönliche Wahl (UserPreference 'view_mode') schlägt diesen Wert weiterhin.
 *  2. Neue Rolle "Marketing" (Content-/Medien-/Übersetzungs-/Channel-Fokus) inkl.
 *     Tab-Rechten und default_view_mode='cockpit'.
 *
 * Additiv und idempotent, damit bestehende Installationen sauber nachziehen.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── 1. Spalte für die Standard-Ansicht je Rolle ─────────────
        if (! Schema::hasColumn('roles', 'default_view_mode')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->string('default_view_mode', 10)->default('gui')->after('guard_name');
            });
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ─── 2. Marketing-Rolle anlegen ──────────────────────────────
        $marketingPermissions = [
            // Medien/Content: Vollzugriff
            'media.view', 'media.create', 'media.edit', 'media.delete',
            'media-usage-types.view',
            // Produkte & Struktur: nur lesend (Kontext)
            'products.view',
            'product-types.view',
            'attributes.view',
            'attribute-types.view',
            'hierarchies.view',
            'hierarchy-nodes.view',
            'relation-types.view',
            'value-lists.view',
            // Übersetzungen
            'translations.view', 'translations.edit',
            // Ausspielung
            'portals.view',
            'catalog-templates.view',
            'reports.view',
            // Arbeitsauswahl / Suche / Export sichten
            'watchlist.view', 'watchlist.edit',
            'search.view',
            'export.view',
            // Basis
            'dashboard.view',
            'copilot.use',
            'semantic-search.view',
        ];

        // Sicherstellen, dass alle Permissions existieren (sind i. d. R. bereits geseedet)
        foreach ($marketingPermissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'sanctum']);
        }

        $marketing = Role::firstOrCreate(['name' => 'Marketing', 'guard_name' => 'sanctum']);
        $marketing->syncPermissions($marketingPermissions);
        $marketing->default_view_mode = 'cockpit';
        $marketing->save();

        // Tab-Rechte (RoleTabPermission): media=write (Default), Rest reduziert
        $marketingTabs = [
            'base-data' => 'read',
            'attributes' => 'read',
            'variant-attributes' => 'read',
            'variants' => 'read',
            'prices' => 'hidden',
            'relations' => 'read',
            'output-hierarchies' => 'read',
        ];
        foreach ($marketingTabs as $tabKey => $level) {
            RoleTabPermission::updateOrCreate(
                ['role_id' => $marketing->id, 'tab_key' => $tabKey],
                ['access_level' => $level],
            );
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $marketing = Role::where('name', 'Marketing')->where('guard_name', 'sanctum')->first();
        if ($marketing && $marketing->users()->count() === 0) {
            RoleTabPermission::where('role_id', $marketing->id)->delete();
            $marketing->delete();
        }

        if (Schema::hasColumn('roles', 'default_view_mode')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('default_view_mode');
            });
        }
    }
};
