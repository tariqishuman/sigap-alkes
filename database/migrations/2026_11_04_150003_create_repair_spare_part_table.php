<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_spare_part', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_id')->constrained('repairs')->cascadeOnDelete();
            $table->foreignId('spare_part_id')->constrained('spare_parts')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_spare_part');
    }
};
