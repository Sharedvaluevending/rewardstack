<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Make partner promotion nullable so we can support pending cross-promos.
        // - MySQL: use raw SQL (no doctrine/dbal dependency)
        // - SQLite (tests): rebuild the table to change NOT NULL -> NULL
        if (DB::getDriverName() === 'sqlite') {
            Schema::disableForeignKeyConstraints();

            // Create a new table with the same shape, except promotion_2_id is nullable.
            Schema::create('cross_promotions__tmp', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->text('description')->nullable();

                $table->foreignId('business_1_id')->constrained('businesses')->onDelete('cascade');
                $table->foreignId('business_2_id')->constrained('businesses')->onDelete('cascade');
                $table->unsignedBigInteger('requested_by_business_id')->nullable();
                $table->index('requested_by_business_id');

                $table->foreignId('promotion_1_id')->constrained('promotions')->onDelete('cascade');
                $table->unsignedBigInteger('promotion_2_id')->nullable();
                $table->foreign('promotion_2_id')->references('id')->on('promotions')->onDelete('cascade');

                $table->string('display_mode')->default('split');
                $table->decimal('revenue_share_percent', 5, 2)->default(50.00);
                $table->string('status')->default('pending');
                $table->index('status');
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('declined_at')->nullable();

                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index('code');
            });

            // Copy existing data.
            DB::statement(
                "INSERT INTO cross_promotions__tmp (
                    id, code, name, description,
                    business_1_id, business_2_id, promotion_1_id, promotion_2_id,
                    display_mode, revenue_share_percent, is_active,
                    created_at, updated_at, deleted_at
                )
                SELECT
                    id, code, name, description,
                    business_1_id, business_2_id, promotion_1_id, promotion_2_id,
                    display_mode, revenue_share_percent, is_active,
                    created_at, updated_at, deleted_at
                FROM cross_promotions"
            );

            // Swap tables.
            Schema::drop('cross_promotions');
            DB::statement('ALTER TABLE cross_promotions__tmp RENAME TO cross_promotions');

            Schema::enableForeignKeyConstraints();
        } else {
            DB::statement('ALTER TABLE cross_promotions MODIFY promotion_2_id bigint(20) unsigned NULL');
        }

        Schema::table('cross_promotions', function (Blueprint $table) {
            if (!Schema::hasColumn('cross_promotions', 'requested_by_business_id')) {
                $table->unsignedBigInteger('requested_by_business_id')->nullable()->after('business_2_id');
                $table->index('requested_by_business_id');
            }

            if (!Schema::hasColumn('cross_promotions', 'status')) {
                // pending -> accepted/declined
                $table->string('status')->default('pending')->after('revenue_share_percent');
                $table->index('status');
            }

            if (!Schema::hasColumn('cross_promotions', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('cross_promotions', 'declined_at')) {
                $table->timestamp('declined_at')->nullable()->after('accepted_at');
            }
        });

        // Backfill: existing cross-promos were created fully-formed, treat as accepted.
        if (Schema::hasColumn('cross_promotions', 'status')) {
            DB::table('cross_promotions')
                ->whereNull('deleted_at')
                ->update([
                    'status' => 'accepted',
                ]);
        }
    }

    public function down(): void
    {
        // Best-effort rollback: ensure promotion_2_id is not null again.
        DB::table('cross_promotions')
            ->whereNull('promotion_2_id')
            ->update(['promotion_2_id' => DB::raw('promotion_1_id')]);

        // NOTE: SQLite does not support ALTER TABLE ... MODIFY.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE cross_promotions MODIFY promotion_2_id bigint(20) unsigned NOT NULL');
        }

        Schema::table('cross_promotions', function (Blueprint $table) {
            if (Schema::hasColumn('cross_promotions', 'declined_at')) {
                $table->dropColumn('declined_at');
            }
            if (Schema::hasColumn('cross_promotions', 'accepted_at')) {
                $table->dropColumn('accepted_at');
            }
            if (Schema::hasColumn('cross_promotions', 'status')) {
                $table->dropIndex(['status']);
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('cross_promotions', 'requested_by_business_id')) {
                $table->dropIndex(['requested_by_business_id']);
                $table->dropColumn('requested_by_business_id');
            }
        });
    }
};

