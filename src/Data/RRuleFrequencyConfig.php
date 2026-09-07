<?php

namespace Zap\Data;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use RRule\RRule;
use Zap\Models\Schedule;

class RRuleFrequencyConfig extends FrequencyConfig
{
    private ?RRule $rruleInstance = null;

    public function __construct(
        public readonly string $rrule,
        public ?string $dtstart = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            rrule: $data['rrule'],
            dtstart: $data['dtstart'] ?? null,
        );
    }

    public function setStartFromStartDate(CarbonInterface $startDate): self
    {
        $this->dtstart = $startDate->format('Ymd\THis');
        $this->rruleInstance = null;

        return $this;
    }

    public function toArray(): array
    {
        return array_filter([
            'rrule' => $this->rrule,
            'dtstart' => $this->dtstart,
        ]);
    }

    public function getNextRecurrence(CarbonInterface $current): CarbonInterface
    {
        $rrule = $this->getRRule();

        foreach ($rrule as $occurrence) {
            $date = Carbon::instance($occurrence);
            if ($date->greaterThan($current)) {
                return $date;
            }
        }

        // Fallback if no future occurrence is found (finite rule exhausted)
        return $current->copy()->addDay();
    }

    public function shouldCreateInstance(CarbonInterface $date): bool
    {
        return $this->getRRule()->occursAt($date->toDateString());
    }

    public function shouldCreateRecurringInstance(Schedule $schedule, CarbonInterface $date): bool
    {
        if ($date->lt($schedule->start_date) || ($schedule->end_date && $date->gt($schedule->end_date))) {
            return false;
        }

        return $this->shouldCreateInstance($date);
    }

    private function getRRule(): RRule
    {
        if ($this->rruleInstance === null) {
            $rruleString = $this->rrule;

            // If the RRULE string doesn't contain DTSTART and we have one, prepend it
            if ($this->dtstart && ! str_contains(strtoupper($rruleString), 'DTSTART')) {
                $rruleString = "DTSTART:{$this->dtstart}\nRRULE:{$rruleString}";
            }

            $this->rruleInstance = new RRule($rruleString);
        }

        return $this->rruleInstance;
    }
}
