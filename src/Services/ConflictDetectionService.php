<?php

namespace Zap\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Zap\Models\Schedule;
use Zap\Models\SchedulePeriod;

class ConflictDetectionService
{
    /**
     * Check if a schedule has conflicts with existing schedules.
     */
    public function hasConflicts(Schedule $schedule): bool
    {
        return ! empty($this->findConflicts($schedule));
    }

    /**
     * Find all schedules that conflict with the given schedule.
     */
    public function findConflicts(Schedule $schedule): array
    {
        if (! config('zap.conflict_detection.enabled', true)) {
            return [];
        }

        $conflicts = [];
        $bufferMinutes = config('zap.conflict_detection.buffer_minutes', 0);

        // Get all other active schedules for the same schedulable
        $otherSchedules = $this->getOtherSchedules($schedule);

        foreach ($otherSchedules as $otherSchedule) {
            // Check conflicts based on schedule types and rules
            $shouldCheckConflict = $this->shouldCheckConflict($schedule, $otherSchedule);

            if ($shouldCheckConflict && $this->schedulesOverlap($schedule, $otherSchedule, $bufferMinutes)) {
                $conflicts[] = $otherSchedule;
            }
        }

        return $conflicts;
    }

    /**
     * Determine if two schedules should be checked for conflicts.
     */
    protected function shouldCheckConflict(Schedule $schedule1, Schedule $schedule2): bool
    {
        // If either schedule allows overlaps (availability), no conflict
        if ($schedule1->allowsOverlaps() || $schedule2->allowsOverlaps()) {
            return false;
        }

        // If either schedule prevents overlaps (appointment/blocked), check conflict
        if ($schedule1->preventsOverlaps() || $schedule2->preventsOverlaps()) {
            return true;
        }

        // For custom schedules, we need to check if they have the noOverlap rule
        // This is handled at the validation level, not here
        return false;
    }

    /**
     * Check if a schedulable has conflicts with a given schedule.
     */
    public function hasSchedulableConflicts(Model $schedulable, Schedule $schedule): bool
    {
        $conflicts = $this->findSchedulableConflicts($schedulable, $schedule);

        return ! empty($conflicts);
    }

    /**
     * Find conflicts for a schedulable with a given schedule.
     */
    public function findSchedulableConflicts(Model $schedulable, Schedule $schedule): array
    {
        // Create a temporary schedule for conflict checking
        $tempSchedule = new Schedule([
            'schedulable_type' => get_class($schedulable),
            'schedulable_id' => $schedulable->getKey(),
            'start_date' => $schedule->start_date,
            'end_date' => $schedule->end_date,
            'is_active' => true,
        ]);

        // Copy periods if they exist
        if ($schedule->relationLoaded('periods')) {
            $tempSchedule->setRelation('periods', $schedule->periods);
        }

        return $this->findConflicts($tempSchedule);
    }

    /**
     * Check if two schedules overlap.
     */
    public function schedulesOverlap(
        Schedule $schedule1,
        Schedule $schedule2,
        int $bufferMinutes = 0
    ): bool {
        // First check date range overlap
        if (! $this->dateRangesOverlap($schedule1, $schedule2)) {
            return false;
        }

        // Then check period-level conflicts
        return $this->periodsOverlap($schedule1, $schedule2, $bufferMinutes);
    }

    /**
     * Check if two schedules have overlapping date ranges.
     */
    protected function dateRangesOverlap(Schedule $schedule1, Schedule $schedule2): bool
    {
        $start1 = $schedule1->start_date;
        $end1 = $schedule1->end_date ?? \Carbon\Carbon::parse('2099-12-31');
        $start2 = $schedule2->start_date;
        $end2 = $schedule2->end_date ?? \Carbon\Carbon::parse('2099-12-31');

        return $start1 <= $end2 && $end1 >= $start2;
    }

