<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            if (!Schema::hasColumn('qr_codes', 'cross_promotion_id')) {
                $table->unsignedBigInteger('cross_promotion_id')->nullable()->after('stackable_pool_id');
                $table->index('cross_promotion_id');
                $table->foreign('cross_promotion_id')
                    ->references('id')
                    ->on('cross_promotions')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            if (Schema::hasColumn('qr_codes', 'cross_promotion_id')) {
                $table->dropForeign(['cross_promotion_id']);
                $table->dropIndex(['cross_promotion_id']);
                $table->dropColumn('cross_promotion_id');
            }
        });
    }
};

