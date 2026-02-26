<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySendGridEventWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $publicKeyB64 = (string) config('services.sendgrid.event_webhook_public_key', '');
        $signatureB64 = (string) $request->header('X-Twilio-Email-Event-Webhook-Signature', '');
        $timestamp = (string) $request->header('X-Twilio-Email-Event-Webhook-Timestamp', '');

        if ($publicKeyB64 === '' || $signatureB64 === '' || $timestamp === '') {
            return response('Missing SendGrid webhook signature', 403);
        }

        // Basic replay protection: reject if timestamp is too far from now.
        // SendGrid uses seconds since epoch.
        if (!ctype_digit($timestamp)) {
            return response('Invalid SendGrid webhook timestamp', 403);
        }
        $ts = (int) $timestamp;
        $skew = abs(time() - $ts);
        if ($skew > 300) { // 5 minutes
            return response('SendGrid webhook timestamp out of range', 403);
        }

        if (!function_exists('openssl_verify')) {
            return response('Webhook verification unavailable', 500);
        }

        // SendGrid provides a base64 encoded ECDSA public key (SubjectPublicKeyInfo DER).
        // Convert DER -> PEM for OpenSSL.
        $publicKeyDer = base64_decode($publicKeyB64, true);
        $signature = base64_decode($signatureB64, true);
        if ($publicKeyDer === false || $signature === false) {
            return response('Invalid SendGrid webhook key/signature encoding', 403);
        }

        $publicKeyPem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($publicKeyDer), 64, "\n")
            . "-----END PUBLIC KEY-----\n";

        $publicKey = openssl_pkey_get_public($publicKeyPem);
        if ($publicKey === false) {
            return response('Invalid SendGrid webhook public key', 403);
        }

        // SendGrid signature verification uses:
        // - data: timestamp + raw request body
        // - algorithm: ECDSA with SHA-256
        $payload = $timestamp . $request->getContent();

        $ok = openssl_verify($payload, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) {
            return response('Invalid SendGrid webhook signature', 403);
        }

        return $next($request);
    }
}

