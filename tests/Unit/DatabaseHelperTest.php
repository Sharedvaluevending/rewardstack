<?php

namespace Tests\Unit;

use App\Helpers\DatabaseHelper;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DatabaseHelperTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_with_retry_returns_callback_result_on_success(): void
    {
        $result = DatabaseHelper::transactionWithRetry(function () {
            return 42;
        });

        $this->assertSame(42, $result);
    }

    public function test_transaction_with_retry_rethrows_non_deadlock_exception(): void
    {
        $this->expectException(QueryException::class);

        DatabaseHelper::transactionWithRetry(function () {
            // Force a non-deadlock DB error (e.g. invalid SQL)
            DB::select('INVALID SQL SYNTAX HERE');
        });
    }

    public function test_transaction_with_retry_succeeds_after_deadlock_on_first_attempt(): void
    {
        $first = true;
        Log::shouldReceive('warning')->once()->withArgs(function ($msg, $context) {
            return $msg === 'Database deadlock detected, retrying'
                && isset($context['attempt'], $context['max_retries'], $context['delay_ms']);
        });
        Log::shouldReceive('error')->never();

        $result = DatabaseHelper::transactionWithRetry(function () use (&$first) {
            if ($first) {
                $first = false;
                $prev = new \Exception('Deadlock found when trying to get lock', 1213);
                throw new QueryException('mysql', 'select 1', [], $prev);
            }
            return 42;
        });

        $this->assertSame(42, $result);
    }

    public function test_transaction_with_retry_throws_after_exhausting_deadlock_retries(): void
    {
        Log::shouldReceive('warning')->times(2); // retries for attempt 1 and 2
        Log::shouldReceive('error')->once()->withArgs(function ($msg, $context) {
            return $msg === 'Database deadlock retry exhausted'
                && isset($context['attempts'], $context['error'], $context['code']);
        });

        $this->expectException(QueryException::class);

        DatabaseHelper::transactionWithRetry(function () {
            throw new QueryException('mysql', 'select 1', [], new \Exception('Deadlock found', 1213));
        }, 3, 1);
    }
}
