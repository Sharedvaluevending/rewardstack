<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_automation_sends', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('automation_id');
            $table->unsignedBigInteger('user_id');

            // Used to prevent duplicates for “per-item” automations (ex: promo_id, punchcard promotion_id).
            $table->string('dedupe_key', 120)->default('default');

            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedInteger('send_count')->default(0);

            $table->timestamps();

            $table->unique(['automation_id', 'user_id', 'dedupe_key'], 'crm_auto_sends_unique');
            $table->index(['business_id', 'last_sent_at'], 'crm_auto_sends_business_last_sent_index');

            $table
                ->foreign('business_id')
                ->references('id')
                ->on('businesses')
                ->onDelete('cascade');

            $table
                ->foreign('automation_id')
                ->references('id')
                ->on('crm_automations')
                ->onDelete('cascade');

            $table
                ->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_automation_sends');
    }
};

