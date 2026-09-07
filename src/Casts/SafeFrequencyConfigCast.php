<?php

namespace Zap\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Zap\Data\FrequencyConfig;
use Zap\Data\MonthlyFrequencyConfig\EveryXMonthsFrequencyConfig;
use Zap\Data\MonthlyFrequencyConfig\MonthlyOrdinalWeekdayFrequencyConfig;
use Zap\Data\RRuleFrequencyConfig;
use Zap\Data\WeeklyFrequencyConfig\EveryXWeeksFrequencyConfig;
use Zap\Models\Schedule;

class SafeFrequencyConfigCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes)
    {
        if ($value === null) {
            return null;
        }

        /** @var Schedule $schedule */
        $schedule = $model;
        $configArray = json_decode($value, true);

        $frequency = $schedule->frequency;

        if (! $frequency || $configArray === null) {
            return null;
        }

        if (is_string($frequency)) {
            // Handle dynamic weekly frequencies (e.g., "every_3_weeks")
            if (preg_match('/^every_(\d+)_weeks$/', $frequency, $matches)) {
                return EveryXWeeksFrequencyConfig::fromArray(
                    array_merge($configArray, ['frequencyWeeks' => (int) $matches[1]])
                );
            }

            // Handle dynamic monthly frequencies (e.g., "every_4_months")
            if (preg_match('/^every_(\d+)_months$/', $frequency, $matches)) {
                return EveryXMonthsFrequencyConfig::fromArray(
                    array_merge($configArray, ['frequencyMonths' => (int) $matches[1]])
                );
            }

            // Handle monthly ordinal weekday (e.g., "first Wednesday of month")
            if ($frequency === 'monthly_ordinal_weekday') {
                return MonthlyOrdinalWeekdayFrequencyConfig::fromArray($configArray);
            }

            // Handle RRULE-based recurrence
            if ($frequency === 'rrule') {
                return RRuleFrequencyConfig::fromArray($configArray);
            }

            return $configArray;
        }

        return $frequency->configClass()::fromArray($configArray);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes)
    {
        /** @var FrequencyConfig|array|null $config */
        $config = $value;

        if ($config === null) {
            return null;
        }

        if (is_array($config)) {
            return json_encode($config);
        }

        return json_encode($config->toArray());
    }
}
