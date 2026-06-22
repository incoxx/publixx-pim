<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->string('type', 20)->default('task')->after('color'); // task, hint, warning, error
            $table->string('status', 20)->default('open')->after('type'); // open, done
            $table->timestamp('resolved_at')->nullable()->after('status');
            $table->foreignUuid('resolved_by')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();
            $table->softDeletes();

            $table->index(['product_id', 'status']);
        });

        Schema::create('note_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('note_id')->constrained('notes')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['note_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_comments');
        Schema::table('notes', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['type', 'status', 'resolved_at', 'resolved_by']);
        });
    }
};
