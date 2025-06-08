<?php

namespace Zap\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Zap\Builders\ScheduleBuilder;
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
     */
    public function schedules(): MorphMany
    {
        return $this->morphMany(Schedule::class, 'schedulable');
    }

    /**
     * Get only active schedules.
     */
    public function activeSchedules(): MorphMany
    {
        return $this->schedules()->active();
    }

    /**
     * Get schedules for a specific date.
     */
    public function schedulesForDate(string $date): MorphMany
    {
        return $this->schedules()->forDate($date);
    }

    /**
     * Get schedules within a date range.
     */
    public function schedulesForDateRange(string $startDate, string $endDate): MorphMany
    {
        return $this->schedules()->forDateRange($startDate, $endDate);
    }

    /**
     * Get recurring schedules.
     */
    public function recurringSchedules(): MorphMany
    {
        return $this->schedules()->recurring();
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
        return app(ConflictDetectionService::class)->hasConflicts($this, $schedule);
    }

    /**
     * Find all schedules that conflict with the given schedule.
     */
    public function findScheduleConflicts(Schedule $schedule): array
    {
        return app(ConflictDetectionService::class)->findConflicts($this, $schedule);
    }

    /**
     * Check if this model is available during a specific time period.
     */
    public function isAvailableAt(string $date, string $startTime, string $endTime): bool
    {
        // Use direct query instead of relationship for better compatibility with test models
        $conflictingPeriods = \Zap\Models\Schedule::where('schedulable_type', get_class($this))
            ->where('schedulable_id', $this->getKey())
            ->active()
            ->whereHas('periods', function ($query) use ($date, $startTime, $endTime) {
                $query->overlapping($date, $startTime, $endTime);
            })
            ->exists();

        return ! $conflictingPeriods;
    }

    /**
     * Get available time slots for a specific date.
     */
    public function getAvailableSlots(
        string $date,
        string $dayStart = '09:00',
        string $dayEnd = '17:00',
        int $slotDuration = 60
    ): array {
        $slots = [];
        $currentTime = \Carbon\Carbon::parse($date.' '.$dayStart);
        $endTime = \Carbon\Carbon::parse($date.' '.$dayEnd);

        while ($currentTime->lessThan($endTime)) {
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
                ];
            }

            $currentTime->addMinutes($slotDuration);
        }

        return $slots;
    }

    /**
     * Get the next available time slot.
     */
    public function getNextAvailableSlot(
        ?string $afterDate = null,
        int $duration = 60,
        string $dayStart = '09:00',
        string $dayEnd = '17:00'
    ): ?array {
        $startDate = $afterDate ?? now()->format('Y-m-d');
        $checkDate = \Carbon\Carbon::parse($startDate);

        // Check up to 30 days in the future
        for ($i = 0; $i < 30; $i++) {
            $dateString = $checkDate->format('Y-m-d');
            $slots = $this->getAvailableSlots($dateString, $dayStart, $dayEnd, $duration);

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
