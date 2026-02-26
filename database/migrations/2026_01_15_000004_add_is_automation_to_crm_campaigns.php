<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_campaigns', function (Blueprint $table) {
            $table->boolean('is_automation')->default(false)->after('segment_id');
            $table->index('is_automation');
        });
    }

    public function down(): void
    {
        Schema::table('crm_campaigns', function (Blueprint $table) {
            $table->dropIndex(['is_automation']);
            $table->dropColumn('is_automation');
        });
    }
};
