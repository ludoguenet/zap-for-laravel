<?php

namespace Zap\Services;

use Illuminate\Database\Eloquent\Model;
use Zap\Exceptions\InvalidScheduleException;

class ValidationService
{
    /**
     * Validate a schedule before creation.
     */
    public function validate(
        Model $schedulable,
        array $attributes,
        array $periods,
        array $rules = []
    ): void {
        $errors = [];

        // Basic validation
        $basicErrors = $this->validateBasicAttributes($attributes);
        if (! empty($basicErrors)) {
            $errors = array_merge($errors, $basicErrors);
        }

        // Period validation
        $periodErrors = $this->validatePeriods($periods);
        if (! empty($periodErrors)) {
            $errors = array_merge($errors, $periodErrors);
        }

        // Business rules validation
        $ruleErrors = $this->validateRules($rules, $schedulable, $attributes, $periods);
        if (! empty($ruleErrors)) {
            $errors = array_merge($errors, $ruleErrors);
        }

        if (! empty($errors)) {
            $message = $this->buildValidationErrorMessage($errors);
            throw (new InvalidScheduleException($message))->setErrors($errors);
        }
    }

    /**
     * Validate basic schedule attributes.
     */
    protected function validateBasicAttributes(array $attributes): array
    {
        $errors = [];

        // Start date is required
        if (empty($attributes['start_date'])) {
            $errors['start_date'] = 'A start date is required for the schedule';
        }

        // End date must be after start date if provided
        if (! empty($attributes['end_date']) && ! empty($attributes['start_date'])) {
            $startDate = \Carbon\Carbon::parse($attributes['start_date']);
            $endDate = \Carbon\Carbon::parse($attributes['end_date']);

            if ($endDate->lte($startDate)) {
                $errors['end_date'] = 'The end date must be after the start date';
            }
        }

        // Check date range limits
        if (! empty($attributes['start_date']) && ! empty($attributes['end_date'])) {
            $startDate = \Carbon\Carbon::parse($attributes['start_date']);
            $endDate = \Carbon\Carbon::parse($attributes['end_date']);
            $maxRange = config('zap.validation.max_date_range', 365);

            if ($endDate->diffInDays($startDate) > $maxRange) {
                $errors['end_date'] = "The schedule duration cannot exceed {$maxRange} days";
            }
        }

        // Require future dates if configured
        if (config('zap.validation.require_future_dates', true)) {
            if (! empty($attributes['start_date'])) {
                $startDate = \Carbon\Carbon::parse($attributes['start_date']);
                if ($startDate->lt(now()->startOfDay())) {
                    $errors['start_date'] = 'The schedule cannot be created in the past. Please choose a future date';
                }
            }
        }

        return $errors;
    }

    /**
     * Validate schedule periods.
     */
    protected function validatePeriods(array $periods): array
    {
        $errors = [];

        if (empty($periods)) {
            $errors['periods'] = 'At least one time period must be defined for the schedule';

            return $errors;
        }

        $maxPeriods = config('zap.validation.max_periods_per_schedule', 50);
        if (count($periods) > $maxPeriods) {
            $errors['periods'] = "Too many time periods. A schedule cannot have more than {$maxPeriods} periods";
        }

        foreach ($periods as $index => $period) {
            $periodErrors = $this->validateSinglePeriod($period, $index);
            if (! empty($periodErrors)) {
                $errors = array_merge($errors, $periodErrors);
            }
        }

        // Check for overlapping periods if not allowed
        if (! config('zap.validation.allow_overlapping_periods', false)) {
            $overlapErrors = $this->checkPeriodOverlaps($periods);
            if (! empty($overlapErrors)) {
                $errors = array_merge($errors, $overlapErrors);
            }
        }

        return $errors;
    }

