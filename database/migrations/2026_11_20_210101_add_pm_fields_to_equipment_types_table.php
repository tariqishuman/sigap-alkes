<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_types', function (Blueprint $table) {
            $table->unsignedTinyInteger('pm_interval_months')->nullable()->after('calibration_interval_months');
            $table->json('pm_checklist')->nullable()->after('pm_interval_months');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_types', function (Blueprint $table) {
            $table->dropColumn(['pm_interval_months', 'pm_checklist']);
        });
    }
};
