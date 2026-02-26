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
        Schema::table('orders', function (Blueprint $table) {
            // Cloudprinter cost tracking (what Cloudprinter charges us)
            $table->decimal('cloudprinter_cost', 10, 2)->nullable()->after('cloudprinter_payload');
            $table->decimal('cloudprinter_product_cost', 10, 2)->nullable()->after('cloudprinter_cost');
            $table->decimal('cloudprinter_shipping_cost', 10, 2)->nullable()->after('cloudprinter_product_cost');
            
            // Margin calculation (what we make)
            $table->decimal('margin', 10, 2)->nullable()->after('cloudprinter_shipping_cost');
            $table->decimal('margin_percentage', 5, 2)->nullable()->after('margin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'cloudprinter_cost',
                'cloudprinter_product_cost',
                'cloudprinter_shipping_cost',
                'margin',
                'margin_percentage',
            ]);
        });
    }
};
