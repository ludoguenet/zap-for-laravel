<?php

namespace Zap\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Zap\Builders\ScheduleBuilder;
use Zap\Events\ScheduleCreated;
use Zap\Exceptions\ScheduleConflictException;
use Zap\Models\Schedule;

class ScheduleService
{
    public function __construct(
        private ValidationService $validator,
        private ConflictDetectionService $conflictService
    ) {}

    /**
     * Create a new schedule with validation and conflict detection.
     */
    public function create(
        Model $schedulable,
        array $attributes,
        array $periods = [],
        array $rules = []
    ): Schedule {
        return DB::transaction(function () use ($schedulable, $attributes, $periods, $rules) {
            // Set default values
            $attributes = array_merge([
                'is_active' => true,
                'is_recurring' => false,
            ], $attributes);

            // Validate the schedule data
            $this->validator->validate($schedulable, $attributes, $periods, $rules);

            // Create the schedule
            $schedule = new Schedule($attributes);
            $schedule->schedulable_type = get_class($schedulable);
            $schedule->schedulable_id = $schedulable->getKey();
            $schedule->save();

            // Create periods if provided
            if (! empty($periods)) {
                foreach ($periods as $period) {
                    $period['schedule_id'] = $schedule->id;
                    $schedule->periods()->create($period);
                }
            }

            // Note: Conflict checking is now done during validation phase
            // No need to check again after creation

            // Fire the created event
            Event::dispatch(new ScheduleCreated($schedule));

            return $schedule->load('periods');
        });
    }

    /**
     * Update an existing schedule.
     */
    public function update(Schedule $schedule, array $attributes, array $periods = []): Schedule
    {
        return DB::transaction(function () use ($schedule, $attributes, $periods) {
            // Update the schedule attributes
            $schedule->update($attributes);

            // Update periods if provided
            if (! empty($periods)) {
                // Delete existing periods and create new ones
                $schedule->periods()->delete();

                foreach ($periods as $period) {
                    $period['schedule_id'] = $schedule->id;
                    $schedule->periods()->create($period);
                }
            }

            // Check for conflicts after update
            $conflicts = $this->conflictService->findConflicts($schedule);
            if (! empty($conflicts)) {
                throw (new ScheduleConflictException(
                    'Updated schedule conflicts with existing schedules'
                ))->setConflictingSchedules($conflicts);
            }

            return $schedule->fresh('periods');
        });
    }

    /**
     * Delete a schedule.
     */
    public function delete(Schedule $schedule): bool
    {
        return DB::transaction(function () use ($schedule) {
            // Delete all periods first
            $schedule->periods()->delete();

            // Delete the schedule
            return $schedule->delete();
        });
    }

    /**
     * Create a schedule builder for a schedulable model.
     */
    public function for(Model $schedulable): ScheduleBuilder
    {
        return (new ScheduleBuilder)->for($schedulable);
    }

    /**
     * Create a new schedule builder.
     */
    public function schedule(): ScheduleBuilder
    {
        return new ScheduleBuilder;
    }

    /**
     * Find all schedules that conflict with the given schedule.
     */
    public function findConflicts(Schedule $schedule): array
    {
        return $this->conflictService->findConflicts($schedule);
    }

    /**
     * Check if a schedule has conflicts.
     */
    public function hasConflicts(Schedule $schedule): bool
    {
        return $this->conflictService->hasConflicts($schedule);
    }

    /**
     * Get available time slots for a schedulable on a given date.
     */
    public function getAvailableSlots(
        Model $schedulable,
        string $date,
        string $startTime = '09:00',
        string $endTime = '17:00',
        int $slotDuration = 60
    ): array {
        if (method_exists($schedulable, 'getAvailableSlots')) {
            return $schedulable->getAvailableSlots($date, $startTime, $endTime, $slotDuration);
        }

        return [];
    }

    /**
     * Check if a schedulable is available at a specific time.
     */
    public function isAvailable(
        Model $schedulable,
        string $date,
        string $startTime,
        string $endTime
    ): bool {
        if (method_exists($schedulable, 'isAvailableAt')) {
            return $schedulable->isAvailableAt($date, $startTime, $endTime);
        }

        return true; // Default to available if no schedule trait
    }

    /**
     * Get all schedules for a schedulable within a date range.
     */
    public function getSchedulesForDateRange(
        Model $schedulable,
        string $startDate,
        string $endDate
    ): \Illuminate\Database\Eloquent\Collection {
        if (method_exists($schedulable, 'schedulesForDateRange')) {
            return $schedulable->schedulesForDateRange($startDate, $endDate)->get();
        }

        return new \Illuminate\Database\Eloquent\Collection;
    }

    /**
     * Generate recurring schedule instances for a given period.
     */
    public function generateRecurringInstances(
        Schedule $schedule,
        string $startDate,
        string $endDate
    ): array {
        if (! $schedule->is_recurring) {
            return [];
        }

        $instances = [];
        $current = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        while ($current->lte($end)) {
            if ($this->shouldCreateInstance($schedule, $current)) {
                $instances[] = [
                    'date' => $current->toDateString(),
                    'schedule' => $schedule,
                ];
            }

            $current = $this->getNextRecurrence($schedule, $current);
        }

        return $instances;
    }

    /**
     * Check if a recurring instance should be created for the given date.
     */
    private function shouldCreateInstance(Schedule $schedule, \Carbon\Carbon $date): bool
    {
        $frequency = $schedule->frequency;
        $config = $schedule->frequency_config ?? [];

        switch ($frequency) {
            case 'daily':
                return true;

            case 'weekly':
                $allowedDays = $config['days'] ?? [];

                return empty($allowedDays) || in_array(strtolower($date->format('l')), $allowedDays);

            case 'monthly':
                $dayOfMonth = $config['day_of_month'] ?? $date->day;

                return $date->day === $dayOfMonth;

            default:
                return false;
        }
    }

    /**
     * Get the next recurrence date.
     */
    private function getNextRecurrence(Schedule $schedule, \Carbon\Carbon $current): \Carbon\Carbon
    {
        $frequency = $schedule->frequency;

        switch ($frequency) {
            case 'daily':
                return $current->addDay();

            case 'weekly':
                return $current->addWeek();

            case 'monthly':
                return $current->addMonth();

            default:
                return $current->addDay();
        }
    }
}
