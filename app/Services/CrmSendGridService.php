<?php

namespace App\Services;

use App\Models\CrmCampaign;
use App\Models\CrmMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class CrmSendGridService
{
    public function isConfigured(): bool
    {
        return (string) config('services.sendgrid.api_key', '') !== '';
    }

    /**
     * Send a single CRM message via SendGrid v3 API.
     *
     * Returns the SendGrid message id when available.
     */
    public function sendMessage(CrmCampaign $campaign, CrmMessage $message, array $options = []): ?string
    {
        $apiKey = (string) config('services.sendgrid.api_key', '');
        if ($apiKey === '') {
            throw new \RuntimeException('SendGrid API key not configured');
        }

        $fromEmail = (string) ($campaign->from_email ?: config('mail.from.address'));
        $fromName = (string) ($campaign->from_name ?: config('app.name', 'Revenue QR'));
        $replyTo = $campaign->reply_to ? (string) $campaign->reply_to : null;

        $unsubscribeUrl = (string) ($options['unsubscribe_url'] ?? '');

        $htmlRaw = $this->replaceTokens($campaign->content_html, $campaign, $message);
        $textRaw = $campaign->content_text ? $this->replaceTokens($campaign->content_text, $campaign, $message) : $this->stripToText($htmlRaw);

        $html = $this->decorateHtml($htmlRaw, $campaign, $unsubscribeUrl);
        $text = $this->decorateText($textRaw, $campaign, $unsubscribeUrl);

        // SendGrid expects custom_args inside personalization (shows up in event webhook payload).
        $customArgs = array_merge([
            'crm_message_id' => (string) $message->id,
            'crm_campaign_id' => (string) $campaign->id,
            'business_id' => (string) $campaign->business_id,
            'user_id' => (string) $message->user_id,
        ], is_array($message->custom_args) ? $message->custom_args : []);
        if ($campaign->promotion_id) {
            $customArgs['promotion_id'] = (string) $campaign->promotion_id;
        }

        $payload = [
            'personalizations' => [[
                'to' => [[ 'email' => $message->email ]],
                'custom_args' => $customArgs,
            ]],
            'from' => [
                'email' => $fromEmail,
                'name' => $fromName,
            ],
            'subject' => $campaign->subject,
            'content' => array_values(array_filter([
                ['type' => 'text/plain', 'value' => $text],
                $html ? ['type' => 'text/html', 'value' => $html] : null,
            ])),
            'tracking_settings' => [
                'open_tracking' => ['enable' => true],
                'click_tracking' => ['enable' => true, 'enable_text' => true],
            ],
        ];

        if ($replyTo) {
            $payload['reply_to'] = ['email' => $replyTo];
        }

        $res = Http::withToken($apiKey)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.sendgrid.com/v3/mail/send', $payload);

        if (!$res->successful()) {
            Log::warning('SendGrid send failed', [
                'campaign_id' => $campaign->id,
                'crm_message_id' => $message->id,
                'status' => $res->status(),
                'body' => $res->body(),
            ]);
            throw new \RuntimeException('SendGrid send failed (HTTP ' . $res->status() . ')');
        }

        // SendGrid returns 202 with X-Message-Id header.
        return $res->header('X-Message-Id');
    }

    protected function replaceTokens(string $content, CrmCampaign $campaign, CrmMessage $message): string
    {
        $businessName = $campaign->business?->name ?: 'Business';
        $userName = $message->user?->name ?: '';
        $firstName = trim(explode(' ', trim($userName))[0] ?? '');

        $promoLink = null;
        if ($campaign->promotion_id && $message->promo_link_token) {
            $promoLink = URL::temporarySignedRoute(
                'crm.campaigns.promo.save',
                now()->addDays(3),
                [
                    'campaign' => $campaign->id,
                    'message' => $message->id,
                    'user' => $message->user_id,
                    'promo' => $campaign->promotion_id,
                    'business' => $campaign->business_id,
                    'token' => $message->promo_link_token,
                ]
            );
        }

        $vars = [
            'business_name' => $businessName,
            'name' => $userName,
            'first_name' => $firstName,
            'email' => $message->email,
            'promo_name' => $campaign->promotion?->name ?: '',
            'promo_link' => $promoLink ?: '',
        ];

        if (is_array($message->custom_args)) {
            foreach ($message->custom_args as $k => $v) {
                if (is_string($k)) {
                    $vars[$k] = is_scalar($v) ? (string) $v : json_encode($v);
                }
            }
        }

        // Replace {{key}} tokens
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_\-]+)\s*\}\}/', function ($m) use ($vars) {
            $key = $m[1];
            return array_key_exists($key, $vars) ? (string) $vars[$key] : $m[0];
        }, $content) ?? $content;
    }

    protected function decorateHtml(string $html, CrmCampaign $campaign, string $unsubscribeUrl): string
    {
        $footer = $this->htmlFooter($campaign, $unsubscribeUrl);
        if (stripos($html, '</body>') !== false) {
            return preg_replace('/<\/body>/i', $footer . '</body>', $html, 1) ?: ($html . $footer);
        }
        return $html . $footer;
    }

    protected function decorateText(string $text, CrmCampaign $campaign, string $unsubscribeUrl): string
    {
        $footer = $this->textFooter($campaign, $unsubscribeUrl);
        return rtrim($text) . "\n\n" . $footer;
    }

    protected function htmlFooter(CrmCampaign $campaign, string $unsubscribeUrl): string
    {
        $businessName = e($campaign->business?->name ?? 'Business');
        $unsub = $unsubscribeUrl ? '<a href="' . e($unsubscribeUrl) . '" style="color:#9ca3af;text-decoration:underline;">Unsubscribe</a>' : '';
        return <<<HTML
<div style="margin-top:24px;border-top:1px solid rgba(255,255,255,0.12);padding-top:14px;color:rgba(255,255,255,0.55);font-size:12px;line-height:1.45;">
  <div style="font-weight:700;color:rgba(255,255,255,0.75);">{$businessName}</div>
  <div style="margin-top:6px;">You received this email because you subscribed to {$businessName}.</div>
  <div style="margin-top:6px;">{$unsub}</div>
</div>
HTML;
    }

    protected function textFooter(CrmCampaign $campaign, string $unsubscribeUrl): string
    {
        $businessName = $campaign->business?->name ?? 'Business';
        $lines = [];
        $lines[] = $businessName;
        $lines[] = "You received this email because you subscribed to {$businessName}.";
        if ($unsubscribeUrl) {
            $lines[] = "Unsubscribe: {$unsubscribeUrl}";
        }
        return implode("\n", $lines);
    }

    protected function stripToText(string $html): string
    {
        $text = strip_tags($html);
        $text = preg_replace("/[ \\t]+/", ' ', $text) ?: $text;
        $text = preg_replace("/\\n{3,}/", "\n\n", $text) ?: $text;
        return trim($text);
    }
}