    /**
     * Validate a single period.
     */
    protected function validateSinglePeriod(array $period, int $index): array
    {
        $errors = [];
        $prefix = "periods.{$index}";

        // Required fields
        if (empty($period['start_time'])) {
            $errors["{$prefix}.start_time"] = 'A start time is required for this period';
        }

        if (empty($period['end_time'])) {
            $errors["{$prefix}.end_time"] = 'An end time is required for this period';
        }

        // Time format validation
        if (! empty($period['start_time']) && ! $this->isValidTimeFormat($period['start_time'])) {
            $errors["{$prefix}.start_time"] = "Invalid start time format '{$period['start_time']}'. Please use HH:MM format (e.g., 09:30)";
        }

        if (! empty($period['end_time']) && ! $this->isValidTimeFormat($period['end_time'])) {
            $errors["{$prefix}.end_time"] = "Invalid end time format '{$period['end_time']}'. Please use HH:MM format (e.g., 17:30)";
        }

        // End time must be after start time
        if (! empty($period['start_time']) && ! empty($period['end_time'])) {
            $baseDate = '2024-01-01'; // Use a consistent base date for time parsing
            $start = \Carbon\Carbon::parse($baseDate.' '.$period['start_time']);
            $end = \Carbon\Carbon::parse($baseDate.' '.$period['end_time']);

            if ($end->lte($start)) {
                $errors["{$prefix}.end_time"] = "End time ({$period['end_time']}) must be after start time ({$period['start_time']})";
            }

            // Duration validation
            $duration = $start->diffInMinutes($end);
            $minDuration = config('zap.validation.min_period_duration', 15);

            $maxDuration = 1440;

            if (config('zap.default_rules.max_duration.enabled')) {
                $maxDuration = config('zap.default_rules.max_duration.minutes', 480);
            }

            if ($duration < $minDuration) {
                $errors["{$prefix}.duration"] = "Period is too short ({$duration} minutes). Minimum duration is {$minDuration} minutes";
            }

            if ($duration > $maxDuration) {
                $errors["{$prefix}.duration"] = "Period is too long ({$duration} minutes). Maximum duration is {$maxDuration} minutes";
            }
        }

        return $errors;
    }

    /**
     * Check for overlapping periods within the same schedule.
     */
    protected function checkPeriodOverlaps(array $periods): array
    {
        $errors = [];

        for ($i = 0; $i < count($periods); $i++) {
            for ($j = $i + 1; $j < count($periods); $j++) {
                $period1 = $periods[$i];
                $period2 = $periods[$j];

                // Only check periods on the same date
                $date1 = $period1['date'] ?? null;
                $date2 = $period2['date'] ?? null;

                // If both periods have dates and they're different, skip
                if ($date1 && $date2 && $date1 !== $date2) {
                    continue;
                }

                // If one has a date and the other doesn't, skip (they're on different days)
                if (($date1 && ! $date2) || (! $date1 && $date2)) {
                    continue;
                }

                if ($this->periodsOverlap($period1, $period2)) {
                    $time1 = "{$period1['start_time']}-{$period1['end_time']}";
                    $time2 = "{$period2['start_time']}-{$period2['end_time']}";
                    $errors["periods.{$i}.overlap"] = "Period {$i} ({$time1}) overlaps with period {$j} ({$time2})";
                }
            }
        }

        return $errors;
    }

    /**
     * Check if two periods overlap.
     */
    protected function periodsOverlap(array $period1, array $period2): bool
    {
        if (empty($period1['start_time']) || empty($period1['end_time']) ||
            empty($period2['start_time']) || empty($period2['end_time'])) {
            return false;
        }

        $baseDate = '2024-01-01'; // Use a consistent base date for time parsing
        $start1 = \Carbon\Carbon::parse($baseDate.' '.$period1['start_time']);
        $end1 = \Carbon\Carbon::parse($baseDate.' '.$period1['end_time']);
        $start2 = \Carbon\Carbon::parse($baseDate.' '.$period2['start_time']);
        $end2 = \Carbon\Carbon::parse($baseDate.' '.$period2['end_time']);

        return $start1 < $end2 && $end1 > $start2;
    }

    /**
     * Validate schedule rules.
     */
    protected function validateRules(array $rules, Model $schedulable, array $attributes, array $periods): array
    {
        $errors = [];

        // Validate explicitly provided rules (these are always enabled unless explicitly disabled)
        foreach ($rules as $ruleName => $ruleConfig) {
            if (! $this->isExplicitRuleEnabled($ruleName, $ruleConfig)) {
                continue;
            }

            $ruleErrors = $this->validateRule($ruleName, $ruleConfig, $schedulable, $attributes, $periods);

            if (! empty($ruleErrors)) {
                $errors = array_merge($errors, $ruleErrors);
            }
        }

        // Add enabled default rules that weren't explicitly provided
        $defaultRules = config('zap.default_rules', []);
        foreach ($defaultRules as $ruleName => $defaultConfig) {
            // Skip if rule was explicitly provided
            if (isset($rules[$ruleName])) {
                continue;
            }

            // Check if default rule is enabled
            if (! $this->isDefaultRuleEnabled($ruleName, $defaultConfig)) {
                continue;
            }

            $ruleErrors = $this->validateRule($ruleName, $defaultConfig, $schedulable, $attributes, $periods);
            if (! empty($ruleErrors)) {
                $errors = array_merge($errors, $ruleErrors);
            }
        }

        // Automatically add no_overlap rule for appointment and blocked schedules if enabled
        $scheduleType = $attributes['schedule_type'] ?? \Zap\Enums\ScheduleTypes::CUSTOM;
        $noOverlapConfig = config('zap.default_rules.no_overlap', []);
        $shouldApplyNoOverlap = $this->shouldApplyNoOverlapRule($scheduleType, $noOverlapConfig, $rules);

        if ($shouldApplyNoOverlap) {
            $ruleErrors = $this->validateNoOverlap($noOverlapConfig, $schedulable, $attributes, $periods);
            $errors = array_merge($errors, $ruleErrors);
        }

        return $errors;
    }

