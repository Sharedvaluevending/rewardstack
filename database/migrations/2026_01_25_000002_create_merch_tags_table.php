<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_tags', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->foreignId('qr_code_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['business_id', 'is_active']);
            $table->index(['owner_user_id', 'claimed_at']);
        });

        Schema::table('scans', function (Blueprint $table) {
            if (!Schema::hasColumn('scans', 'merch_tag_id')) {
                $table->foreignId('merch_tag_id')
                    ->nullable()
                    ->constrained('merch_tags')
                    ->nullOnDelete()
                    ->after('qr_code_id');
                $table->index('merch_tag_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            if (Schema::hasColumn('scans', 'merch_tag_id')) {
                $table->dropConstrainedForeignId('merch_tag_id');
            }
        });

        Schema::dropIfExists('merch_tags');
    }
};
