<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Cloudprinter integration (sticker kits / print API)
            $table->string('cloudprinter_order_id')->nullable()->after('printful_status');
            $table->string('cloudprinter_status')->nullable()->after('cloudprinter_order_id');
            $table->json('cloudprinter_payload')->nullable()->after('cloudprinter_status');

            $table->index('cloudprinter_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['cloudprinter_order_id']);
            $table->dropColumn(['cloudprinter_order_id', 'cloudprinter_status', 'cloudprinter_payload']);
        });
    }
};


