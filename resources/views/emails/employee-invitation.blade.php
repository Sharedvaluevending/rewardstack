<x-mail::message>
@php
    $business = $invite->business;
    $logoUrl = $business?->logo_url;
@endphp

@if ($logoUrl)
<p style="text-align: center; margin: 0 0 16px;">
    <img src="{{ $logoUrl }}" alt="{{ $business?->name ?? config('app.name') }} logo" style="max-height: 48px; width: auto;">
</p>
@endif

# Hello!

You have been invited to join **{{ $invite->business->name }}** as a **{{ $invite->role }}**.

<x-mail::button :url="$inviteUrl">
Accept Invitation
</x-mail::button>

This invitation will expire in {{ $invite->expires_at->diffForHumans() }}.

If you did not expect this invitation, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
