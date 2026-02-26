@php
  $promoUrl = url('/promo/' . $code);
  $signupUrl = url('/portal/join?redirect_to=' . urlencode('/promo/' . $code) . '&from=promo_email');
  $loginUrl = url('/login?redirect_to=' . urlencode('/promo/' . $code));
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your Promo</title>
</head>
<body style="margin:0;padding:0;background:#0b1220;font-family:Arial,Helvetica,sans-serif;color:#ffffff;">
    <div style="max-width:600px;margin:0 auto;padding:24px;">
        <div style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:16px;padding:22px;">
            <h2 style="margin:0 0 8px 0;font-size:20px;line-height:1.3;">
                {{ $businessName }} — {{ $promotionName }}
            </h2>
            <p style="margin:0 0 16px 0;color:rgba(255,255,255,0.75);font-size:14px;line-height:1.45;">
                Tap below to open your promo/QR again.
            </p>

            <div style="margin:18px 0;">
                <a href="{{ $promoUrl }}"
                   style="display:inline-block;background:#7c3aed;color:#fff;text-decoration:none;padding:12px 16px;border-radius:10px;font-weight:700;">
                    Open my promo
                </a>
            </div>

            <div style="margin-top:14px;">
                <p style="margin:0 0 10px 0;color:rgba(255,255,255,0.75);font-size:13px;line-height:1.45;">
                    Want to save this in your portal (Scans + Saved) so you never lose it?
                </p>
                <a href="{{ $signupUrl }}"
                   style="display:inline-block;background:rgba(16,185,129,0.18);border:1px solid rgba(16,185,129,0.35);color:#d1fae5;text-decoration:none;padding:10px 14px;border-radius:10px;font-weight:700;">
                    Create free account to save it
                </a>
                <span style="display:inline-block;width:10px;"></span>
                <a href="{{ $loginUrl }}"
                   style="display:inline-block;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.14);color:#fff;text-decoration:none;padding:10px 14px;border-radius:10px;font-weight:700;">
                    Log in
                </a>
            </div>

            <hr style="border:none;border-top:1px solid rgba(255,255,255,0.12);margin:18px 0;">

            <p style="margin:0;color:rgba(255,255,255,0.55);font-size:12px;line-height:1.45;">
                If a staff member asks, your promo code is: <strong style="letter-spacing:1px;">{{ $code }}</strong>
            </p>
        </div>
    </div>
</body>
</html>