    /**
     * Validate a specific rule.
     */
    protected function validateRule(
        string $ruleName,
        $ruleConfig,
        Model $schedulable,
        array $attributes,
        array $periods
    ): array {
        switch ($ruleName) {
            case 'working_hours':
                return $this->validateWorkingHours($ruleConfig, $periods);

            case 'max_duration':
                return $this->validateMaxDuration($ruleConfig, $periods);

            case 'no_weekends':
                return $this->validateNoWeekends($ruleConfig, $attributes, $periods);

            case 'no_overlap':
                return $this->validateNoOverlap($ruleConfig, $schedulable, $attributes, $periods);

            default:
                return [];
        }
    }

    /**
     * Merge provided rules with default rules from configuration.
     */
    protected function mergeWithDefaultRules(array $rules): array
    {
        $defaultRules = config('zap.default_rules', []);

        // Start with default rules
        $mergedRules = $defaultRules;

        // Override with provided rules
        foreach ($rules as $ruleName => $ruleConfig) {
            $mergedRules[$ruleName] = $ruleConfig;
        }

        return $mergedRules;
    }

    /**
     * Check if an explicitly provided rule is enabled.
     */
    protected function isExplicitRuleEnabled(string $ruleName, $ruleConfig): bool
    {
        // If rule config is just a boolean, use it directly
        if (is_bool($ruleConfig)) {
            return $ruleConfig;
        }

        // If rule config is an array, check for 'enabled' key
        if (is_array($ruleConfig)) {
            // If the rule has explicit enabled/disabled setting, use it
            if (isset($ruleConfig['enabled'])) {
                return $ruleConfig['enabled'];
            }

            // If the rule config is empty (like from builder methods), check global config
            if (empty($ruleConfig)) {
                $defaultConfig = config("zap.default_rules.{$ruleName}", []);

                return $this->isDefaultRuleEnabled($ruleName, $defaultConfig);
            }

            // For explicit rules with config (like workingHoursOnly(), maxDuration()),
            // they should be enabled since they were explicitly requested
            return true;
        }

        // For other types (explicit rule config provided), consider the rule enabled
        return $ruleConfig !== null;
    }

    /**
     * Check if a default rule is enabled.
     */
    protected function isDefaultRuleEnabled(string $ruleName, $ruleConfig): bool
    {
        // If rule config is just a boolean, use it directly
        if (is_bool($ruleConfig)) {
            return $ruleConfig;
        }

        // If rule config is an array, check for 'enabled' key
        if (is_array($ruleConfig)) {
            return $ruleConfig['enabled'] ?? true;
        }

        // For other types, consider the rule enabled if config is provided
        return $ruleConfig !== null;
    }

    /**
     * Check if a rule is enabled (legacy method - kept for compatibility).
     */
    protected function isRuleEnabled(string $ruleName, $ruleConfig): bool
    {
        return $this->isExplicitRuleEnabled($ruleName, $ruleConfig);
    }

    /**
     * Determine if no_overlap rule should be applied.
     */
    protected function shouldApplyNoOverlapRule(\Zap\Enums\ScheduleTypes $scheduleType, array $noOverlapConfig, array $providedRules): bool
    {
        // If no_overlap rule was explicitly provided, don't auto-apply
        if (isset($providedRules['no_overlap'])) {
            return false;
        }

        // Check if no_overlap rule is enabled in config
        if (! ($noOverlapConfig['enabled'] ?? false)) {
            return false;
        }

        // Check if this schedule type should get the no_overlap rule
        $appliesTo = $noOverlapConfig['applies_to'] ?? [];

        return in_array($scheduleType, $appliesTo);
    }

