<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Question\Question;

class SetEnvSecret extends Command
{
    protected $signature = 'env:set-secret
        {key : The .env key to set (e.g. STRIPE_SECRET)}
        {--clear : Clear config cache after updating}';

    protected $description = 'Safely set a .env secret value via hidden prompt (no echo, no logging)';

    public function handle(): int
    {
        $key = strtoupper(trim((string) $this->argument('key')));

        if ($key === '') {
            $this->error('Key is required.');
            return self::FAILURE;
        }

        $question = new Question("Enter value for {$key} (input hidden): ");
        $question->setHidden(true);
        $question->setHiddenFallback(false);

        /** @var string|null $value */
        $value = $this->output->askQuestion($question);
        $value = $value === null ? '' : trim($value);

        if ($value === '') {
            $this->error('No value provided. Aborting.');
            return self::FAILURE;
        }

        $envPath = base_path('.env');
        if (! is_file($envPath) || ! is_readable($envPath) || ! is_writable($envPath)) {
            $this->error("Cannot read/write {$envPath}. Check permissions.");
            return self::FAILURE;
        }

        $env = file_get_contents($envPath);
        if ($env === false) {
            $this->error("Failed to read {$envPath}.");
            return self::FAILURE;
        }

        // Quote value if it contains spaces or special chars that commonly break .env parsing.
        $needsQuotes = preg_match('/\s|#|=|"|\'/', $value) === 1;
        $encodedValue = $needsQuotes ? '"' . str_replace('"', '\"', $value) . '"' : $value;

        $pattern = '/^' . preg_quote($key, '/') . '=.*/m';
        if (preg_match($pattern, $env) === 1) {
            $env = preg_replace($pattern, $key . '=' . $encodedValue, $env) ?? $env;
        } else {
            $env = rtrim($env) . PHP_EOL . $key . '=' . $encodedValue . PHP_EOL;
        }

        // Ensure trailing newline for POSIX friendliness
        if (! Str::endsWith($env, PHP_EOL)) {
            $env .= PHP_EOL;
        }

        $ok = file_put_contents($envPath, $env);
        if ($ok === false) {
            $this->error("Failed to write {$envPath}.");
            return self::FAILURE;
        }

        $this->info("Updated {$key} in .env (value not displayed).");

        if ($this->option('clear')) {
            $this->callSilent('config:clear');
            $this->info('Configuration cache cleared.');
        }

        return self::SUCCESS;
    }
}


