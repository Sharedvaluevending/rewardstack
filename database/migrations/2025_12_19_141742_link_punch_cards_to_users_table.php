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
        // Check if user_id column already exists
        if (!Schema::hasColumn('punch_cards', 'user_id')) {
            Schema::table('punch_cards', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('promotion_id')->constrained()->onDelete('cascade');
                $table->index('user_id');
            });
        }
        
        // Add new unique constraint: one punch card per user per promotion (if user_id is set)
        // Keep the old constraint for backward compatibility with anonymous users
        Schema::table('punch_cards', function (Blueprint $table) {
            if (!Schema::hasIndex('punch_cards', 'punch_cards_promotion_user_unique')) {
                // Add unique constraint for user_id + promotion_id (when user_id is not null)
                // Note: MySQL doesn't support partial unique indexes, so we'll use a composite unique
                // that allows nulls. The old constraint handles anonymous users.
                try {
                    $table->unique(['promotion_id', 'user_id'], 'punch_cards_promotion_user_unique');
                } catch (\Exception $e) {
                    // Index might already exist, ignore
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('punch_cards', function (Blueprint $table) {
            $table->dropUnique('punch_cards_promotion_user_unique');
            $table->dropIndex(['customer_identifier']);
            $table->unique(['promotion_id', 'customer_identifier']);
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
