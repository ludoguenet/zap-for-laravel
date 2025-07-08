<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Zap\Enums\ScheduleTypes;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->enum('schedule_type', ScheduleTypes::values())
                ->default(ScheduleTypes::CUSTOM)
                ->after('description');

            // Add indexes for performance
            $table->index('schedule_type', 'schedules_type_index');
            $table->index(['schedulable_type', 'schedulable_id', 'schedule_type'], 'schedules_schedulable_type_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex('schedules_type_index');
            $table->dropIndex('schedules_schedulable_type_index');
            $table->dropColumn('schedule_type');
        });
    }
};
