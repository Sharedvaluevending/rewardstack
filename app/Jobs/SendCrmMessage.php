<?php

namespace App\Jobs;

use App\Models\CrmCampaign;
use App\Models\CrmMessage;
use App\Services\CrmSendGridService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class SendCrmMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $crmMessageId)
    {
    }

    public $tries = 3;
    public $backoff = [30, 120, 300];

    public function handle(CrmSendGridService $sendGrid): void
    {
        $message = CrmMessage::with(['campaign.business', 'campaign.promotion', 'user'])->find($this->crmMessageId);
        if (!$message) {
            return;
        }

        $campaign = $message->campaign;
        if (!$campaign || $campaign->status === CrmCampaign::STATUS_CANCELLED) {
            return;
        }

        // Guard: do not re-send if already sent.
        if (in_array($message->status, ['sent', 'delivered', 'opened', 'clicked'], true) && $message->sent_at) {
            return;
        }

        // Signed unsubscribe link (no login required).
        $unsubscribeUrl = URL::temporarySignedRoute(
            'email.unsubscribe',
            now()->addDays(365),
            ['u' => $message->user_id, 'b' => $message->business_id]
        );

        try {
            $sgId = $sendGrid->sendMessage($campaign, $message, [
                'unsubscribe_url' => $unsubscribeUrl,
            ]);

            $message->status = 'sent';
            $message->sent_at = $message->sent_at ?: now();
            if ($sgId) {
                $message->sendgrid_message_id = $sgId;
            }
            $message->save();
        } catch (\Throwable $e) {
            $message->status = 'failed';
            $message->last_error = $e->getMessage();
            $message->save();

            // Re-throw so Laravel can retry based on $tries/$backoff.
            throw $e;
        }
    }
}

