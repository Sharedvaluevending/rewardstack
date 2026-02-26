<?php

namespace App\Http\Controllers\Business\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmSegment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SegmentController extends Controller
{
    public function index(Request $request)
    {
        $business = $request->user()->business;

        $segments = CrmSegment::where('business_id', $business->id)
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Business/CRM/Segments', [
            'segments' => $segments->map(fn (CrmSegment $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'is_active' => (bool) $s->is_active,
                'definition' => $s->definition,
                'created_at' => $s->created_at?->format('M d, Y'),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $business = $request->user()->business;

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'definition' => 'nullable|array',
            'definition.filters' => 'nullable|array',
            'definition.filters.last_seen_days' => 'nullable|integer|min:1|max:365',
            'definition.filters.min_scans' => 'nullable|integer|min:0|max:100000',
            'definition.filters.min_redemptions' => 'nullable|integer|min:0|max:100000',
            'definition.filters.min_saved' => 'nullable|integer|min:0|max:100000',
            'definition.filters.min_level' => 'nullable|integer|min:1|max:50',
        ]);

        CrmSegment::create([
            'business_id' => $business->id,
            'name' => $validated['name'],
            'definition' => $validated['definition'] ?? ['filters' => []],
            'is_active' => true,
        ]);

        return redirect()->route('business.crm.segments')->with('success', 'Segment created.');
    }

    public function update(Request $request, CrmSegment $segment)
    {
        $business = $request->user()->business;
        abort_unless($segment->business_id === $business->id, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'definition' => 'nullable|array',
            'definition.filters' => 'nullable|array',
            'definition.filters.last_seen_days' => 'nullable|integer|min:1|max:365',
            'definition.filters.min_scans' => 'nullable|integer|min:0|max:100000',
            'definition.filters.min_redemptions' => 'nullable|integer|min:0|max:100000',
            'definition.filters.min_saved' => 'nullable|integer|min:0|max:100000',
            'definition.filters.min_level' => 'nullable|integer|min:1|max:50',
        ]);

        $segment->update([
            'name' => $validated['name'],
            'definition' => $validated['definition'] ?? ['filters' => []],
        ]);

        return redirect()->route('business.crm.segments')->with('success', 'Segment updated.');
    }

    public function destroy(Request $request, CrmSegment $segment)
    {
        $business = $request->user()->business;
        abort_unless($segment->business_id === $business->id, 403);

        // Null out segment_id on any campaigns via FK nullOnDelete; delete the segment.
        $segment->delete();

        return redirect()->route('business.crm.segments')->with('success', 'Segment deleted.');
    }
}

