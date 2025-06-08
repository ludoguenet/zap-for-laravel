<?php

namespace Zap\Builders;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Zap\Models\Schedule;
use Zap\Services\ScheduleService;

class ScheduleBuilder
{
    private ?Model $schedulable = null;

    private array $attributes = [];

    private array $periods = [];

    private array $rules = [];

    /**
     * Set the schedulable model (User, etc.)
     */
    public function for(Model $schedulable): self
    {
        $this->schedulable = $schedulable;

        return $this;
    }

    /**
     * Set the schedule name.
     */
    public function named(string $name): self
    {
        $this->attributes['name'] = $name;

        return $this;
    }

    /**
     * Set the schedule description.
     */
    public function description(string $description): self
    {
        $this->attributes['description'] = $description;

        return $this;
    }

    /**
     * Set the start date.
     */
    public function from(Carbon|string $startDate): self
    {
        $this->attributes['start_date'] = $startDate instanceof Carbon
            ? $startDate->toDateString()
            : $startDate;

        return $this;
    }

    /**
     * Set the end date.
     */
    public function to(Carbon|string|null $endDate): self
    {
        $this->attributes['end_date'] = $endDate instanceof Carbon
            ? $endDate->toDateString()
            : $endDate;

        return $this;
    }

    /**
     * Set both start and end dates.
     */
    public function between(Carbon|string $start, Carbon|string $end): self
    {
        return $this->from($start)->to($end);
    }

    /**
     * Add a time period to the schedule.
     */
    public function addPeriod(string $startTime, string $endTime, ?Carbon $date = null): self
    {
        $this->periods[] = [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'date' => $date?->toDateString() ?? $this->attributes['start_date'] ?? now()->toDateString(),
        ];

        return $this;
    }

    /**
     * Add multiple periods at once.
     */
    public function addPeriods(array $periods): self
    {
        foreach ($periods as $period) {
            $this->addPeriod(
                $period['start_time'],
                $period['end_time'],
                isset($period['date']) ? Carbon::parse($period['date']) : null
            );
        }

        return $this;
    }

    /**
     * Set schedule as daily recurring.
     */
    public function daily(): self
    {
        $this->attributes['is_recurring'] = true;
        $this->attributes['frequency'] = 'daily';

        return $this;
    }

    /**
     * Set schedule as weekly recurring.
     */
    public function weekly(array $days = []): self
    {
        $this->attributes['is_recurring'] = true;
        $this->attributes['frequency'] = 'weekly';
        $this->attributes['frequency_config'] = ['days' => $days];

        return $this;
    }

    /**
     * Set schedule as monthly recurring.
     */
    public function monthly(array $config = []): self
    {
        $this->attributes['is_recurring'] = true;
        $this->attributes['frequency'] = 'monthly';
        $this->attributes['frequency_config'] = $config;

        return $this;
    }

    /**
     * Set custom recurring frequency.
     */
    public function recurring(string $frequency, array $config = []): self
    {
        $this->attributes['is_recurring'] = true;
        $this->attributes['frequency'] = $frequency;
        $this->attributes['frequency_config'] = $config;

        return $this;
    }

    /**
     * Add a validation rule.
     */
    public function withRule(string $ruleName, array $config = []): self
    {
        $this->rules[$ruleName] = $config;

        return $this;
    }

    /**
     * Add no overlap rule.
     */
    public function noOverlap(): self
    {
        return $this->withRule('no_overlap');
    }

    /**
     * Add working hours only rule.
     */
    public function workingHoursOnly(string $start = '09:00', string $end = '17:00'): self
    {
        return $this->withRule('working_hours', compact('start', 'end'));
    }

    /**
     * Add maximum duration rule.
     */
    public function maxDuration(int $minutes): self
    {
        return $this->withRule('max_duration', ['minutes' => $minutes]);
    }

    /**
     * Add no weekends rule.
     */
    public function noWeekends(): self
    {
        return $this->withRule('no_weekends');
    }

    /**
     * Add custom metadata.
     */
    public function withMetadata(array $metadata): self
    {
        $this->attributes['metadata'] = array_merge($this->attributes['metadata'] ?? [], $metadata);

        return $this;
    }

    /**
     * Set the schedule as inactive.
     */
    public function inactive(): self
    {
        $this->attributes['is_active'] = false;

        return $this;
    }

    /**
     * Set the schedule as active (default).
     */
    public function active(): self
    {
        $this->attributes['is_active'] = true;

        return $this;
    }

    /**
     * Build and validate the schedule without saving.
     */
    public function build(): array
    {
        if (! $this->schedulable) {
            throw new \InvalidArgumentException('Schedulable model must be set using for() method');
        }

        if (empty($this->attributes['start_date'])) {
            throw new \InvalidArgumentException('Start date must be set using from() method');
        }

        return [
            'schedulable' => $this->schedulable,
            'attributes' => $this->attributes,
            'periods' => $this->periods,
            'rules' => $this->rules,
        ];
    }

    /**
     * Save the schedule.
     */
    public function save(): Schedule
    {
        $built = $this->build();

        return app(ScheduleService::class)->create(
            $built['schedulable'],
            $built['attributes'],
            $built['periods'],
            $built['rules']
        );
    }

    /**
     * Get the current attributes.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get the current periods.
     */
    public function getPeriods(): array
    {
        return $this->periods;
    }

    /**
     * Get the current rules.
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Reset the builder to start fresh.
     */
    public function reset(): self
    {
        $this->schedulable = null;
        $this->attributes = [];
        $this->periods = [];
        $this->rules = [];

        return $this;
    }

    /**
     * Clone the builder with the same configuration.
     */
    public function clone(): self
    {
        $clone = new self;
        $clone->schedulable = $this->schedulable;
        $clone->attributes = $this->attributes;
        $clone->periods = $this->periods;
        $clone->rules = $this->rules;

        return $clone;
    }
}
