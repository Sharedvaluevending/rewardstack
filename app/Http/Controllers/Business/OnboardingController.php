<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Services\OnboardingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OnboardingController extends Controller
{
    protected OnboardingService $onboardingService;

    public function __construct(OnboardingService $onboardingService)
    {
        $this->onboardingService = $onboardingService;
    }

    /**
     * Mark an onboarding step as complete
     */
    public function completeStep(Request $request): JsonResponse
    {
        $request->validate([
            'step_id' => 'required|string',
        ]);

        $business = $request->user()->business;
        
        if (!$business) {
            return response()->json(['error' => 'Business not found'], 404);
        }

        $business->completeOnboardingStep($request->step_id);

        $progress = $business->getOnboardingProgress();

        return response()->json([
            'success' => true,
            'progress' => $progress,
            'completed' => $business->hasCompletedOnboarding(),
        ]);
    }

    /**
     * Dismiss the onboarding checklist
     */
    public function dismiss(Request $request): JsonResponse
    {
        $business = $request->user()->business;
        
        if (!$business) {
            return response()->json(['error' => 'Business not found'], 404);
        }

        $business->update(['onboarding_dismissed' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Onboarding checklist dismissed',
        ]);
    }

    /**
     * Reopen the dismissed onboarding checklist
     */
    public function reopen(Request $request): JsonResponse
    {
        $business = $request->user()->business;
        
        if (!$business) {
            return response()->json(['error' => 'Business not found'], 404);
        }

        $business->update(['onboarding_dismissed' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Onboarding checklist reopened',
        ]);
    }

    /**
     * Get current onboarding progress
     */
    public function progress(Request $request): JsonResponse
    {
        $business = $request->user()->business;
        
        if (!$business) {
            return response()->json(['error' => 'Business not found'], 404);
        }

        $steps = $this->onboardingService->getStepsForBusiness($business);
        $progress = $business->getOnboardingProgress();

        return response()->json([
            'steps' => $steps,
            'progress' => $progress,
            'show_onboarding' => $business->shouldShowOnboarding(),
            'completed' => $business->hasCompletedOnboarding(),
        ]);
    }
}