    /**
     * Validate working hours rule.
     */
    protected function validateWorkingHours($config, array $periods): array
    {
        if (! is_array($config)) {
            return [];
        }

        // Support both old and new configuration key formats
        $startTime = $config['start_time'] ?? $config['start'] ?? null;
        $endTime = $config['end_time'] ?? $config['end'] ?? null;

        if (empty($startTime) || empty($endTime)) {
            return [];
        }

        $errors = [];
        $baseDate = '2024-01-01'; // Use a consistent base date for time parsing
        $workStart = \Carbon\Carbon::parse($baseDate.' '.$startTime);
        $workEnd = \Carbon\Carbon::parse($baseDate.' '.$endTime);

        foreach ($periods as $index => $period) {
            if (empty($period['start_time']) || empty($period['end_time'])) {
                continue;
            }

            $periodStart = \Carbon\Carbon::parse($baseDate.' '.$period['start_time']);
            $periodEnd = \Carbon\Carbon::parse($baseDate.' '.$period['end_time']);

            if ($periodStart->lt($workStart) || $periodEnd->gt($workEnd)) {
                $errors["periods.{$index}.working_hours"] =
                    "Period {$period['start_time']}-{$period['end_time']} is outside working hours ({$startTime}-{$endTime})";
            }
        }

        return $errors;
    }

    /**
     * Validate maximum duration rule.
     */
    protected function validateMaxDuration($config, array $periods): array
    {
        if (! is_array($config) || empty($config['minutes'])) {
            return [];
        }

        $errors = [];
        $maxMinutes = $config['minutes'];

        foreach ($periods as $index => $period) {
            if (empty($period['start_time']) || empty($period['end_time'])) {
                continue;
            }

            $baseDate = '2024-01-01'; // Use a consistent base date for time parsing
            $start = \Carbon\Carbon::parse($baseDate.' '.$period['start_time']);
            $end = \Carbon\Carbon::parse($baseDate.' '.$period['end_time']);
            $duration = $start->diffInMinutes($end);

            if ($duration > $maxMinutes) {
                $hours = round($duration / 60, 1);
                $maxHours = round($maxMinutes / 60, 1);
                $errors["periods.{$index}.max_duration"] =
                    "Period {$period['start_time']}-{$period['end_time']} is too long ({$hours} hours). Maximum allowed is {$maxHours} hours";
            }
        }

        return $errors;
    }

    /**
     * Validate no weekends rule.
     */
    protected function validateNoWeekends($config, array $attributes, array $periods): array
    {
        if (! is_array($config)) {
            return [];
        }

        $errors = [];
        $blockSaturday = $config['saturday'] ?? true;
        $blockSunday = $config['sunday'] ?? true;

        // Check start date
        if (! empty($attributes['start_date'])) {
            $startDate = \Carbon\Carbon::parse($attributes['start_date']);
            if (($blockSaturday && $startDate->isSaturday()) || ($blockSunday && $startDate->isSunday())) {
                $dayName = $startDate->format('l');
                $errors['start_date'] = "Schedule cannot start on {$dayName}. Weekend schedules are not allowed";
            }
        }

        // Check period dates
        foreach ($periods as $index => $period) {
            if (! empty($period['date'])) {
                $periodDate = \Carbon\Carbon::parse($period['date']);
                if (($blockSaturday && $periodDate->isSaturday()) || ($blockSunday && $periodDate->isSunday())) {
                    $dayName = $periodDate->format('l');
                    $errors["periods.{$index}.date"] = "Period cannot be scheduled on {$dayName}. Weekend periods are not allowed";
                }
            }
        }

        return $errors;
    }

    /**
     * Validate no overlap rule.
     */
    protected function validateNoOverlap($config, Model $schedulable, array $attributes, array $periods): array
    {
        // Check if global conflict detection is disabled
        if (! config('zap.conflict_detection.enabled', true)) {
            return [];
        }

        // Create a temporary schedule for conflict checking
        $tempSchedule = new \Zap\Models\Schedule([
            'schedulable_type' => get_class($schedulable),
            'schedulable_id' => $schedulable->getKey(),
            'start_date' => $attributes['start_date'],
            'end_date' => $attributes['end_date'] ?? null,
            'is_active' => true,
            'is_recurring' => $attributes['is_recurring'] ?? false,
            'frequency' => $attributes['frequency'] ?? null,
            'frequency_config' => $attributes['frequency_config'] ?? null,
            'schedule_type' => $attributes['schedule_type'] ?? \Zap\Enums\ScheduleTypes::CUSTOM,
        ]);

        // Create temporary periods
        $tempPeriods = collect();
        foreach ($periods as $period) {
            $tempPeriods->push(new \Zap\Models\SchedulePeriod([
                'date' => $period['date'] ?? $attributes['start_date'],
                'start_time' => $period['start_time'],
                'end_time' => $period['end_time'],
                'is_available' => $period['is_available'] ?? true,
                'metadata' => $period['metadata'] ?? null,
            ]));
        }
        $tempSchedule->setRelation('periods', $tempPeriods);

        // For custom schedules with noOverlap rule, check conflicts with all other schedules
        if ($tempSchedule->schedule_type->is(\Zap\Enums\ScheduleTypes::CUSTOM)) {
            $conflicts = $this->findCustomScheduleConflicts($tempSchedule);
        } else {
            // Use the conflict detection service for typed schedules
            $conflictService = app(\Zap\Services\ConflictDetectionService::class);
            $conflicts = $conflictService->findConflicts($tempSchedule);
        }

        if (! empty($conflicts)) {
            // Build a detailed conflict message
            $message = $this->buildConflictErrorMessage($tempSchedule, $conflicts);

            // Throw the appropriate exception type for conflicts
            throw (new \Zap\Exceptions\ScheduleConflictException($message))
                ->setConflictingSchedules($conflicts);
        }

        return [];
    }

