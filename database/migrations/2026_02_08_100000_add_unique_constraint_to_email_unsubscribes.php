<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove any duplicate rows before adding the unique constraint.
        // Keep the oldest record (lowest id) for each (email, business_id, reason) tuple.
        $duplicates = DB::table('email_unsubscribes as eu1')
            ->join('email_unsubscribes as eu2', function ($join) {
                $join->on('eu1.email', '=', 'eu2.email')
                    ->on(DB::raw('COALESCE(eu1.business_id, 0)'), '=', DB::raw('COALESCE(eu2.business_id, 0)'))
                    ->on('eu1.reason', '=', 'eu2.reason')
                    ->whereColumn('eu1.id', '>', 'eu2.id');
            })
            ->pluck('eu1.id');

        if ($duplicates->isNotEmpty()) {
            DB::table('email_unsubscribes')->whereIn('id', $duplicates)->delete();
        }

        Schema::table('email_unsubscribes', function (Blueprint $table) {
            $table->unique(['email', 'business_id', 'reason'], 'email_unsubs_email_business_reason_unique');
        });
    }

    public function down(): void
    {
        Schema::table('email_unsubscribes', function (Blueprint $table) {
            $table->dropUnique('email_unsubs_email_business_reason_unique');
        });
    }
};
