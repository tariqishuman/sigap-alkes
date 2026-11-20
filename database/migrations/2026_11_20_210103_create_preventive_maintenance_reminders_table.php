<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preventive_maintenance_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->date('cycle_due_at');
            $table->timestamp('sent_at');
            $table->string('channel', 64)->default('database');
            $table->timestamps();

            $table->unique(['equipment_id', 'kind', 'cycle_due_at'], 'pm_reminders_unique_cycle');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preventive_maintenance_reminders');
    }
};
