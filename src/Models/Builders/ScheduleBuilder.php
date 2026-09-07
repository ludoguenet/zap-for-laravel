<?php

namespace Zap\Models\Builders;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Zap\Enums\Frequency;
use Zap\Enums\ScheduleTypes;
use Zap\Helper\DateHelper;

class ScheduleBuilder extends Builder
{
    public function active(bool $active = true): ScheduleBuilder
    {
        return $this->where('is_active', $active);
    }

    public function recurring(bool $recurring = true): ScheduleBuilder
    {
        return $this->where('is_recurring', $recurring);
    }

    /**
     * Scope a query to only include schedules of a specific type.
     */
    public function ofType(ScheduleTypes|string $type): ScheduleBuilder
    {
        return $this->where('schedule_type', $type);
    }

    /**
     * Scope a query to only include availability schedules.
     */
    public function availability(): ScheduleBuilder
    {
        return $this->where('schedule_type', ScheduleTypes::AVAILABILITY->value);
    }

    /**
     * Scope a query to only include appointment schedules.
     */
    public function appointments(): ScheduleBuilder
    {
        return $this->where('schedule_type', ScheduleTypes::APPOINTMENT->value);
    }

    /**
     * Scope a query to only include blocked schedules.
     */
    public function blocked(): ScheduleBuilder
    {
        return $this->where('schedule_type', ScheduleTypes::BLOCKED->value);
    }

