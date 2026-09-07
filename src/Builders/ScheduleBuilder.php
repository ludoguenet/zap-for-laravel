<?php

namespace Zap\Builders;

use BadMethodCallException;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use NumberFormatter;
use Zap\Data\DailyFrequencyConfig;
use Zap\Data\FrequencyConfig;
use Zap\Data\MonthlyFrequencyConfig\AnnuallyFrequencyConfig;
use Zap\Data\MonthlyFrequencyConfig\BiMonthlyFrequencyConfig;
use Zap\Data\MonthlyFrequencyConfig\EveryXMonthsFrequencyConfig;
use Zap\Data\MonthlyFrequencyConfig\MonthlyFrequencyConfig;
use Zap\Data\MonthlyFrequencyConfig\MonthlyOrdinalWeekdayFrequencyConfig;
use Zap\Data\MonthlyFrequencyConfig\QuarterlyFrequencyConfig;
use Zap\Data\MonthlyFrequencyConfig\SemiAnnuallyFrequencyConfig;
use Zap\Data\RRuleFrequencyConfig;
use Zap\Data\WeeklyFrequencyConfig\BiWeeklyFrequencyConfig;
use Zap\Data\WeeklyFrequencyConfig\EveryXWeeksFrequencyConfig;
use Zap\Enums\Frequency;
use Zap\Enums\ScheduleTypes;
use Zap\Models\Schedule;
use Zap\Services\ScheduleService;

/**
 * @method self everyThreeWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyFourWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyFiveWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everySixWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everySevenWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyEightWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyNineWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyTenWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyElevenWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyTwelveWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyThirteenWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyFourteenWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyFifteenWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everySixteenWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everySeventeenWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyEighteenWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyNineteenWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyTwentyWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyTwentyOneWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyTwentyTwoWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyTwentyThreeWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyTwentyFourWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyTwentyFiveWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyTwentySixWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyTwentySevenWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyTwentyEightWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyTwentyNineWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyThirtyWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyThirtyOneWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyThirtyTwoWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyThirtyThreeWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyThirtyFourWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyThirtyFiveWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyThirtySixWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyThirtySevenWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyThirtyEightWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyThirtyNineWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyFortyWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyFortyOneWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyFortyTwoWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyFortyThreeWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyFortyFourWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyFortyFiveWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyFortySixWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyFortySevenWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyFortyEightWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyFortyNineWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyFiftyWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyFiftyOneWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyFiftyTwoWeeks(array $days = [], \Carbon\CarbonInterface|string|null $startsOn = null)
 * @method self everyFourMonths(array $config = [])
 * @method self everyFiveMonths(array $config = [])
 * @method self everySevenMonths(array $config = [])
 * @method self everyEightMonths(array $config = [])
 * @method self everyNineMonths(array $config = [])
 * @method self everyTenMonths(array $config = [])
 * @method self everyElevenMonths(array $config = [])
 * @method self firstSundayOfMonth()
 * @method self firstMondayOfMonth()
 * @method self firstTuesdayOfMonth()
 * @method self firstWednesdayOfMonth()
 * @method self firstThursdayOfMonth()
 * @method self firstFridayOfMonth()
 * @method self firstSaturdayOfMonth()
 * @method self secondSundayOfMonth()
 * @method self secondMondayOfMonth()
 * @method self secondTuesdayOfMonth()
 * @method self secondWednesdayOfMonth()
 * @method self secondThursdayOfMonth()
 * @method self secondFridayOfMonth()
 * @method self secondSaturdayOfMonth()
 * @method self thirdSundayOfMonth()
 * @method self thirdMondayOfMonth()
 * @method self thirdTuesdayOfMonth()
 * @method self thirdWednesdayOfMonth()
 * @method self thirdThursdayOfMonth()
 * @method self thirdFridayOfMonth()
 * @method self thirdSaturdayOfMonth()
 * @method self fourthSundayOfMonth()
 * @method self fourthMondayOfMonth()
 * @method self fourthTuesdayOfMonth()
 * @method self fourthWednesdayOfMonth()
 * @method self fourthThursdayOfMonth()
 * @method self fourthFridayOfMonth()
 * @method self fourthSaturdayOfMonth()
 * @method self lastSundayOfMonth()
 * @method self lastMondayOfMonth()
 * @method self lastTuesdayOfMonth()
 * @method self lastWednesdayOfMonth()
 * @method self lastThursdayOfMonth()
 * @method self lastFridayOfMonth()
 * @method self lastSaturdayOfMonth()
 */
