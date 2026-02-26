<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Email Delivery Test</title>
</head>
<body style="margin:0;padding:0;background:#0b1220;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#111a2e;border-radius:16px;overflow:hidden;border:1px solid rgba(255,255,255,0.10);">
                    <tr>
                        <td style="padding:22px 24px;color:#ffffff;font-size:18px;font-weight:bold;">
                            Shared Value Vending — Test Email
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px 22px 24px;color:rgba(255,255,255,0.85);font-size:14px;line-height:1.6;">
                            This is a test email from the platform to confirm deliverability and inbox placement.
                            <br><br>
                            Sent at: {{ now()->toDateTimeString() }}
                            <br>
                            App URL: {{ config('app.url') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px 24px 24px;">
                            <a href="{{ config('app.url') }}"
                               style="display:inline-block;background:#2f81f7;color:#ffffff;text-decoration:none;padding:10px 14px;border-radius:10px;font-size:14px;">
                                Open Dashboard
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 24px;color:rgba(255,255,255,0.60);font-size:12px;line-height:1.5;border-top:1px solid rgba(255,255,255,0.08);">
                            If you received this in Spam/Junk, please mark it as “Not spam” so future messages reach the inbox.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>


