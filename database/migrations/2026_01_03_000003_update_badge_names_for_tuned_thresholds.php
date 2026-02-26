<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Keep badge labels consistent with tuned requirements (existing installs).
        DB::table('badges')->where('slug', 'first-play')->update([
            'name' => 'Play 3 Games',
            'description' => 'Play 3 games',
            'updated_at' => now(),
        ]);

        DB::table('badges')->where('slug', 'first-win')->update([
            'name' => 'Win 3 Games',
            'description' => 'Win 3 games',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('badges')->where('slug', 'first-play')->update([
            'name' => 'First Play',
            'description' => 'Play your first game',
            'updated_at' => now(),
        ]);

        DB::table('badges')->where('slug', 'first-win')->update([
            'name' => 'First Win',
            'description' => 'Win your first game',
            'updated_at' => now(),
        ]);
    }
};


