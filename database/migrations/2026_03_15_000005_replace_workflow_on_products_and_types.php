<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Products: replace old workflow_status enum + add new FK columns ---
        if (Schema::hasColumn('products', 'workflow_status')) {
            Schema::table('products', function (Blueprint $table) {
                // Drop old FK only if it exists
                try {
                    $table->dropForeign(['workflow_assignee_id']);
                } catch (\Throwable) {
                    // FK may not exist
                }
                try {
                    $table->dropIndex(['workflow_status']);
                } catch (\Throwable) {
                    // Index may not exist
                }
                $columns = ['workflow_status'];
                if (Schema::hasColumn('products', 'workflow_assignee_id')) {
                    $columns[] = 'workflow_assignee_id';
                }
                $table->dropColumn($columns);
            });
        }

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'workflow_id')) {
                $table->char('workflow_id', 36)->nullable()->after('status');
                $table->foreign('workflow_id')->references('id')->on('workflows')->nullOnDelete();
            }
            if (!Schema::hasColumn('products', 'current_workflow_status_id')) {
                $table->char('current_workflow_status_id', 36)->nullable()->after('workflow_id');
                $table->foreign('current_workflow_status_id')->references('id')->on('workflow_statuses')->nullOnDelete();
                $table->index('current_workflow_status_id');
            }
            if (!Schema::hasColumn('products', 'workflow_assignee_id')) {
                $table->char('workflow_assignee_id', 36)->nullable()->after('current_workflow_status_id');
                $table->foreign('workflow_assignee_id')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('products', 'workflow_team_id')) {
                $table->char('workflow_team_id', 36)->nullable()->after('workflow_assignee_id');
                $table->foreign('workflow_team_id')->references('id')->on('teams')->nullOnDelete();
            }
        });

        // --- ProductTypes: replace workflow_enabled with workflow_id ---
        if (Schema::hasColumn('product_types', 'workflow_enabled')) {
            Schema::table('product_types', function (Blueprint $table) {
                $table->dropColumn('workflow_enabled');
            });
        }

        if (!Schema::hasColumn('product_types', 'workflow_id')) {
            Schema::table('product_types', function (Blueprint $table) {
                $table->char('workflow_id', 36)->nullable()->after('has_physical_dimensions');
                $table->foreign('workflow_id')->references('id')->on('workflows')->nullOnDelete();
            });
        }

        // --- WorkflowTasks: add new references ---
        Schema::table('workflow_tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('workflow_tasks', 'workflow_status_id')) {
                $table->char('workflow_status_id', 36)->nullable()->after('status');
                $table->foreign('workflow_status_id')->references('id')->on('workflow_statuses')->nullOnDelete();
            }
            if (!Schema::hasColumn('workflow_tasks', 'team_id')) {
                $table->char('team_id', 36)->nullable()->after('assigned_to');
                $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
            }
            if (!Schema::hasColumn('workflow_tasks', 'project_id')) {
                $table->char('project_id', 36)->nullable()->after('team_id');
                $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('workflow_tasks', function (Blueprint $table) {
            $table->dropForeign(['workflow_status_id']);
            $table->dropForeign(['team_id']);
            $table->dropForeign(['project_id']);
            $table->dropColumn(['workflow_status_id', 'team_id', 'project_id']);
        });

        Schema::table('product_types', function (Blueprint $table) {
            $table->dropForeign(['workflow_id']);
            $table->dropColumn('workflow_id');
        });

        Schema::table('product_types', function (Blueprint $table) {
            $table->boolean('workflow_enabled')->default(false)->after('has_physical_dimensions');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['workflow_id']);
            $table->dropForeign(['current_workflow_status_id']);
            $table->dropForeign(['workflow_assignee_id']);
            $table->dropForeign(['workflow_team_id']);
            $table->dropIndex(['current_workflow_status_id']);
            $table->dropColumn(['workflow_id', 'current_workflow_status_id', 'workflow_assignee_id', 'workflow_team_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->enum('workflow_status', ['editing', 'review', 'approved'])->nullable()->default(null)->after('status');
            $table->char('workflow_assignee_id', 36)->nullable()->after('workflow_status');
            $table->foreign('workflow_assignee_id')->references('id')->on('users')->nullOnDelete();
            $table->index('workflow_status');
        });
    }
};
