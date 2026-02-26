{{ $businessName }} — {{ $promotionName }}

Open your promo:
{{ url('/promo/' . $code) }}

Create a free account to save it (Scans + Saved):
{{ url('/portal/join?redirect_to=' . urlencode('/promo/' . $code) . '&from=promo_email') }}

Log in:
{{ url('/login?redirect_to=' . urlencode('/promo/' . $code)) }}

Promo code: {{ $code }}


