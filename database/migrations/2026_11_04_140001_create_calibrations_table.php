<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calibrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->foreignId('testing_institution_id')->nullable()->constrained('testing_institutions')->restrictOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tested_at');
            $table->string('result');
            $table->string('certificate_number')->nullable();
            $table->date('valid_until')->nullable()->index();
            $table->string('certificate_path')->nullable();
            $table->timestamps();

            $table->unique(['testing_institution_id', 'certificate_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibrations');
    }
};
