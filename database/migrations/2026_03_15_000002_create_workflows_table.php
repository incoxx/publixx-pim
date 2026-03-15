<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workflows')) {
            Schema::create('workflows', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->char('start_status_id', 36)->nullable();
                $table->char('end_status_id', 36)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('start_status_id')->references('id')->on('workflow_statuses')->nullOnDelete();
                $table->foreign('end_status_id')->references('id')->on('workflow_statuses')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('workflow_status_assignments')) {
            Schema::create('workflow_status_assignments', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('workflow_id', 36);
                $table->char('workflow_status_id', 36);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('workflow_id')->references('id')->on('workflows')->cascadeOnDelete();
                $table->foreign('workflow_status_id')->references('id')->on('workflow_statuses')->cascadeOnDelete();
                $table->unique(['workflow_id', 'workflow_status_id'], 'wf_status_assignments_wf_status_unique');
            });
        }

        if (!Schema::hasTable('workflow_transitions')) {
            Schema::create('workflow_transitions', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('workflow_id', 36);
                $table->char('from_status_id', 36);
                $table->char('to_status_id', 36);
                $table->integer('duration_hours')->nullable();
                $table->string('name', 255)->nullable();
                $table->timestamps();

                $table->foreign('workflow_id')->references('id')->on('workflows')->cascadeOnDelete();
                $table->foreign('from_status_id')->references('id')->on('workflow_statuses')->cascadeOnDelete();
                $table->foreign('to_status_id')->references('id')->on('workflow_statuses')->cascadeOnDelete();
                $table->unique(['workflow_id', 'from_status_id', 'to_status_id'], 'wf_transitions_wf_from_to_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('workflow_status_assignments');
        Schema::dropIfExists('workflows');
    }
};
