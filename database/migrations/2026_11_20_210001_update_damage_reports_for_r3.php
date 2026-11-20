<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->boolean('is_safe_to_use')->default(true)->after('complaint');
            $table->json('photos')->nullable()->after('is_safe_to_use');
            $table->boolean('marked_unfit_by_report')->default(false)->after('status');
            $table->foreignId('verified_by')->nullable()->after('marked_unfit_by_report')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->text('rejected_reason')->nullable()->after('verified_at');
            $table->text('closed_without_repair_reason')->nullable()->after('rejected_reason');
            $table->foreignId('closed_by')->nullable()->after('closed_without_repair_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable()->after('closed_by');
        });
    }

    public function down(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn([
                'is_safe_to_use',
                'photos',
                'marked_unfit_by_report',
                'verified_at',
                'rejected_reason',
                'closed_without_repair_reason',
                'closed_at',
            ]);
        });
    }
};
