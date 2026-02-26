<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->unsignedInteger('punch_card_max_cards_per_user')->nullable()->after('is_featured');
            $table->unsignedInteger('punch_card_total_cards_limit')->nullable()->after('punch_card_max_cards_per_user');
            $table->unsignedInteger('punch_card_max_punches_per_day')->nullable()->after('punch_card_total_cards_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn([
                'punch_card_max_cards_per_user',
                'punch_card_total_cards_limit',
                'punch_card_max_punches_per_day',
            ]);
        });
    }
};
