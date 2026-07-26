<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passwort für Freigabelinks optional machen: Ein Katalog-/Dokument-Freigabelink
 * darf künftig auch nur über den (nicht erratbaren) Token ohne Passwort zugänglich
 * sein. Bestehende Links behalten ihr gesetztes Passwort.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_share_links', function (Blueprint $table) {
            $table->string('password_hash')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('collection_share_links', function (Blueprint $table) {
            $table->string('password_hash')->nullable(false)->change();
        });
    }
};
