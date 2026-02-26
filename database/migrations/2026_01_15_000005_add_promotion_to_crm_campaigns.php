<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('promotion_id')->nullable()->after('is_automation');
            $table->index('promotion_id');
            $table
                ->foreign('promotion_id')
                ->references('id')
                ->on('promotions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('crm_campaigns', function (Blueprint $table) {
            $table->dropForeign(['promotion_id']);
            $table->dropIndex(['promotion_id']);
            $table->dropColumn('promotion_id');
        });
    }
};