    /**
     * Check if periods from two schedules overlap.
     */
    protected function periodsOverlap(
        Schedule $schedule1,
        Schedule $schedule2,
        int $bufferMinutes = 0
    ): bool {
        $periods1 = $this->getSchedulePeriods($schedule1);
        $periods2 = $this->getSchedulePeriods($schedule2);

        foreach ($periods1 as $period1) {
            foreach ($periods2 as $period2) {
                if ($this->periodPairOverlaps($period1, $period2, $bufferMinutes)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if two specific periods overlap.
     */
    protected function periodPairOverlaps(
        SchedulePeriod $period1,
        SchedulePeriod $period2,
        int $bufferMinutes = 0
    ): bool {
        // Must be on the same date
        if (! $period1->date->eq($period2->date)) {
            return false;
        }

        $start1 = $this->parseTime($period1->start_time);
        $end1 = $this->parseTime($period1->end_time);
        $start2 = $this->parseTime($period2->start_time);
        $end2 = $this->parseTime($period2->end_time);

        // Apply buffer
        if ($bufferMinutes > 0) {
            $start1->subMinutes($bufferMinutes);
            $end1->addMinutes($bufferMinutes);
        }

        return $start1 < $end2 && $end1 > $start2;
    }

    /**
     * Get periods for a schedule, handling recurring schedules.
     */
    protected function getSchedulePeriods(Schedule $schedule): Collection
    {
        $periods = $schedule->relationLoaded('periods')
            ? $schedule->periods
            : $schedule->periods()->get();

        // If this is a recurring schedule, we need to generate recurring instances
        if ($schedule->is_recurring) {
            return $this->generateRecurringPeriods($schedule, $periods);
        }

        return $periods;
    }

    /**
     * Generate recurring periods for a recurring schedule within a reasonable range.
     */
    protected function generateRecurringPeriods(Schedule $schedule, Collection $basePeriods): Collection
    {
        if (! $schedule->is_recurring || $basePeriods->isEmpty()) {
            return $basePeriods;
        }

        $allPeriods = collect();

        // Generate recurring instances for the next year to cover reasonable conflicts
        $startDate = $schedule->start_date;
        $endDate = $schedule->end_date ?? $startDate->copy()->addYear();

        // Limit the range to avoid infinite generation
        $maxEndDate = $startDate->copy()->addYear();
        if ($endDate->gt($maxEndDate)) {
            $endDate = $maxEndDate;
        }

        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            // Check if this date should have a recurring instance
            if ($this->shouldCreateRecurringInstance($schedule, $current)) {
                // Generate periods for this recurring date
                foreach ($basePeriods as $basePeriod) {
                    $recurringPeriod = new \Zap\Models\SchedulePeriod([
                        'schedule_id' => $schedule->id,
                        'date' => $current->toDateString(),
                        'start_time' => $basePeriod->start_time,
                        'end_time' => $basePeriod->end_time,
                        'is_available' => $basePeriod->is_available,
                        'metadata' => $basePeriod->metadata,
                    ]);

                    $allPeriods->push($recurringPeriod);
                }
            }

            $current = $this->getNextRecurrence($schedule, $current);

            if ($current->gt($endDate)) {
                break;
            }
        }

        return $allPeriods;
    }

    /**
     * Check if a recurring instance should be created for the given date.
     */
    protected function shouldCreateRecurringInstance(Schedule $schedule, \Carbon\Carbon $date): bool
    {
        $frequency = $schedule->frequency;
        $config = $schedule->frequency_config ?? [];

        switch ($frequency) {
            case 'daily':
                return true;

            case 'weekly':
                $allowedDays = $config['days'] ?? ['monday'];
                $allowedDayNumbers = array_map(function ($day) {
                    return match (strtolower($day)) {
                        'sunday' => 0,
                        'monday' => 1,
                        'tuesday' => 2,
                        'wednesday' => 3,
                        'thursday' => 4,
                        'friday' => 5,
                        'saturday' => 6,
                        default => 1, // Default to Monday
                    };
                }, $allowedDays);

                return in_array($date->dayOfWeek, $allowedDayNumbers);

            case 'monthly':
                $dayOfMonth = $config['day_of_month'] ?? $schedule->start_date->day;

                return $date->day === $dayOfMonth;

            default:
                return false;
        }
    }

    /**
     * Get the next recurrence date for a recurring schedule.
     */
    protected function getNextRecurrence(Schedule $schedule, \Carbon\Carbon $current): \Carbon\Carbon
    {
        $frequency = $schedule->frequency;
        $config = $schedule->frequency_config ?? [];

        switch ($frequency) {
            case 'daily':
                return $current->copy()->addDay();

            case 'weekly':
                $allowedDays = $config['days'] ?? ['monday'];

                return $this->getNextWeeklyOccurrence($current, $allowedDays);

            case 'monthly':
                $dayOfMonth = $config['day_of_month'] ?? $current->day;

                return $current->copy()->addMonth()->day($dayOfMonth);

            default:
                return $current->copy()->addDay();
        }
    }

    /**
     * Get the next weekly occurrence for the given days.
     */
    protected function getNextWeeklyOccurrence(\Carbon\Carbon $current, array $allowedDays): \Carbon\Carbon
    {
        $next = $current->copy()->addDay();

        // Convert day names to numbers (0 = Sunday, 1 = Monday, etc.)
        $allowedDayNumbers = array_map(function ($day) {
            return match (strtolower($day)) {
                'sunday' => 0,
                'monday' => 1,
                'tuesday' => 2,
                'wednesday' => 3,
                'thursday' => 4,
                'friday' => 5,
                'saturday' => 6,
                default => 1, // Default to Monday
            };
        }, $allowedDays);

        // Find the next allowed day
        while (! in_array($next->dayOfWeek, $allowedDayNumbers)) {
            $next->addDay();

            // Prevent infinite loop
            if ($next->diffInDays($current) > 7) {
                break;
            }
        }

        return $next;
    }

    /**
     * Get other active schedules for the same schedulable.
     */
    protected function getOtherSchedules(Schedule $schedule): Collection
    {
        return Schedule::where('schedulable_type', $schedule->schedulable_type)
            ->where('schedulable_id', $schedule->schedulable_id)
            ->where('id', '!=', $schedule->id)
            ->active()
            ->with('periods')
            ->get();
    }

    /**
     * Parse a time string to Carbon instance.
     */
    protected function parseTime(string $time): \Carbon\Carbon
    {
        $baseDate = '2024-01-01'; // Use a consistent base date for time parsing

        return \Carbon\Carbon::parse($baseDate.' '.$time);
    }

    /**
     * Get conflicts for a specific time period.
     */
    public function findPeriodConflicts(
        Model $schedulable,
        string $date,
        string $startTime,
        string $endTime
    ): Collection {
        return Schedule::where('schedulable_type', get_class($schedulable))
            ->where('schedulable_id', $schedulable->getKey())
            ->active()
            ->forDate($date)
            ->whereHas('periods', function ($query) use ($date, $startTime, $endTime) {
                $query->whereDate('date', $date)
                    ->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime);
            })
            ->with('periods')
            ->get();
    }

    /**
     * Check if a specific time slot is available.
     */
    public function isTimeSlotAvailable(
        Model $schedulable,
        string $date,
        string $startTime,
        string $endTime
    ): bool {
        return $this->findPeriodConflicts($schedulable, $date, $startTime, $endTime)->isEmpty();
    }
}
