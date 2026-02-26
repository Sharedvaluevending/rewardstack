<?php

namespace App\Services;

use App\Models\CrossPromotion;
use App\Models\Promotion;

class CrossPromoRulesService
{
    /**
     * Detect conflicts between two promotions' rules
     */
    public function detectConflicts(Promotion $promo1, Promotion $promo2): array
    {
        return CrossPromotion::detectRulesConflicts($promo1, $promo2);
    }
    
    /**
     * Validate cross-promo rules structure
     */
    public function validateRules(array $rules): array
    {
        $errors = [];
        
        // Validate valid_days if present
        if (isset($rules['valid_days']) && is_array($rules['valid_days'])) {
            $validDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            foreach ($rules['valid_days'] as $day) {
                if (!in_array(strtolower($day), $validDays)) {
                    $errors[] = "Invalid day: {$day}";
                }
            }
        }
        
        // Validate valid_hours if present
        if (isset($rules['valid_hours']) && is_array($rules['valid_hours'])) {
            $start = $rules['valid_hours']['start'] ?? null;
            $end = $rules['valid_hours']['end'] ?? null;
            
            if ($start && !preg_match('/^\d{2}:\d{2}$/', $start)) {
                $errors[] = "Invalid time format for start: {$start}. Use HH:MM format.";
            }
            if ($end && !preg_match('/^\d{2}:\d{2}$/', $end)) {
                $errors[] = "Invalid time format for end: {$end}. Use HH:MM format.";
            }
        }
        
        // Validate limits
        if (isset($rules['max_redemptions_per_user']) && $rules['max_redemptions_per_user'] < 0) {
            $errors[] = "max_redemptions_per_user must be 0 or greater";
        }
        
        if (isset($rules['max_per_day']) && $rules['max_per_day'] < 0) {
            $errors[] = "max_per_day must be 0 or greater";
        }
        
        return $errors;
    }
    
    /**
     * Merge rules from two promotions (for agreement)
     */
    public function mergeRules(Promotion $promo1, Promotion $promo2): array
    {
        $rules1 = $promo1->rules ?? [];
        $rules2 = $promo2->rules ?? [];
        
        $merged = [];
        
        // Merge valid_days (intersection)
        $days1 = $rules1['valid_days'] ?? [];
        $days2 = $rules2['valid_days'] ?? [];
        if (!empty($days1) && !empty($days2)) {
            $merged['valid_days'] = array_values(array_intersect($days1, $days2));
        } elseif (!empty($days1)) {
            $merged['valid_days'] = $days1;
        } elseif (!empty($days2)) {
            $merged['valid_days'] = $days2;
        }
        
        // Merge valid_hours (overlap or use broader window)
        $hours1 = $rules1['valid_hours'] ?? null;
        $hours2 = $rules2['valid_hours'] ?? null;
        if ($hours1 && $hours2) {
            // Use the broader time window
            $start1 = $hours1['start'] ?? '00:00';
            $end1 = $hours1['end'] ?? '23:59';
            $start2 = $hours2['start'] ?? '00:00';
            $end2 = $hours2['end'] ?? '23:59';
            
            $merged['valid_hours'] = [
                'start' => min($start1, $start2),
                'end' => max($end1, $end2),
            ];
        } elseif ($hours1) {
            $merged['valid_hours'] = $hours1;
        } elseif ($hours2) {
            $merged['valid_hours'] = $hours2;
        }
        
        // Use the more restrictive limit
        $limit1 = $rules1['max_redemptions_per_user'] ?? null;
        $limit2 = $rules2['max_redemptions_per_user'] ?? null;
        if ($limit1 && $limit2) {
            $merged['max_redemptions_per_user'] = min($limit1, $limit2);
        } elseif ($limit1) {
            $merged['max_redemptions_per_user'] = $limit1;
        } elseif ($limit2) {
            $merged['max_redemptions_per_user'] = $limit2;
        }
        
        return $merged;
    }
    
    /**
     * Format rules for display
     */
    public function formatRulesForDisplay(array $rules): array
    {
        $formatted = [];
        
        if (isset($rules['valid_days']) && is_array($rules['valid_days'])) {
            $formatted['valid_days'] = array_map('ucfirst', $rules['valid_days']);
        }
        
        if (isset($rules['valid_hours']) && is_array($rules['valid_hours'])) {
            $start = $rules['valid_hours']['start'] ?? null;
            $end = $rules['valid_hours']['end'] ?? null;
            if ($start && $end) {
                try {
                    $startTime = \Carbon\Carbon::createFromFormat('H:i', $start)->format('g:i A');
                    $endTime = \Carbon\Carbon::createFromFormat('H:i', $end)->format('g:i A');
                    $formatted['valid_hours'] = "{$startTime} - {$endTime}";
                } catch (\Exception $e) {
                    $formatted['valid_hours'] = "{$start} - {$end}";
                }
            }
        }
        
        if (isset($rules['max_redemptions_per_user'])) {
            $formatted['max_redemptions_per_user'] = $rules['max_redemptions_per_user'] === 0 
                ? 'Unlimited' 
                : $rules['max_redemptions_per_user'];
        }
        
        if (isset($rules['max_per_day'])) {
            $formatted['max_per_day'] = $rules['max_per_day'] === 0 
                ? 'Unlimited' 
                : $rules['max_per_day'];
        }
        
        return $formatted;
    }
}
