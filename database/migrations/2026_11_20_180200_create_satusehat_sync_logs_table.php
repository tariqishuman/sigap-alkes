<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('satusehat_sync_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('resource', 50)->index();
            $table->unsignedBigInteger('local_id')->nullable()->index();
            $table->string('action', 20); // create | update | probe
            $table->string('status', 20); // success | failed | skipped
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('response_summary')->nullable();
            $table->timestamp('synced_at')->useCurrent()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satusehat_sync_logs');
    }
};
