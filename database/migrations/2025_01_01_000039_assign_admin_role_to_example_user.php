<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $user = User::where('email', 'admin@example.com')->first();

        if ($user && !$user->hasRole('Admin')) {
            $user->assignRole('Admin');
        }
    }

    public function down(): void
    {
        // Intentionally left empty
    }
};
