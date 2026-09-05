<?php

namespace Zap\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Zap\Casts\SafeFrequencyCast;
use Zap\Casts\SafeFrequencyConfigCast;
use Zap\Data\FrequencyConfig;
use Zap\Enums\Frequency;
use Zap\Enums\ScheduleTypes;
use Zap\Models\Builders\ScheduleBuilder;

/**
 * @property int|string $id
 * @property string $name
 * @property string|null $description
 * @property string $schedulable_type
 * @property int|string $schedulable_id
 * @property ScheduleTypes $schedule_type
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property bool $is_recurring
 * @property Frequency|string|null $frequency
 * @property FrequencyConfig|array|null $frequency_config
 * @property array|null $metadata
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, SchedulePeriod> $periods
 * @property-read Model $schedulable
 * @property-read int $total_duration
 *
 * @method static \Illuminate\Database\Eloquent\Builder active(bool $active = true)
 * @method static \Illuminate\Database\Eloquent\Builder recurring(bool $recurring = true)
 * @method static \Illuminate\Database\Eloquent\Builder ofType(ScheduleTypes|string $type)
 * @method static \Illuminate\Database\Eloquent\Builder availability()
 * @method static \Illuminate\Database\Eloquent\Builder appointments()
 * @method static \Illuminate\Database\Eloquent\Builder blocked()
 * @method static \Illuminate\Database\Eloquent\Builder forDate(string $date)
 * @method static \Illuminate\Database\Eloquent\Builder forDateRange(string $startDate, string $endDate)
 */
class Schedule extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'schedules';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'schedulable_type',
        'schedulable_id',
        'name',
        'description',
        'schedule_type',
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
        'schedule_type' => ScheduleTypes::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'is_recurring' => 'boolean',
        'frequency' => SafeFrequencyCast::class,
        'frequency_config' => SafeFrequencyConfigCast::class,
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * The attributes that should be guarded.
     */
    protected $guarded = [];

    /**
     * Retrieve the FQCN of the class to use for Schedule Period models.
     *
     * @return class-string<SchedulePeriod>
     */
    protected function getSchedulePeriodClass(): string
    {
        return config('zap.models.schedule_period', SchedulePeriod::class);
    }

    /**
     * Get the parent schedulable model.
     */
    public function schedulable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the schedule periods.
     *
     * @return HasMany<SchedulePeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany($this->getSchedulePeriodClass(), 'schedule_id');
    }

    /**
     * Create a new Eloquent query builder for the model.
     */
    public function newEloquentBuilder($query): ScheduleBuilder
    {
        return new ScheduleBuilder($query);
    }

    /**
     * Create a new Eloquent Collection instance.
     *
     * @param  array<int, static>  $models
     * @return Collection<int, static>
     */
    public function newCollection(array $models = []): Collection
    {
        return new Collection($models);
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

        return $this->end_date >= $other->start_date;
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

        $checkDate = Carbon::parse($date);
        $startDate = $this->start_date;
        $endDate = $this->end_date;

        return $checkDate->greaterThanOrEqualTo($startDate) &&
               ($endDate === null || $checkDate->lessThanOrEqualTo($endDate));
    }

    /**
     * Check if this schedule is of availability type.
     */
    public function isAvailability(): bool
    {
        return $this->schedule_type->is(ScheduleTypes::AVAILABILITY);
    }

    /**
     * Check if this schedule is of appointment type.
     */
    public function isAppointment(): bool
    {
        return $this->schedule_type->is(ScheduleTypes::APPOINTMENT);
    }

    /**
     * Check if this schedule is of blocked type.
     */
    public function isBlocked(): bool
    {
        return $this->schedule_type->is(ScheduleTypes::BLOCKED);
    }

    /**
     * Check if this schedule is of custom type.
     */
    public function isCustom(): bool
    {
        return $this->schedule_type->is(ScheduleTypes::CUSTOM);
    }

    /**
     * Check if this schedule should prevent overlaps (appointments and blocked schedules).
     */
    public function preventsOverlaps(): bool
    {
        return $this->schedule_type->preventsOverlaps();
    }

    /**
     * Check if this schedule allows overlaps (availability schedules).
     */
    public function allowsOverlaps(): bool
    {
        return $this->schedule_type->allowsOverlaps();
    }
}
