<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calibration_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->string('kind');
            $table->date('cycle_valid_until');
            $table->timestamp('sent_at');
            $table->string('channel');
            $table->timestamps();

            // One reminder per kind per certificate cycle (PRD §F2).
            $table->unique(['equipment_id', 'kind', 'cycle_valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_reminders');
    }
};
