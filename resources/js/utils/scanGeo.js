/**
 * Collect consent-based geolocation and update the latest scan row for this session + QR code.
 *
 * - Does NOT create a new scan row (ScanController already did that on /s/:code)
 * - Best-effort: silently fails if permission denied or network fails
 */
export async function collectAndSendScanGeo(qrCodeCode) {
    try {
        if (!qrCodeCode) return;
        if (typeof window === 'undefined') return;
        if (!('geolocation' in navigator)) return;

        // Avoid repeated prompts/calls for the same code in the same tab session
        const key = `scanGeoSent:${qrCodeCode}`;
        if (sessionStorage.getItem(key) === '1') return;

        const position = await new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(resolve, reject, {
                enableHighAccuracy: false,
                timeout: 4000,
                maximumAge: 10 * 60 * 1000,
            });
        });

        const lat = position?.coords?.latitude;
        const lng = position?.coords?.longitude;
        if (typeof lat !== 'number' || typeof lng !== 'number') return;

        // Reverse geocode (no key) for city/region/country
        let city, region, country;
        try {
            const url = new URL('https://api.bigdatacloud.net/data/reverse-geocode-client');
            url.searchParams.set('latitude', String(lat));
            url.searchParams.set('longitude', String(lng));
            url.searchParams.set('localityLanguage', 'en');

            const res = await fetch(url.toString(), { method: 'GET' });
            if (res.ok) {
                const data = await res.json();
                city = data?.city || data?.locality || data?.principalSubdivisionCode || undefined;
                region = data?.principalSubdivision || undefined;
                country = data?.countryName || undefined;
            }
        } catch {
            // ignore reverse-geocode failures
        }

        const updateUrl = new URL('/api/public/scan/geo', window.location.origin);
        updateUrl.searchParams.set('code', qrCodeCode);
        updateUrl.searchParams.set('lat', String(lat));
        updateUrl.searchParams.set('lng', String(lng));
        if (city) updateUrl.searchParams.set('city', city);
        if (region) updateUrl.searchParams.set('region', region);
        if (country) updateUrl.searchParams.set('country', country);

        await fetch(updateUrl.toString(), {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        sessionStorage.setItem(key, '1');
    } catch {
        // user denied permission / timeout / other failure -> ignore
    }
}


