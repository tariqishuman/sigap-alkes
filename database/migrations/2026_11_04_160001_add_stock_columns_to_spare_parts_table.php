<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spare_parts', function (Blueprint $table) {
            $table->unsignedInteger('stock')->default(0)->after('unit');
            $table->unsignedInteger('min_stock')->default(0)->after('stock');
        });
    }

    public function down(): void
    {
        Schema::table('spare_parts', function (Blueprint $table) {
            $table->dropColumn(['stock', 'min_stock']);
        });
    }
};
