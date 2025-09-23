<?php

namespace Zap\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Zap\Builders\ScheduleBuilder;
use Zap\Enums\ScheduleTypes;
use Zap\Models\Schedule;
use Zap\Services\ConflictDetectionService;

/**
 * Trait HasSchedules
 *
 * This trait provides scheduling capabilities to any Eloquent model.
 * Use this trait in models that need to be schedulable.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasSchedules
{
    /**
     * Get all schedules for this model.
     *
     * @return MorphMany<Schedule, $this>
     */
    public function schedules(): MorphMany
    {
        return $this->morphMany(Schedule::class, 'schedulable');
    }

    /**
     * Get only active schedules.
     *
     * @return MorphMany<Schedule, $this>
     */
    public function activeSchedules(): MorphMany
    {
        return $this->schedules()->active();
    }

    /**
     * Get availability schedules.
     *
     * @return MorphMany<Schedule, $this>
     */
    public function availabilitySchedules(): MorphMany
    {
        return $this->schedules()->availability();
    }

    /**
     * Get appointment schedules.
     *
     * @return MorphMany<Schedule, $this>
     */
    public function appointmentSchedules(): MorphMany
    {
        return $this->schedules()->appointments();
    }

    /**
     * Get blocked schedules.
     *
     * @return MorphMany<Schedule, $this>
     */
    public function blockedSchedules(): MorphMany
    {
        return $this->schedules()->blocked();
    }

    /**
     * Get schedules for a specific date.
     *
     * @return MorphMany<Schedule, $this>
     */
    public function schedulesForDate(string $date): MorphMany
    {
        return $this->schedules()->forDate($date);
    }

    /**
     * Get schedules within a date range.
     *
     * @return MorphMany<Schedule, $this>
     */
    public function schedulesForDateRange(string $startDate, string $endDate): MorphMany
    {
        return $this->schedules()->forDateRange($startDate, $endDate);
    }

    /**
     * Get recurring schedules.
     *
     * @return MorphMany<Schedule, $this>
     */
    public function recurringSchedules(): MorphMany
    {
        return $this->schedules()->recurring();
    }

    /**
     * Get schedules of a specific type.
     *
     * @return MorphMany<Schedule, $this>
     */
    public function schedulesOfType(string $type): MorphMany
    {
        return $this->schedules()->ofType($type);
    }

    /**
     * Create a new schedule builder for this model.
     */
    public function createSchedule(): ScheduleBuilder
    {
        return (new ScheduleBuilder)->for($this);
    }

    /**
     * Check if this model has any schedule conflicts with the given schedule.
     */
    public function hasScheduleConflict(Schedule $schedule): bool
    {
        return app(ConflictDetectionService::class)->hasConflicts($schedule);
    }

    /**
     * Find all schedules that conflict with the given schedule.
     */
    public function findScheduleConflicts(Schedule $schedule): array
    {
        return app(ConflictDetectionService::class)->findConflicts($schedule);
    }

    /**
     * Check if this model is available during a specific time period.
     */
    public function isAvailableAt(string $date, string $startTime, string $endTime): bool
    {
        // Get all active schedules for this model on this date
        $schedules = \Zap\Models\Schedule::where('schedulable_type', get_class($this))
            ->where('schedulable_id', $this->getKey())
            ->active()
            ->forDate($date)
            ->with('periods')
            ->get();

        foreach ($schedules as $schedule) {
            $shouldBlock = $schedule->schedule_type->is(ScheduleTypes::CUSTOM) || $schedule->preventsOverlaps();

            if ($shouldBlock && $this->scheduleBlocksTime($schedule, $date, $startTime, $endTime)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a specific schedule blocks the given time period.
     */
    protected function scheduleBlocksTime(\Zap\Models\Schedule $schedule, string $date, string $startTime, string $endTime): bool
    {
        if (! $schedule->isActiveOn($date)) {
            return false;
        }

        $bufferMinutes = (int) config('zap.conflict_detection.buffer_minutes', 0);

        if ($schedule->is_recurring) {
            return $this->recurringScheduleBlocksTime($schedule, $date, $startTime, $endTime, $bufferMinutes);
        }

        // For non-recurring schedules: if no buffer, keep using the optimized overlapping scope
        if ($bufferMinutes <= 0) {
            return $schedule->periods()->overlapping($date, $startTime, $endTime, $schedule->end_date ?? null)->exists();
        }

        // With buffer, we need to evaluate in PHP
        $periods = $schedule->periods()->forDate($date)->get();

        foreach ($periods as $period) {
            if ($this->timePeriodsOverlapWithBuffer($period->start_time, $period->end_time, $startTime, $endTime, $bufferMinutes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a recurring schedule blocks the given time period.
     */
    protected function recurringScheduleBlocksTime(\Zap\Models\Schedule $schedule, string $date, string $startTime, string $endTime, int $bufferMinutes = 0): bool
    {
        $checkDate = \Carbon\Carbon::parse($date);

        // Check if this date should have a recurring instance
        if (! $this->shouldCreateRecurringInstance($schedule, $checkDate)) {
            return false;
        }

        // Get the base periods and check if any would overlap on this date
        $basePeriods = $schedule->periods;

        foreach ($basePeriods as $basePeriod) {
            if ($this->timePeriodsOverlapWithBuffer($basePeriod->start_time, $basePeriod->end_time, $startTime, $endTime, $bufferMinutes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a recurring instance should be created for the given date.
     */
    protected function shouldCreateRecurringInstance(\Zap\Models\Schedule $schedule, \Carbon\Carbon $date): bool
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
     * Check if two time periods overlap.
     */
    protected function timePeriodsOverlap(string $start1, string $end1, string $start2, string $end2): bool
    {
        return $start1 < $end2 && $end1 > $start2;
    }

    /**
     * Check if two time periods overlap, applying an optional symmetric buffer (in minutes)
     * around the first period.
     */
    protected function timePeriodsOverlapWithBuffer(string $start1, string $end1, string $start2, string $end2, int $bufferMinutes = 0): bool
    {
        if ($bufferMinutes <= 0) {
            return $this->timePeriodsOverlap($start1, $end1, $start2, $end2);
        }

        $baseDate = '2024-01-01';
        $s1 = \Carbon\Carbon::parse($baseDate.' '.$start1)->subMinutes($bufferMinutes);
        $e1 = \Carbon\Carbon::parse($baseDate.' '.$end1)->addMinutes($bufferMinutes);
        $s2 = \Carbon\Carbon::parse($baseDate.' '.$start2);
        $e2 = \Carbon\Carbon::parse($baseDate.' '.$end2);

        return $s1->lt($e2) && $e1->gt($s2);
    }

    /**
     * Get available time slots for a specific date.
     */
    public function getAvailableSlots(
        string $date,
        string $dayStart = '09:00',
        string $dayEnd = '17:00',
        int $slotDuration = 60,
        ?int $bufferMinutes = null
    ): array {
        // Validate inputs to prevent infinite loops
        if ($slotDuration <= 0) {
            return [];
        }

        if ($bufferMinutes === null) {
            $bufferMinutes = (int) config('zap.time_slots.buffer_minutes', 0);
        }

        $bufferMinutes = max(0, $bufferMinutes);

        $slots = [];
        $currentTime = \Carbon\Carbon::parse($date.' '.$dayStart);
        $endTime = \Carbon\Carbon::parse($date.' '.$dayEnd);

        // If end time is before or equal to start time, return empty array
        if ($endTime->lessThanOrEqualTo($currentTime)) {
            return [];
        }

        // Safety counter to prevent infinite loops (max 1440 minutes in a day / min slot duration)
        $maxIterations = 1440;
        $iterations = 0;

        $slotInterval = $slotDuration + $bufferMinutes;

        while ($currentTime->lessThan($endTime) && $iterations < $maxIterations) {
            $slotEnd = $currentTime->copy()->addMinutes($slotDuration);

            if ($slotEnd->lessThanOrEqualTo($endTime)) {
                $isAvailable = $this->isAvailableAt(
                    $date,
                    $currentTime->format('H:i'),
                    $slotEnd->format('H:i')
                );

                $slots[] = [
                    'start_time' => $currentTime->format('H:i'),
                    'end_time' => $slotEnd->format('H:i'),
                    'is_available' => $isAvailable,
                    'buffer_minutes' => $bufferMinutes,
                ];
            }

            $currentTime->addMinutes($slotInterval);
            $iterations++;
        }

        return $slots;
    }

    /**
     * Get bookable time slots for a specific date that intersect with availability schedules.
     */
    public function getBookableSlots(
        string $date,
        int $slotDuration = 60,
        ?int $bufferMinutes = null
    ): array {
        if ($slotDuration <= 0) {
            return [];
        }

        if ($bufferMinutes === null) {
            $bufferMinutes = (int) config('zap.time_slots.buffer_minutes', 0);
        }

        $bufferMinutes = max(0, $bufferMinutes);

        // Get availability periods for this date in a single query
        $availabilityPeriods = $this->getAvailabilityPeriodsForDate($date);

        if ($availabilityPeriods->isEmpty()) {
            return [];
        }

        // Get all blocking schedules in a single query for conflict checking
        $blockingSchedules = $this->getBlockingSchedulesForDate($date);

        $slotInterval = $slotDuration + $bufferMinutes;
        $allSlots = collect();

        // Generate slots for each availability period
        $availabilityPeriods->each(function ($period) use ($date, $slotDuration, $bufferMinutes, $slotInterval, $blockingSchedules, &$allSlots) {
            $currentTime = \Carbon\Carbon::parse($date.' '.$period->start_time);
            $periodEnd = \Carbon\Carbon::parse($date.' '.$period->end_time);

            while ($currentTime->lessThan($periodEnd)) {
                $slotEnd = $currentTime->copy()->addMinutes($slotDuration);

                if ($slotEnd->lessThanOrEqualTo($periodEnd)) {
                    $startTime = $currentTime->format('H:i');
                    $endTime = $slotEnd->format('H:i');

                    // Check availability against pre-loaded blocking schedules
                    $isAvailable = $this->isSlotAvailable($startTime, $endTime, $date, $blockingSchedules, $bufferMinutes);

                    $allSlots->push([
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'is_available' => $isAvailable,
                        'buffer_minutes' => $bufferMinutes,
                    ]);
                }

                $currentTime->addMinutes($slotInterval);
            }
        });

        // Remove duplicates and sort
        return $allSlots
            ->unique(fn ($slot) => $slot['start_time'].'|'.$slot['end_time'])
            ->sortBy('start_time')
            ->values()
            ->toArray();
    }

    /**
     * Get all availability periods for a specific date in a single optimized query.
     */
    protected function getAvailabilityPeriodsForDate(string $date): \Illuminate\Support\Collection
    {
        $checkDate = \Carbon\Carbon::parse($date);

        // Get all availability schedules for this date
        $availabilitySchedules = Schedule::where('schedulable_type', get_class($this))
            ->where('schedulable_id', $this->getKey())
            ->availability()
            ->active()
            ->forDate($date)
            ->with('periods')
            ->get();

        $allPeriods = collect();

        $availabilitySchedules->each(function ($schedule) use ($date, $checkDate, &$allPeriods) {
            if (! $schedule->isActiveOn($date)) {
                return;
            }

            if ($schedule->is_recurring) {
                if ($this->shouldCreateRecurringInstance($schedule, $checkDate)) {
                    $schedule->periods->each(function ($period) use (&$allPeriods) {
                        $allPeriods->push((object) [
                            'start_time' => $period->start_time,
                            'end_time' => $period->end_time,
                        ]);
                    });
                }
            } else {
                $schedule->periods()
                    ->forDate($date)
                    ->get(['start_time', 'end_time'])
                    ->each(function ($period) use (&$allPeriods) {
                        $allPeriods->push($period);
                    });
            }
        });

        return $allPeriods;
    }

    /**
     * Get all blocking schedules for a specific date in a single query.
     */
    protected function getBlockingSchedulesForDate(string $date): \Illuminate\Support\Collection
    {
        return Schedule::where('schedulable_type', get_class($this))
            ->where('schedulable_id', $this->getKey())
            ->whereIn('schedule_type', [ScheduleTypes::APPOINTMENT, ScheduleTypes::BLOCKED, ScheduleTypes::CUSTOM])
            ->active()
            ->forDate($date)
            ->with('periods')
            ->get();
    }

    /**
     * Check if a slot is available against pre-loaded blocking schedules.
     */
    protected function isSlotAvailable(string $startTime, string $endTime, string $date, \Illuminate\Support\Collection $blockingSchedules, int $bufferMinutes = 0): bool
    {
        foreach ($blockingSchedules as $schedule) {
            if ($schedule->schedule_type->is(ScheduleTypes::CUSTOM) || $schedule->preventsOverlaps()) {
                if ($this->scheduleBlocksTime($schedule, $date, $startTime, $endTime)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get the next bookable time slot that respects availability schedules.
     */
    public function getNextBookableSlot(
        ?string $afterDate = null,
        int $duration = 60,
        ?int $bufferMinutes = null
    ): ?array {
        if ($duration <= 0) {
            return null;
        }

        $startDate = $afterDate ?? now()->format('Y-m-d');
        $checkDate = \Carbon\Carbon::parse($startDate);

        // Check up to 30 days in the future
        for ($i = 0; $i < 30; $i++) {
            $dateString = $checkDate->format('Y-m-d');
            $slots = $this->getBookableSlots($dateString, $duration, $bufferMinutes);

            foreach ($slots as $slot) {
                if ($slot['is_available']) {
                    return array_merge($slot, ['date' => $dateString]);
                }
            }

            $checkDate->addDay();
        }

        return null;
    }

    /**
     * Get the next available time slot.
     */
    public function getNextAvailableSlot(
        ?string $afterDate = null,
        int $duration = 60,
        string $dayStart = '09:00',
        string $dayEnd = '17:00',
        ?int $bufferMinutes = null
    ): ?array {
        // Validate inputs
        if ($duration <= 0) {
            return null;
        }

        $startDate = $afterDate ?? now()->format('Y-m-d');
        $checkDate = \Carbon\Carbon::parse($startDate);

        // Check up to 30 days in the future
        for ($i = 0; $i < 30; $i++) {
            $dateString = $checkDate->format('Y-m-d');
            $slots = $this->getAvailableSlots($dateString, $dayStart, $dayEnd, $duration, $bufferMinutes);

            foreach ($slots as $slot) {
                if ($slot['is_available']) {
                    return array_merge($slot, ['date' => $dateString]);
                }
            }

            $checkDate->addDay();
        }

        return null;
    }

    /**
     * Count total scheduled time for a date range.
     */
    public function getTotalScheduledTime(string $startDate, string $endDate): int
    {
        return $this->schedules()
            ->active()
            ->forDateRange($startDate, $endDate)
            ->with('periods')
            ->get()
            ->sum(function ($schedule) {
                return $schedule->periods->sum('duration_minutes');
            });
    }

    /**
     * Check if the model has any schedules.
     */
    public function hasSchedules(): bool
    {
        return $this->schedules()->exists();
    }

    /**
     * Check if the model has any active schedules.
     */
    public function hasActiveSchedules(): bool
    {
        return $this->activeSchedules()->exists();
    }
}
