<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tag-Gruppen — dasselbe Muster wie Attributgruppen (attribute_types):
 * mehrsprachiger Name, Sortierung, Tags hängen per FK daran.
 *
 * tag_group_id ist bewusst nullable: bestehende Tags bleiben gültig und
 * ungruppierte Tags sollen weiter erlaubt sein. Beim Löschen einer Gruppe
 * werden ihre Tags nicht gelöscht, sondern ungruppiert (nullOnDelete) —
 * ein Tag hängt an Produkten und Medien, den darf eine Gruppenpflege nicht
 * mitreißen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tag_groups', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('technical_name', 100)->unique();
            $table->string('name_de', 255);
            $table->string('name_en', 255)->nullable();
            $table->json('name_json')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->char('tag_group_id', 36)->nullable()->after('technical_name');
            $table->foreign('tag_group_id')->references('id')->on('tag_groups')->nullOnDelete();
            $table->index('tag_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropForeign(['tag_group_id']);
            $table->dropIndex(['tag_group_id']);
            $table->dropColumn('tag_group_id');
        });

        Schema::dropIfExists('tag_groups');
    }
};
