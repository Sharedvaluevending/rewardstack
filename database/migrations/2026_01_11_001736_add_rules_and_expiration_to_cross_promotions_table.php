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
        Schema::table('cross_promotions', function (Blueprint $table) {
            // Cross-promo specific rules (overrides individual promotion rules)
            $table->json('cross_promo_rules')->nullable()->after('revenue_share_percent');
            
            // Rules agreement status
            // 'use_promotion_rules' = each promotion uses its own rules (default)
            // 'pending_agreement' = rules proposed, waiting for partner approval
            // 'agreed' = both businesses agreed on shared rules
            // 'overridden' = one business overrode with their own rules
            $table->string('rules_status')->default('use_promotion_rules')->after('cross_promo_rules');
            
            // Expiration and lifecycle
            $table->timestamp('starts_at')->nullable()->after('rules_status');
            $table->timestamp('expires_at')->nullable()->after('starts_at');
            $table->unsignedInteger('usage_limit')->nullable()->after('expires_at')->comment('Total claims allowed for entire cross-promo');
            
            // Indexes for performance
            $table->index('rules_status');
            $table->index('expires_at');
            $table->index('starts_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cross_promotions', function (Blueprint $table) {
            $table->dropIndex(['starts_at']);
            $table->dropIndex(['expires_at']);
            $table->dropIndex(['rules_status']);
            $table->dropColumn([
                'cross_promo_rules',
                'rules_status',
                'starts_at',
                'expires_at',
                'usage_limit',
            ]);
        });
    }
};
