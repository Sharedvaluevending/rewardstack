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
        Schema::table('user_promo_tokens', function (Blueprint $table) {
            // First add a non-unique index so the foreign key is satisfied
            $table->index('user_id');
            // Now we can drop the unique constraint
            $table->dropUnique('user_promo_tokens_user_id_qr_code_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_promo_tokens', function (Blueprint $table) {
            $table->unique(['user_id', 'qr_code_id'], 'user_promo_tokens_user_id_qr_code_id_unique');
            $table->dropIndex(['user_id']);
        });
    }
};
