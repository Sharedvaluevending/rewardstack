<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_segments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('name', 120);
            $table->json('definition')->nullable(); // filter JSON
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['business_id', 'is_active']);

            $table
                ->foreign('business_id')
                ->references('id')
                ->on('businesses')
                ->onDelete('cascade');
        });

        Schema::create('crm_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('segment_id')->nullable();

            $table->string('name', 140);
            $table->string('subject', 200);
            $table->string('preheader', 200)->nullable();

            // Content
            $table->longText('content_html');
            $table->longText('content_text')->nullable();

            // Sender identity (platform-from by default; stored for audit/future per-business verification)
            $table->string('from_name', 120)->nullable();
            $table->string('from_email', 255)->nullable();
            $table->string('reply_to', 255)->nullable();

            // State
            $table->string('status', 30)->default('draft'); // draft|scheduled|sending|sent|cancelled
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Metrics (denormalized for quick dashboards)
            $table->unsignedInteger('recipients_total')->default(0);
            $table->unsignedInteger('sent_total')->default(0);
            $table->unsignedInteger('delivered_total')->default(0);
            $table->unsignedInteger('open_total')->default(0);
            $table->unsignedInteger('click_total')->default(0);
            $table->unsignedInteger('bounce_total')->default(0);
            $table->unsignedInteger('spam_total')->default(0);
            $table->unsignedInteger('unsubscribe_total')->default(0);
            $table->unsignedInteger('failed_total')->default(0);

            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'scheduled_at']);

            $table
                ->foreign('business_id')
                ->references('id')
                ->on('businesses')
                ->onDelete('cascade');

            $table
                ->foreign('segment_id')
                ->references('id')
                ->on('crm_segments')
                ->nullOnDelete();
        });

        Schema::create('crm_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('user_id');

            $table->string('email', 255);
            $table->string('status', 30)->default('queued'); // queued|sent|delivered|opened|clicked|bounced|spam|unsubscribed|failed

            // SendGrid identifiers
            $table->string('sendgrid_message_id', 255)->nullable();
            $table->timestamp('last_event_at')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();

            $table->text('last_error')->nullable();
            $table->json('custom_args')->nullable();

            $table->timestamps();

            $table->unique(['campaign_id', 'user_id'], 'crm_messages_campaign_user_unique');
            $table->index(['business_id', 'status']);
            $table->index(['campaign_id', 'status']);

            $table
                ->foreign('business_id')
                ->references('id')
                ->on('businesses')
                ->onDelete('cascade');

            $table
                ->foreign('campaign_id')
                ->references('id')
                ->on('crm_campaigns')
                ->onDelete('cascade');

            $table
                ->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        Schema::create('crm_message_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->nullable();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->unsignedBigInteger('crm_message_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();

            $table->string('email', 255)->nullable();
            $table->string('event', 50);
            $table->timestamp('event_at')->nullable();

            $table->string('sg_event_id', 255)->nullable();
            $table->string('sg_message_id', 255)->nullable();

            $table->string('url', 2048)->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->json('payload')->nullable();

            $table->timestamps();

            $table->unique(['sg_event_id'], 'crm_message_events_sg_event_unique');
            $table->index(['business_id', 'event']);
            $table->index(['campaign_id', 'event']);

            $table
                ->foreign('crm_message_id')
                ->references('id')
                ->on('crm_messages')
                ->nullOnDelete();
        });

        Schema::create('crm_automations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('name', 140);
            $table->string('trigger', 80); // subscribed|post_redeem|winback|etc.
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['business_id', 'is_active']);

            $table
                ->foreign('business_id')
                ->references('id')
                ->on('businesses')
                ->onDelete('cascade');
        });

        Schema::create('crm_ai_recommendations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('type', 80); // campaign_idea|segment_idea|timing|etc.
            $table->json('payload')->nullable();
            $table->string('status', 30)->default('new'); // new|applied|dismissed
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table
                ->foreign('business_id')
                ->references('id')
                ->on('businesses')
                ->onDelete('cascade');
        });

        Schema::create('email_unsubscribes', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('business_id')->nullable(); // null = global suppression
            $table->string('reason', 50); // user_unsubscribe|bounce|spamreport|blocked
            $table->string('source', 50)->nullable(); // sendgrid|portal|admin
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'business_id']);
            $table->index(['business_id', 'reason']);

            $table
                ->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table
                ->foreign('business_id')
                ->references('id')
                ->on('businesses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_unsubscribes');
        Schema::dropIfExists('crm_ai_recommendations');
        Schema::dropIfExists('crm_automations');
        Schema::dropIfExists('crm_message_events');
        Schema::dropIfExists('crm_messages');
        Schema::dropIfExists('crm_campaigns');
        Schema::dropIfExists('crm_segments');
    }
};