    /**
     * Scope a query to only include schedules for a specific date.
     */
    public function forDate(string $date): ScheduleBuilder
    {
        $checkDate = Carbon::parse($date);
        $weekday = strtolower($checkDate->format('l')); // monday, tuesday, ...
        $dayOfMonth = $checkDate->day;
        $month = $checkDate->month;
        $isDateInEvenIsoWeek = DateHelper::isDateInEvenIsoWeek($date);

        // Valid start_month values for sub-annual frequencies at this calendar month
        $validStartMonthsBimonthly = array_values(array_filter(range(1, 12), fn ($m) => ($month - $m + 12) % 2 === 0));
        $validStartMonthsQuarterly = array_values(array_filter(range(1, 12), fn ($m) => ($month - $m + 12) % 3 === 0));
        $validStartMonthsSemiannually = array_values(array_filter(range(1, 12), fn ($m) => ($month - $m + 12) % 6 === 0));

        return $this
            // date range
            ->where('start_date', '<=', $checkDate)
            ->where(function ($q) use ($checkDate) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $checkDate);
            })

            // recurrence logic
            ->where(function ($q) use ($checkDate, $weekday, $dayOfMonth, $month, $isDateInEvenIsoWeek, $validStartMonthsBimonthly, $validStartMonthsQuarterly, $validStartMonthsSemiannually) {

                //
                // 1️⃣ NOT RECURRING — match exact start_date if no end_date, or any date in range if end_date is set
                //
                $q->where(function ($nonRecurring) use ($checkDate) {
                    $nonRecurring->where('is_recurring', false)
                        ->where(function ($dateLogic) use ($checkDate) {
                            // If end_date is set, any date in the range is valid (handled by outer where clauses)
                            $dateLogic->whereNotNull('end_date')
                                // If end_date is NULL, treat as single-day event - only match exact start_date
                                ->orWhereDate('start_date', $checkDate);
                        });
                })

                    //
                    // 2️⃣ DAILY — match all days
                    //
                    ->orWhere(function ($daily) {
                        $daily->where('is_recurring', true)
                            ->where('frequency', Frequency::DAILY->value);
                    })

                    //
                    // 3️⃣ WEEKLY — match weekday inside config
                    //
                    ->orWhere(function ($weekly) use ($weekday) {
                        $weekly->where('is_recurring', true)
                            ->where('frequency', Frequency::WEEKLY->value)
                            ->whereJsonContains('frequency_config->days', $weekday);
                    })
                    //
                    // 3b️⃣ BI-WEEKLY — match weekday AND ISO week parity relative to startsOn
                    //
                    ->orWhere(function ($biweekly) use ($weekday, $isDateInEvenIsoWeek) {
                        $biweekly->where('is_recurring', true)
                            ->where('frequency', Frequency::BIWEEKLY->value)
                            ->whereJsonContains('frequency_config->days', $weekday)
                            ->where(function ($parity) use ($isDateInEvenIsoWeek) {
                                // null = old record without isEvenStartWeek → include for backward compat
                                $parity->whereNull('frequency_config->isEvenStartWeek')
                                    ->orWhere('frequency_config->isEvenStartWeek', $isDateInEvenIsoWeek);
                            });
                    })
                    //
                    // 4️⃣ WEEKLY_EVEN | WEEKLY_ODD — match weekday inside config
                    //
                    ->orWhere(function ($query) use ($weekday, $isDateInEvenIsoWeek) {
                        $query->where('is_recurring', true)
                            ->where('frequency', $isDateInEvenIsoWeek ? Frequency::WEEKLY_EVEN->value : Frequency::WEEKLY_ODD->value)
                            ->whereJsonContains('frequency_config->days', $weekday);
                    })

                    //
                    // 5️⃣ MONTHLY — any month is valid; match day_of_month from config
                    //
                    ->orWhere(function ($monthly) use ($dayOfMonth) {
                        $monthly->where('is_recurring', true)
                            ->where('frequency', Frequency::MONTHLY->value)
                            ->where(function ($m) use ($dayOfMonth) {
                                $m->whereJsonContains('frequency_config->days_of_month', $dayOfMonth)
                                    ->orWhere('frequency_config->days_of_month', $dayOfMonth);
                            });
                    })

                    //
                    // 5b️⃣ BIMONTHLY — match day_of_month and only months where (M - start_month + 12) % 2 = 0
                    //
                    ->orWhere(function ($bimonthly) use ($dayOfMonth, $validStartMonthsBimonthly) {
                        $bimonthly->where('is_recurring', true)
                            ->where('frequency', Frequency::BIMONTHLY->value)
                            ->where(function ($m) use ($dayOfMonth) {
                                $m->whereJsonContains('frequency_config->days_of_month', $dayOfMonth)
                                    ->orWhere('frequency_config->days_of_month', $dayOfMonth);
                            })
                            ->whereIn('frequency_config->start_month', $validStartMonthsBimonthly);
                    })

                    //
                    // 5c️⃣ QUARTERLY — match day_of_month and only months where (M - start_month + 12) % 3 = 0
                    //
                    ->orWhere(function ($quarterly) use ($dayOfMonth, $validStartMonthsQuarterly) {
                        $quarterly->where('is_recurring', true)
                            ->where('frequency', Frequency::QUARTERLY->value)
                            ->where(function ($m) use ($dayOfMonth) {
                                $m->whereJsonContains('frequency_config->days_of_month', $dayOfMonth)
                                    ->orWhere('frequency_config->days_of_month', $dayOfMonth);
                            })
                            ->whereIn('frequency_config->start_month', $validStartMonthsQuarterly);
                    })

                    //
                    // 5d️⃣ SEMIANNUALLY — match day_of_month and only months where (M - start_month + 12) % 6 = 0
                    //
                    ->orWhere(function ($semi) use ($dayOfMonth, $validStartMonthsSemiannually) {
                        $semi->where('is_recurring', true)
                            ->where('frequency', Frequency::SEMIANNUALLY->value)
                            ->where(function ($m) use ($dayOfMonth) {
                                $m->whereJsonContains('frequency_config->days_of_month', $dayOfMonth)
                                    ->orWhere('frequency_config->days_of_month', $dayOfMonth);
                            })
                            ->whereIn('frequency_config->start_month', $validStartMonthsSemiannually);
                    })

                    //
                    // 5e️⃣ ANNUALLY — match day_of_month and exact start_month
                    //
                    ->orWhere(function ($annually) use ($dayOfMonth, $month) {
                        $annually->where('is_recurring', true)
                            ->where('frequency', Frequency::ANNUALLY->value)
                            ->where(function ($m) use ($dayOfMonth) {
                                $m->whereJsonContains('frequency_config->days_of_month', $dayOfMonth)
                                    ->orWhere('frequency_config->days_of_month', $dayOfMonth);
                            })
                            ->where('frequency_config->start_month', $month);
                    })

                    //
                    // 6️⃣ MONTHLY ORDINAL WEEKDAY — match day_of_week; ordinal filtered by shouldCreateRecurringInstance
                    //
                    ->orWhere(function ($ordinalWeekday) use ($checkDate) {
                        $ordinalWeekday->where('is_recurring', true)
                            ->where('frequency', 'monthly_ordinal_weekday')
                            ->where('frequency_config->day_of_week', $checkDate->dayOfWeek);
                    })

                    //
                    // 7️⃣ RRULE — include all; occurrence check delegated to shouldCreateRecurringInstance
                    //
                    ->orWhere(function ($rrule) {
                        $rrule->where('is_recurring', true)
                            ->where('frequency', 'rrule');
                    });
            });
    }

    /**
     * Scope a query to only include schedules within a date range.
     */
    public function forDateRange(string $startDate, string $endDate): ScheduleBuilder
    {
        return $this->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
                ->orWhereBetween('end_date', [$startDate, $endDate])
                ->orWhere(function ($q2) use ($startDate, $endDate) {
                    $q2->where('start_date', '<=', $startDate)
                        ->where(
                            fn ($q3) => $q3->whereNull('end_date')->orWhere('end_date', '>=', $endDate),
                        );
                });
        });
    }
}
