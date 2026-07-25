<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // In Production duerfen NIEMALS Default-Konten mit trivialem Passwort entstehen.
        // 'admin@publixx.com' / 'password' waere sonst ein offener Vollzugriff (Audit K-4).
        if (app()->environment('production')) {
            $this->createProductionAdmin();

            return;
        }

        // Nicht-Production (local/testing/demo): bequeme Default-Admins fuer die Entwicklung.
        $admins = [
            'admin@publixx.com',
            'admin@example.com',
        ];

        foreach ($admins as $email) {
            $admin = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => 'Admin',
                    'password' => 'password',
                    'language' => 'de',
                    'is_active' => true,
                ]
            );

            $admin->assignRole('Admin');
        }
    }

    /**
     * In Production wird ein Admin ausschliesslich aus explizit gesetzten
     * Umgebungsvariablen erzeugt. Ohne diese wird bewusst KEIN Konto angelegt —
     * der Betreiber muss den ersten Admin bewusst (mit starkem Passwort) einrichten.
     */
    private function createProductionAdmin(): void
    {
        $email = env('INITIAL_ADMIN_EMAIL');
        $password = env('INITIAL_ADMIN_PASSWORD');

        if (empty($email) || empty($password)) {
            $this->command?->warn(
                'AdminUserSeeder: In Production wurde kein Admin-Konto angelegt. '
                . 'Setze INITIAL_ADMIN_EMAIL und INITIAL_ADMIN_PASSWORD in der .env '
                . 'oder lege den ersten Admin manuell an (php artisan tinker).'
            );

            return;
        }

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin',
                'password' => $password, // wird durch den 'hashed'-Cast des User-Models gehasht
                'language' => 'de',
                'is_active' => true,
            ]
        );

        $admin->assignRole('Admin');
    }
}
