<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AveryDpoService
{
    public function mergeColumn(): string
    {
        return (string) config('printkits.merge_column', 'qr_url');
    }

    public function bundleUrlForSize(string $sizeKey): string
    {
        $sizes = (array) config('printkits.sizes', []);
        $size = Arr::get($sizes, $sizeKey);
        $filename = is_array($size) ? ($size['bundle_filename'] ?? null) : null;

        if (!$filename) {
            throw new \InvalidArgumentException("Unknown sticker size: {$sizeKey}");
        }

        $base = rtrim((string) config('printkits.bundle_base_url', ''), '/');
        if ($base === '') {
            // Fallback to app URL at runtime if bundle_base_url isn't set.
            $base = rtrim((string) config('app.url', ''), '/') . '/avery/bundles';
        }

        return $base . '/' . ltrim((string) $filename, '/');
    }

    /**
     * Build a CSV payload for Avery merge with one or more columns.
     *
     * Avery accepts mergeDataFormat=csv and mergeData as a "data string".
     *
     * @param array<int, array<string, string>> $rows
     * @param array<int, string> $headers
     */
    public function buildCsv(array $rows, array $headers): string
    {
        $normalizedHeaders = array_values(array_filter(array_unique(array_map(
            fn ($h) => trim((string) $h),
            $headers
        ))));

        if (empty($normalizedHeaders)) {
            $normalizedHeaders = ['qr_url'];
        }

        $lines = [implode(',', array_map(fn ($h) => $this->csvCell($h), $normalizedHeaders))];

        foreach ($rows as $row) {
            $cells = [];
            foreach ($normalizedHeaders as $header) {
                $cells[] = $this->csvCell((string) ($row[$header] ?? ''));
            }
            $lines[] = implode(',', $cells);
        }

        // Use \n; Avery examples don't require CRLF.
        return implode("\n", $lines);
    }

    /**
     * Backward-compatible helper for legacy single-column callers.
     *
     * @param array<int, string> $values
     */
    public function buildSingleColumnCsv(string $columnHeader, array $values): string
    {
        $rows = array_map(fn ($val) => [$columnHeader => (string) $val], $values);
        return $this->buildCsv($rows, [$columnHeader]);
    }

    public function directMergeActionUrl(): string
    {
        $base = rtrim((string) config('avery.merge_direct_url'), '/');
        $consumer = (string) config('avery.consumer');
        $deploymentId = (string) config('avery.deployment_id', 'US_en');
        $profile = (string) config('avery.profile', '');

        $query = http_build_query(array_filter([
            'deploymentId' => $deploymentId,
            'consumer' => $consumer,
            'profile' => $profile !== '' ? $profile : null,
            // backUrl is optional; include only if set
            'backUrl' => config('avery.back_url') ?: null,
        ], fn ($v) => $v !== null && $v !== ''));

        // Avery docs show parameters on query string for the action URL.
        return $base . ($query ? ('?' . $query) : '');
    }

    /**
     * @param array<string, string> $fields
     */
    public function buildAutoPostHtml(string $actionUrl, array $fields): string
    {
        $inputs = '';
        foreach ($fields as $name => $value) {
            $inputs .= sprintf(
                "<input type=\"hidden\" name=\"%s\" value=\"%s\" />\n",
                e($name),
                e($value),
            );
        }

        $title = 'Opening Avery Design & Print…';

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>{$title}</title>
  <meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; form-action {$this->formActionOrigin($actionUrl)}; base-uri 'none'; frame-ancestors 'none';" />
  <style>
    body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji","Segoe UI Emoji"; background:#0b1220; color:#e5e7eb; margin:0; }
    .wrap { max-width: 720px; margin: 64px auto; padding: 24px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); border-radius: 16px; }
    .muted { color:#9ca3af; font-size: 14px; }
    .btn { display:inline-block; margin-top:16px; background:rgba(255,255,255,0.1); color:#fff; padding:10px 14px; border-radius: 10px; text-decoration:none; border:1px solid rgba(255,255,255,0.15); }
  </style>
</head>
<body>
  <div class="wrap">
    <h1 style="margin:0 0 8px 0; font-size:20px;">Opening Avery Design &amp; Print…</h1>
    <div class="muted">If you are not redirected automatically, click the button below.</div>
    <form id="averyForm" action="{$actionUrl}" method="POST" accept-charset="UTF-8">
{$inputs}      <button type="submit" class="btn">Continue to Avery</button>
      <button type="submit" class="btn" formtarget="_blank" style="margin-left:8px;">Open in New Tab</button>
    </form>
    <div class="muted" style="margin-top:12px;">This order is already paid. You can retry this step anytime from your Print Kit orders.</div>
  </div>
  <script>
    // Attempt auto-submit once; manual buttons remain as fallback.
    setTimeout(function () {
      document.getElementById('averyForm').submit();
    }, 50);
  </script>
</body>
</html>
HTML;
    }

    protected function csvCell(string $value): string
    {
        // Minimal CSV escaping. Wrap in quotes if needed.
        $needsQuotes = str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n") || str_contains($value, "\r");
        $escaped = str_replace('"', '""', $value);
        return $needsQuotes ? "\"{$escaped}\"" : $escaped;
    }

    protected function formActionOrigin(string $actionUrl): string
    {
        $parts = parse_url($actionUrl);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return 'https://services.print.avery.com';
        }
        $port = isset($parts['port']) ? (':' . $parts['port']) : '';
        return $parts['scheme'] . '://' . $parts['host'] . $port;
    }
}

