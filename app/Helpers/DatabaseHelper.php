<?php

namespace App\Helpers;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseHelper
{
    /**
     * Execute a database transaction with automatic deadlock retry
     * 
     * @param callable $callback The transaction callback
     * @param int $maxRetries Maximum number of retry attempts (default: 3)
     * @param int $baseDelayMs Base delay in milliseconds before retry (default: 100)
     * @return mixed The result of the callback
     * @throws \Exception If max retries exceeded or non-deadlock error occurs
     */
    public static function transactionWithRetry(callable $callback, int $maxRetries = 3, int $baseDelayMs = 100)
    {
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            try {
                return DB::transaction($callback);
            } catch (QueryException $e) {
                $attempt++;
                
                // Check if this is a deadlock error
                // MySQL deadlock error code: 1213
                // SQLSTATE: 40001 (serialization failure)
                $isDeadlock = $e->getCode() == 1213 
                    || $e->getCode() == '40001'
                    || str_contains($e->getMessage(), 'Deadlock found')
                    || str_contains($e->getMessage(), 'try restarting transaction');
                
                if (!$isDeadlock) {
                    // Not a deadlock, re-throw immediately
                    throw $e;
                }
                
                // If we've exhausted retries, throw the exception
                if ($attempt >= $maxRetries) {
                    Log::error('Database deadlock retry exhausted', [
                        'attempts' => $attempt,
                        'error' => $e->getMessage(),
                        'code' => $e->getCode(),
                    ]);
                    throw $e;
                }
                
                // Exponential backoff: 100ms, 200ms, 400ms
                $delayMs = $baseDelayMs * (2 ** ($attempt - 1));
                usleep($delayMs * 1000); // Convert to microseconds
                
                Log::warning('Database deadlock detected, retrying', [
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'delay_ms' => $delayMs,
                ]);
            }
        }
        
        // Should never reach here, but just in case
        throw new \RuntimeException('Transaction retry logic failed unexpectedly');
    }
}

