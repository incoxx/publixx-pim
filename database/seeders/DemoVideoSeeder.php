<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder für Video-Engine Demo-Daten.
 * Baut auf bestehenden Seedern auf und ergänzt nur video-spezifische Daten.
 *
 * Vollständig idempotent: Kann beliebig oft ausgeführt werden.
 */
class DemoVideoSeeder extends Seeder
{
    public function run(): void
    {
        // Bestehende Seeder aufrufen (diese sind selbst idempotent)
        $this->call([
            RoleAndPermissionSeeder::class,
            AdminUserSeeder::class,
            ProductTypeSeeder::class,
            DemoAttributeSeeder::class,
            DemoHierarchySeeder::class,
            DemoProductSeeder::class,
            DemoMediaSeeder::class,
        ]);

        // Video-spezifischer Demo-User
        $this->createDemoUser();
    }

    /**
     * Erstellt den Demo-User für Video-Aufnahmen.
     * E-Mail und Passwort werden in .env konfiguriert.
     */
    private function createDemoUser(): void
    {
        $email = env('VIDEO_DEMO_USER_EMAIL', 'demo@anypim.local');
        $password = env('VIDEO_DEMO_USER_PASSWORD', 'demo1234');

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Demo User',
                'password' => Hash::make($password),
                'language' => 'de',
                'is_active' => true,
            ]
        );

        // Admin-Rolle zuweisen (für vollen Zugriff in Videos)
        try {
            if (!$user->hasRole('Admin')) {
                $user->assignRole('Admin');
            }
        } catch (\Throwable $e) {
            $this->command->warn("Rolle konnte nicht zugewiesen werden: {$e->getMessage()}");
        }

        $this->command->info("Demo-User: {$email} (Admin-Rolle)");
    }
}
