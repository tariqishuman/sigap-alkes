<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_types', function (Blueprint $table) {
            $table->boolean('is_critical')->default(false)->after('requires_installation');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->boolean('is_critical_area')->default(false)->after('floor');
        });

        Schema::table('damage_reports', function (Blueprint $table) {
            $table->foreignId('acknowledged_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable()->after('acknowledged_by');
            $table->timestamp('escalated_at')->nullable()->after('acknowledged_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('push_pref_important')->default(true)->after('testing_institution_id');
            $table->boolean('push_pref_info')->default(false)->after('push_pref_important');
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('endpoint', 500)->unique();
            $table->string('public_key', 255);
            $table->string('auth_token', 255);
            $table->string('content_encoding', 32)->default('aesgcm');
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['push_pref_important', 'push_pref_info']);
        });

        Schema::table('damage_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('acknowledged_by');
            $table->dropColumn(['acknowledged_at', 'escalated_at']);
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('is_critical_area');
        });

        Schema::table('equipment_types', function (Blueprint $table) {
            $table->dropColumn('is_critical');
        });
    }
};