    /**
     * Find conflicts for custom schedules with noOverlap rule.
     */
    protected function findCustomScheduleConflicts(\Zap\Models\Schedule $schedule): array
    {
        $conflicts = [];
        $bufferMinutes = config('zap.conflict_detection.buffer_minutes', 0);

        // Get all other active schedules for the same schedulable
        $otherSchedules = \Zap\Models\Schedule::where('schedulable_type', $schedule->schedulable_type)
            ->where('schedulable_id', $schedule->schedulable_id)
            ->where('id', '!=', $schedule->id)
            ->active()
            ->with('periods')
            ->get();

        $conflictService = app(\Zap\Services\ConflictDetectionService::class);

        foreach ($otherSchedules as $otherSchedule) {
            if ($conflictService->schedulesOverlap($schedule, $otherSchedule, $bufferMinutes)) {
                $conflicts[] = $otherSchedule;
            }
        }

        return $conflicts;
    }

    /**
     * Build a descriptive validation error message.
     */
    protected function buildValidationErrorMessage(array $errors): string
    {
        $errorCount = count($errors);
        $errorMessages = [];

        foreach ($errors as $field => $message) {
            $errorMessages[] = "• {$field}: {$message}";
        }

        $summary = $errorCount === 1
            ? 'Schedule validation failed with 1 error:'
            : "Schedule validation failed with {$errorCount} errors:";

        return $summary."\n".implode("\n", $errorMessages);
    }

    /**
     * Build a detailed conflict error message.
     */
    protected function buildConflictErrorMessage(\Zap\Models\Schedule $newSchedule, array $conflicts): string
    {
        $conflictCount = count($conflicts);
        $newScheduleName = $newSchedule->name ?? 'New schedule';

        if ($conflictCount === 1) {
            $conflict = $conflicts[0];
            $conflictName = $conflict->name ?? "Schedule #{$conflict->id}";

            $message = "Schedule conflict detected! '{$newScheduleName}' conflicts with existing schedule '{$conflictName}'.";

            // Add details about the conflict
            if ($conflict->is_recurring) {
                $frequency = ucfirst($conflict->frequency ?? 'recurring');
                $message .= " The conflicting schedule is a {$frequency} schedule";

                if ($conflict->frequency === 'weekly' && ! empty($conflict->frequency_config['days'])) {
                    $days = implode(', ', array_map('ucfirst', $conflict->frequency_config['days']));
                    $message .= " on {$days}";
                }

                $message .= '.';
            }

            return $message;
        } else {
            $message = "Multiple schedule conflicts detected! '{$newScheduleName}' conflicts with {$conflictCount} existing schedules:";

            foreach ($conflicts as $index => $conflict) {
                $conflictName = $conflict->name ?? "Schedule #{$conflict->id}";
                $message .= "\n• {$conflictName}";

                if ($conflict->is_recurring) {
                    $frequency = ucfirst($conflict->frequency ?? 'recurring');
                    $message .= " ({$frequency}";

                    if ($conflict->frequency === 'weekly' && ! empty($conflict->frequency_config['days'])) {
                        $days = implode(', ', array_map('ucfirst', $conflict->frequency_config['days']));
                        $message .= " - {$days}";
                    }

                    $message .= ')';
                }
            }

            return $message;
        }
    }

    /**
     * Check if a time string is in valid format.
     */
    protected function isValidTimeFormat(string $time): bool
    {
        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time) === 1;
    }
}
