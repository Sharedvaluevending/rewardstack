## Security headers

The app sets baseline security headers via middleware:

- File: `app/Http/Middleware/SecurityHeaders.php`
- Enabled globally in: `app/Http/Kernel.php`

### What is set
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(self), microphone=(), camera=()`
- `Content-Security-Policy: ...` (includes Stripe + Printful)

### HTTPS enforcement (production)
When `APP_ENV=production`, we also set:
- `Strict-Transport-Security: max-age=31536000; includeSubDomains`

### Notes
Any CSP change can break frontend scripts. Treat CSP edits as a **moderate-risk** change and test in staging first.
