<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table): void {
            $table->string('satusehat_id', 100)->nullable()->index()->after('qr_token');
        });

        Schema::table('equipment_types', function (Blueprint $table): void {
            $table->string('fhir_type_code', 50)->nullable()->after('residual_value_percent');
            $table->string('fhir_type_display', 150)->nullable()->after('fhir_type_code');
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table): void {
            $table->dropColumn('satusehat_id');
        });

        Schema::table('equipment_types', function (Blueprint $table): void {
            $table->dropColumn(['fhir_type_code', 'fhir_type_display']);
        });
    }
};
