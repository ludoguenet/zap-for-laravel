<?php

namespace Zap\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $schedulable_type
 * @property int $schedulable_id
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property bool $is_recurring
 * @property string|null $frequency
 * @property array|null $frequency_config
 * @property array|null $metadata
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SchedulePeriod> $periods
 * @property-read Model $schedulable
 * @property-read int $total_duration
 */
class Schedule extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'schedulable_type',
        'schedulable_id',
        'name',
        'description',
        'start_date',
        'end_date',
        'is_recurring',
        'frequency',
        'frequency_config',
        'metadata',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_recurring' => 'boolean',
        'frequency_config' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the parent schedulable model.
     */
    public function schedulable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the schedule periods.
     */
    public function periods(): HasMany
    {
        return $this->hasMany(SchedulePeriod::class);
    }

    /**
     * Create a new Eloquent query builder for the model.
     */
    public function newEloquentBuilder($query): Builder
    {
        return new Builder($query);
    }

    /**
     * Create a new Eloquent Collection instance.
     *
     * @param  array<int, static>  $models
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public function newCollection(array $models = []): Collection
    {
        return new Collection($models);
    }

    /**
     * Scope a query to only include active schedules.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope a query to only include recurring schedules.
     */
    public function scopeRecurring(Builder $query): void
    {
        $query->where('is_recurring', true);
    }

    /**
     * Scope a query to only include schedules for a specific date.
     */
    public function scopeForDate(Builder $query, string $date): void
    {
        $query->where('start_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            });
    }

    /**
     * Scope a query to only include schedules within a date range.
     */
    public function scopeForDateRange(Builder $query, string $startDate, string $endDate): void
    {
        $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
                ->orWhereBetween('end_date', [$startDate, $endDate])
                ->orWhere(function ($q2) use ($startDate, $endDate) {
                    $q2->where('start_date', '<=', $startDate)
                        ->where('end_date', '>=', $endDate);
                });
        });
    }

    /**
     * Check if this schedule overlaps with another schedule.
     */
    public function overlapsWith(Schedule $other): bool
    {
        // Basic date range overlap check
        if ($this->end_date && $other->end_date) {
            return $this->start_date <= $other->end_date && $this->end_date >= $other->start_date;
        }

        // Handle open-ended schedules
        if (! $this->end_date && ! $other->end_date) {
            return $this->start_date <= $other->start_date;
        }

        if (! $this->end_date) {
            return $this->start_date <= ($other->end_date ?? $other->start_date);
        }

        if (! $other->end_date) {
            return $this->end_date >= $other->start_date;
        }

        return false;
    }

    /**
     * Get the total duration of all periods in minutes.
     */
    public function getTotalDurationAttribute(): int
    {
        return $this->periods->sum('duration_minutes');
    }

    /**
     * Check if the schedule is currently active.
     */
    public function isActiveOn(string $date): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $checkDate = \Carbon\Carbon::parse($date);
        $startDate = $this->start_date;
        $endDate = $this->end_date;

        return $checkDate->greaterThanOrEqualTo($startDate) &&
               ($endDate === null || $checkDate->lessThanOrEqualTo($endDate));
    }
}
