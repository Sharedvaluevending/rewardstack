<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merch_tags', function (Blueprint $table) {
            if (!Schema::hasColumn('merch_tags', 'qr_image_path')) {
                $table->string('qr_image_path')->nullable()->after('order_item_id');
                $table->index('qr_image_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('merch_tags', function (Blueprint $table) {
            if (Schema::hasColumn('merch_tags', 'qr_image_path')) {
                $table->dropIndex(['qr_image_path']);
                $table->dropColumn('qr_image_path');
            }
        });
    }
};
