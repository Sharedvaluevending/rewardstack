<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HelpController extends Controller
{
    public function faq(Request $request)
    {
        $business = $request->user()->business;

        return Inertia::render('Business/Help/FAQ', [
            'business' => $business?->only(['id', 'name']),
            'lastUpdated' => now()->format('M d, Y'),
        ]);
    }
}