class ScheduleBuilder
{
    /**
     * Mapping of week counts to existing named methods.
     * These redirect to the existing frequency methods instead of using generic config.
     */
    private const WEEKLY_REDIRECTS = [
        1 => 'weekly',
        2 => 'biweekly',
    ];

    /**
     * Mapping of month counts to existing named methods.
     * These redirect to the existing frequency methods instead of using generic config.
     */
    private const MONTHLY_REDIRECTS = [
        1 => 'monthly',
        2 => 'bimonthly',
        3 => 'quarterly',
        6 => 'semiannually',
        12 => 'annually',
    ];

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
    public function from(CarbonInterface|string $startDate): self
    {
        $this->attributes['start_date'] = $startDate instanceof CarbonInterface
            ? $startDate->toDateString()
            : $startDate;

        return $this;
    }

    /**
     * Alias of from()
     */
    public function on(CarbonInterface|string $startDate): self
    {
        return $this->from($startDate);
    }

    /**
     * Set the end date.
     */
    public function to(CarbonInterface|string|null $endDate): self
    {
        $this->attributes['end_date'] = $endDate instanceof CarbonInterface
            ? $endDate->toDateString()
            : $endDate;

        return $this;
    }

    /**
     * Set the date for a specific year.
     */
    public function forYear(int $year): self
    {
        $this
            ->from("$year-01-01")
            ->to("$year-12-31");

        return $this;
    }

    /**
     * Set both start and end dates.
     */
    public function between(CarbonInterface|string $start, CarbonInterface|string $end): self
    {
        return $this->from($start)->to($end);
    }

    /**
     * Add a time period to the schedule.
     */
    public function addPeriod(string $startTime, string $endTime, ?CarbonInterface $date = null): self
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
        $this->attributes['frequency'] = Frequency::DAILY;
        $this->attributes['frequency_config'] = new DailyFrequencyConfig;

