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
        Schema::table('businesses', function (Blueprint $table) {
            $table->timestamp('onboarding_completed_at')->nullable()->after('is_testing_account');
            $table->json('onboarding_steps')->nullable()->after('onboarding_completed_at');
            $table->boolean('onboarding_dismissed')->default(false)->after('onboarding_steps');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['onboarding_completed_at', 'onboarding_steps', 'onboarding_dismissed']);
        });
    }
};
