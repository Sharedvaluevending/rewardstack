<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_messages', function (Blueprint $table) {
            $table->string('promo_link_token', 64)->nullable()->after('custom_args');
            $table->timestamp('promo_link_used_at')->nullable()->after('promo_link_token');

            $table->unique('promo_link_token', 'crm_messages_promo_link_token_unique');
        });
    }

    public function down(): void
    {
        Schema::table('crm_messages', function (Blueprint $table) {
            $table->dropUnique('crm_messages_promo_link_token_unique');
            $table->dropColumn(['promo_link_token', 'promo_link_used_at']);
        });
    }
};