        return $this;
    }

    /**
     * Internal helper to configure a weekly frequency for the schedule.
     *
     * This method centralizes the logic for setting weekly recurring schedules,
     * including standard weeks, odd weeks, and time periods.
     * It prevents code duplication across public methods like weekly(), weekDays(), weeklyOdd() and weekOddDays()
     *
     * @param  Frequency  $frequency  The frequency type (WEEKLY, WEEKLY_ODD, etc.)
     * @param  array  $days  Array of days the schedule applies to
     * @param  string|null  $startTime  Optional start time for the daily period
     * @param  string|null  $endTime  Optional end time for the daily period
     * @return self Returns the current instance for method chaining
     */
    private function setWeeklyFrequency(Frequency $frequency, array $days, ?string $startTime = null, ?string $endTime = null): self
    {
        $this->attributes['is_recurring'] = true;
        $this->attributes['frequency'] = $frequency;

        $configClass = app($frequency->configClass());
        $this->attributes['frequency_config'] = $configClass->fromArray([
            'days' => $days,
        ]);

        if ($startTime !== null && $endTime !== null) {
            $this->addPeriod($startTime, $endTime, null);
        }

        return $this;
    }

    /**
     * Set schedule as weekly recurring.
     */
    public function weekly(array $days = []): self
    {
        $this->setWeeklyFrequency(Frequency::WEEKLY, $days);

        return $this;
    }

    /**
     * Set schedule as weekly recurring and add a time period.
     */
    public function weekDays(array $days, string $startTime, string $endTime): self
    {
        $this->setWeeklyFrequency(Frequency::WEEKLY, $days, $startTime, $endTime);

        return $this;
    }

    /**
     * Set schedule as weekly recurring on odd weeks.
     */
    public function weeklyOdd(array $days = []): self
    {
        $this->setWeeklyFrequency(Frequency::WEEKLY_ODD, $days);

        return $this;
    }

    /**
     * Set schedule as weekly recurring on odd weeks and add a time period.
     */
    public function weekOddDays(array $days, string $startTime, string $endTime): self
    {
        $this->setWeeklyFrequency(Frequency::WEEKLY_ODD, $days, $startTime, $endTime);

        return $this;
    }

    /**
     * Set schedule as weekly recurring on even weeks.
     */
    public function weeklyEven(array $days = []): self
    {
        $this->setWeeklyFrequency(Frequency::WEEKLY_EVEN, $days);

        return $this;
    }

    /**
     * Set schedule as weekly recurring on even weeks and add a time period.
     */
    public function weekEvenDays(array $days, string $startTime, string $endTime): self
    {
        $this->setWeeklyFrequency(Frequency::WEEKLY_EVEN, $days, $startTime, $endTime);

        return $this;
    }

    /**
     * Set schedule as bi-weekly recurring.
     */
    public function biweekly(array $days = [], CarbonInterface|string|null $startsOn = null): self
    {
        $this->attributes['is_recurring'] = true;
        $this->attributes['frequency'] = Frequency::BIWEEKLY;
        $this->attributes['frequency_config'] = BiWeeklyFrequencyConfig::fromArray([
            'days' => $days,
            'startsOn' => $startsOn,
        ]);

        return $this;
    }

    /**
     * Set schedule as monthly recurring.
     */
    public function monthly(array $config = []): self
    {
        $this->attributes['is_recurring'] = true;
        $this->attributes['frequency'] = Frequency::MONTHLY;
        $this->attributes['frequency_config'] = MonthlyFrequencyConfig::fromArray(
            $config
        );

        return $this;
    }

    /**
     * Set schedule as bi-monthly recurring.
     */
    public function bimonthly(array $config = []): self
    {
        $this->attributes['is_recurring'] = true;
        $this->attributes['frequency'] = Frequency::BIMONTHLY;
        $this->attributes['frequency_config'] = BiMonthlyFrequencyConfig::fromArray(
            $config
        );

        return $this;
    }

    /**
     * Set schedule as quarterly recurring.
     */
    public function quarterly(array $config = []): self
    {
        $this->attributes['is_recurring'] = true;
        $this->attributes['frequency'] = Frequency::QUARTERLY;
        $this->attributes['frequency_config'] = QuarterlyFrequencyConfig::fromArray(
            $config
        );

        return $this;
    }

    /**
     * Set schedule as semi-annually recurring.
     */
    public function semiannually(array $config = []): self
    {
        $this->attributes['is_recurring'] = true;
        $this->attributes['frequency'] = Frequency::SEMIANNUALLY;
        $this->attributes['frequency_config'] = SemiAnnuallyFrequencyConfig::fromArray(
            $config
        );

        return $this;
    }

    /**
     * Set schedule as annually recurring.
     */
    public function annually(array $config = []): self
    {
        $this->attributes['is_recurring'] = true;
        $this->attributes['frequency'] = Frequency::ANNUALLY;
        $this->attributes['frequency_config'] = AnnuallyFrequencyConfig::fromArray(
            $config
        );

        return $this;
    }

    /**
     * Set schedule recurrence using an RFC 5545 RRULE string.
     */
    public function rrule(string $rrule): self
    {
        $this->attributes['is_recurring'] = true;
        $this->attributes['frequency'] = 'rrule';
        $this->attributes['frequency_config'] = new RRuleFrequencyConfig($rrule);

        return $this;
    }

    /**
     * Set custom recurring frequency.
     */
    public function recurring(string|Frequency $frequency, array|FrequencyConfig $config = []): self
    {
        // Check if frequency is a valid enum value and convert config accordingly for backward compatibility
        if (is_string($frequency)) {
            $frequency = Frequency::tryFrom($frequency) ?? $frequency;
            if ($frequency instanceof Frequency) {
                $configClass = $frequency->configClass();
                if ($config instanceof FrequencyConfig && ! ($config instanceof $configClass)) {
                    throw new \InvalidArgumentException("Invalid config class for frequency {$frequency->value}. Expected ".$configClass);
                }
                $config = $config instanceof $configClass ? $config :
                    $frequency->configClass()::fromArray($config);
            }
        }

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
     * Add allow overlap rule.
     */
    public function allowOverlap(): self
    {
        return $this->withRule('no_overlap', ['enabled' => false]);
    }

    /**
     * Set schedule as availability type (allows overlaps).
     */
    public function availability(): self
    {
        $this->attributes['schedule_type'] = ScheduleTypes::AVAILABILITY;

        return $this;
    }

    /**
     * Set schedule as appointment type (prevents overlaps).
     */
    public function appointment(): self
    {
        $this->attributes['schedule_type'] = ScheduleTypes::APPOINTMENT;

        return $this;
    }

    /**
     * Set schedule as blocked type (prevents overlaps).
     */
    public function blocked(): self
    {
        $this->attributes['schedule_type'] = ScheduleTypes::BLOCKED;

        return $this;
    }

    /**
     * Set schedule as custom type.
     */
    public function custom(): self
    {
        $this->attributes['schedule_type'] = ScheduleTypes::CUSTOM;

        return $this;
    }

    /**
     * Set schedule type explicitly.
     */
    public function type(string $type): self
    {
        try {
            $scheduleType = ScheduleTypes::from($type);
        } catch (\ValueError) {
            throw new \InvalidArgumentException("Invalid schedule type: {$type}. Valid types are: ".implode(', ', ScheduleTypes::values()));
        }

        $this->attributes['schedule_type'] = $scheduleType;

        return $this;
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

        // Set default schedule_type if not specified
        if (! isset($this->attributes['schedule_type'])) {
            $this->attributes['schedule_type'] = ScheduleTypes::CUSTOM;
        }

        if (isset($this->attributes['frequency_config']) && $this->attributes['frequency_config'] instanceof FrequencyConfig) {
            $this->attributes['frequency_config']->setStartFromStartDate(
                Carbon::parse($this->attributes['start_date'])
            );
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

    /**
     * Handle dynamic method calls for everyXWeeks and everyXMonths patterns.
     *
     * @param  array<int, mixed>  $parameters
     *
     * @throws BadMethodCallException
     */
    public function __call(string $method, array $parameters): self
    {
        // Match ordinal weekday of month: firstWednesdayOfMonth, secondFridayOfMonth, lastMondayOfMonth
        if (preg_match('/^(first|second|third|fourth|last)(Sunday|Monday|Tuesday|Wednesday|Thursday|Friday|Saturday)OfMonth$/i', $method, $ordinalMatches)) {
            return $this->setMonthlyOrdinalWeekdayFrequency(
                $ordinalMatches[1],
                $ordinalMatches[2]
            );
        }

        // Match patterns like everyThreeWeeks, everyFourMonths, etc.
        if (preg_match('/^every([A-Z][a-zA-Z]+)(Week|Weeks|Month|Months)$/', $method, $matches)) {
            $wordNumber = $matches[1];
            $unit = strtolower($matches[2]);

            $number = $this->wordToNumber($wordNumber);

            if ($number === null) {
                throw new BadMethodCallException("Invalid number word '{$wordNumber}' in method {$method}");
            }

            // Handle weeks (1-52)
            if (str_starts_with($unit, 'week')) {
                if ($number < 1 || $number > 52) {
                    throw new BadMethodCallException("Week frequency must be between 1 and 52, got {$number}");
                }

                // Check for redirect to existing method
                if (isset(self::WEEKLY_REDIRECTS[$number])) {
                    $redirectMethod = self::WEEKLY_REDIRECTS[$number];
                    $days = $parameters[0] ?? [];
                    $startsOn = $parameters[1] ?? null;

                    // weekly() only accepts days, biweekly() accepts days and startsOn
                    if ($redirectMethod === 'weekly') {
                        return $this->weekly($days);
                    }

                    return $this->biweekly($days, $startsOn);
                }

                return $this->setEveryXWeeksFrequency(
                    $number,
                    $parameters[0] ?? [],
                    $parameters[1] ?? null
                );
            }

            // Handle months (1-12)
            if (str_starts_with($unit, 'month')) {
                if ($number < 1 || $number > 12) {
                    throw new BadMethodCallException("Month frequency must be between 1 and 12, got {$number}");
                }

                // Check for redirect to existing method
                if (isset(self::MONTHLY_REDIRECTS[$number])) {
                    $redirectMethod = self::MONTHLY_REDIRECTS[$number];

                    return $this->$redirectMethod($parameters[0] ?? []);
                }

                return $this->setEveryXMonthsFrequency(
                    $number,
                    $parameters[0] ?? []
                );
            }
        }

        throw new BadMethodCallException("Method {$method} does not exist on ".static::class);
    }

    /**
     * Convert a word number (e.g., "Three", "TwentyOne") to its integer value.
     *
     * @param  string  $word  The word representation of a number
     * @return int|null The integer value, or null if conversion fails
     */
    private function wordToNumber(string $word): ?int
    {
        // Convert camelCase to hyphen-separated words for compound numbers
        // e.g., "TwentyOne" -> "Twenty-One" -> "twenty-one"
        // NumberFormatter expects "twenty-one" format, not "twenty one"
        $hyphenated = preg_replace('/([a-z])([A-Z])/', '$1-$2', $word);
        $lowered = strtolower($hyphenated ?? $word);

        $formatter = new NumberFormatter('en', NumberFormatter::SPELLOUT);
        $number = $formatter->parse($lowered);

        return $number !== false ? (int) $number : null;
    }

    /**
     * Set schedule to recur every X weeks.
     *
     * @param  int<1, 52>  $weeks
     */
    private function setEveryXWeeksFrequency(int $weeks, array $days, CarbonInterface|string|null $startsOn): self
    {
        $this->attributes['is_recurring'] = true;
        $this->attributes['frequency'] = "every_{$weeks}_weeks";
        $this->attributes['frequency_config'] = new EveryXWeeksFrequencyConfig(
            frequencyWeeks: $weeks,
            days: $days,
            startsOn: $startsOn,
        );

        return $this;
    }

    /**
     * Set schedule to recur on an ordinal weekday of each month (e.g. 1st Wednesday, last Monday).
     *
     * @param  string  $ordinalWord  first, second, third, fourth, last
     * @param  string  $dayName  Sunday, Monday, ... Saturday
     */
    private function setMonthlyOrdinalWeekdayFrequency(string $ordinalWord, string $dayName): self
    {
        $ordinal = match (strtolower($ordinalWord)) {
            'first' => 1,
            'second' => 2,
            'third' => 3,
            'fourth' => 4,
            'last' => 5,
            default => throw new BadMethodCallException("Invalid ordinal '{$ordinalWord}' in method."),
        };

        $dayOfWeek = match (strtolower($dayName)) {
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            default => 1,
        };

        $this->attributes['is_recurring'] = true;
        $this->attributes['frequency'] = 'monthly_ordinal_weekday';
        $this->attributes['frequency_config'] = new MonthlyOrdinalWeekdayFrequencyConfig(
            ordinal: $ordinal,
            dayOfWeek: $dayOfWeek,
        );

        return $this;
    }

    /**
     * Set schedule to recur every X months.
     *
     * @param  int<1, 12>  $months
     */
    private function setEveryXMonthsFrequency(int $months, array $config): self
    {
        if (array_key_exists('day_of_month', $config) && ! array_key_exists('days_of_month', $config)) {
            $config['days_of_month'] = [$config['day_of_month']];
            unset($config['day_of_month']);
        }

        $this->attributes['is_recurring'] = true;
        $this->attributes['frequency'] = "every_{$months}_months";
        $this->attributes['frequency_config'] = new EveryXMonthsFrequencyConfig(
            frequencyMonths: $months,
            days_of_month: $config['days_of_month'] ?? null,
            start_month: $config['start_month'] ?? null,
        );

        return $this;
    }
}
