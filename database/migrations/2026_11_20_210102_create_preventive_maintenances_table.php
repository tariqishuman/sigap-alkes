<?php

use App\Enums\PreventiveMaintenanceResult;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preventive_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->date('performed_at')->index();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('checklist_results');
            $table->string('result')->default(PreventiveMaintenanceResult::Good->value)->index();
            $table->text('notes')->nullable();
            $table->date('next_due_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preventive_maintenances');
    }
};
