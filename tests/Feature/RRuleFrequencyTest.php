<?php

use Carbon\Carbon;
use Zap\Data\RRuleFrequencyConfig;
use Zap\Facades\Zap;
use Zap\Models\Schedule;

describe('RRULE Frequency', function () {

    describe('Persistence', function () {

        it('can save and retrieve an rrule schedule', function () {
            $user = createUser();

            $schedule = Zap::for($user)
                ->named('MWF Standup')
                ->from('2025-01-06')
                ->to('2025-12-31')
                ->addPeriod('09:00', '09:30')
                ->rrule('FREQ=WEEKLY;BYDAY=MO,WE,FR')
                ->save();

            expect($schedule)->toBeInstanceOf(Schedule::class);
            expect($schedule->is_recurring)->toBeTrue();
            expect($schedule->frequency)->toBe('rrule');
            expect($schedule->frequency_config)->toBeInstanceOf(RRuleFrequencyConfig::class);
            expect($schedule->frequency_config->rrule)->toBe('FREQ=WEEKLY;BYDAY=MO,WE,FR');

            $retrieved = Schedule::find($schedule->id);
            expect($retrieved->frequency)->toBe('rrule');
            expect($retrieved->frequency_config)->toBeInstanceOf(RRuleFrequencyConfig::class);
            expect($retrieved->frequency_config->rrule)->toBe('FREQ=WEEKLY;BYDAY=MO,WE,FR');
        });

        it('can save and retrieve a monthly rrule', function () {
            $user = createUser();

            $schedule = Zap::for($user)
                ->named('Monthly 1st and 15th')
                ->from('2025-01-01')
                ->to('2025-12-31')
                ->addPeriod('10:00', '11:00')
                ->rrule('FREQ=MONTHLY;BYMONTHDAY=1,15')
                ->save();

            $retrieved = Schedule::find($schedule->id);
            expect($retrieved->frequency_config)->toBeInstanceOf(RRuleFrequencyConfig::class);
            expect($retrieved->frequency_config->rrule)->toBe('FREQ=MONTHLY;BYMONTHDAY=1,15');
        });

    });

    describe('Recurrence Logic', function () {

        it('shouldCreateInstance matches weekly BYDAY pattern', function () {
            $config = RRuleFrequencyConfig::fromArray([
                'rrule' => 'FREQ=WEEKLY;BYDAY=MO,WE,FR',
                'dtstart' => '20250101T000000',
            ]);

            // Monday
            expect($config->shouldCreateInstance(Carbon::parse('2025-01-06')))->toBeTrue();
            // Tuesday
            expect($config->shouldCreateInstance(Carbon::parse('2025-01-07')))->toBeFalse();
            // Wednesday
            expect($config->shouldCreateInstance(Carbon::parse('2025-01-08')))->toBeTrue();
            // Thursday
            expect($config->shouldCreateInstance(Carbon::parse('2025-01-09')))->toBeFalse();
            // Friday
            expect($config->shouldCreateInstance(Carbon::parse('2025-01-10')))->toBeTrue();
        });

        it('shouldCreateInstance matches monthly BYMONTHDAY pattern', function () {
            $config = RRuleFrequencyConfig::fromArray([
                'rrule' => 'FREQ=MONTHLY;BYMONTHDAY=1,15',
                'dtstart' => '20250101T000000',
            ]);

            expect($config->shouldCreateInstance(Carbon::parse('2025-01-01')))->toBeTrue();
            expect($config->shouldCreateInstance(Carbon::parse('2025-01-15')))->toBeTrue();
            expect($config->shouldCreateInstance(Carbon::parse('2025-01-10')))->toBeFalse();
            expect($config->shouldCreateInstance(Carbon::parse('2025-02-01')))->toBeTrue();
            expect($config->shouldCreateInstance(Carbon::parse('2025-02-15')))->toBeTrue();
        });

        it('shouldCreateRecurringInstance respects schedule date range', function () {
            $user = createUser();

            $schedule = Zap::for($user)
                ->named('Test')
                ->from('2025-03-01')
                ->to('2025-06-30')
                ->addPeriod('09:00', '10:00')
                ->rrule('FREQ=WEEKLY;BYDAY=MO')
                ->save();

            $config = $schedule->frequency_config;

            // Before schedule range
            expect($config->shouldCreateRecurringInstance($schedule, Carbon::parse('2025-02-24')))->toBeFalse();
            // Within range on a Monday
            expect($config->shouldCreateRecurringInstance($schedule, Carbon::parse('2025-03-03')))->toBeTrue();
            // Within range on a Tuesday
            expect($config->shouldCreateRecurringInstance($schedule, Carbon::parse('2025-03-04')))->toBeFalse();
            // After schedule range
            expect($config->shouldCreateRecurringInstance($schedule, Carbon::parse('2025-07-07')))->toBeFalse();
        });

        it('getNextRecurrence returns the next matching date', function () {
            $config = RRuleFrequencyConfig::fromArray([
                'rrule' => 'FREQ=WEEKLY;BYDAY=MO,FR',
                'dtstart' => '20250101T000000',
            ]);

            // From Monday, next should be Friday
            $next = $config->getNextRecurrence(Carbon::parse('2025-01-06'));
            expect($next->toDateString())->toBe('2025-01-10');

            // From Friday, next should be Monday
            $next = $config->getNextRecurrence(Carbon::parse('2025-01-10'));
            expect($next->toDateString())->toBe('2025-01-13');
        });

    });

    describe('Availability Integration', function () {

        it('blocks time on rrule-matched days', function () {
            $user = createUser();

            Zap::for($user)
                ->named('MWF Block')
                ->from('2025-01-01')
                ->to('2025-12-31')
                ->addPeriod('09:00', '10:00')
                ->rrule('FREQ=WEEKLY;BYDAY=MO,WE,FR')
                ->save();

            // Monday - blocked
            expect($user->isAvailableAt('2025-01-06', '09:00', '10:00'))->toBeFalse();
            // Tuesday - available
            expect($user->isAvailableAt('2025-01-07', '09:00', '10:00'))->toBeTrue();
            // Wednesday - blocked
            expect($user->isAvailableAt('2025-01-08', '09:00', '10:00'))->toBeFalse();
            // Thursday - available
            expect($user->isAvailableAt('2025-01-09', '09:00', '10:00'))->toBeTrue();
            // Friday - blocked
            expect($user->isAvailableAt('2025-01-10', '09:00', '10:00'))->toBeFalse();
        });

        it('works with complex rrule patterns like first Monday of every month', function () {
            $user = createUser();

            Zap::for($user)
                ->named('First Monday Monthly')
                ->from('2025-01-01')
                ->to('2025-12-31')
                ->addPeriod('10:00', '11:00')
                ->rrule('FREQ=MONTHLY;BYDAY=1MO')
                ->save();

            // Jan 6 2025 = 1st Monday
            expect($user->isAvailableAt('2025-01-06', '10:00', '11:00'))->toBeFalse();
            // Jan 13 = 2nd Monday
            expect($user->isAvailableAt('2025-01-13', '10:00', '11:00'))->toBeTrue();
            // Feb 3 2025 = 1st Monday
            expect($user->isAvailableAt('2025-02-03', '10:00', '11:00'))->toBeFalse();
        });

        it('works with interval-based rrules like every 2 weeks', function () {
            $user = createUser();

            Zap::for($user)
                ->named('Biweekly Tuesday')
                ->from('2025-01-07')
                ->to('2025-12-31')
                ->addPeriod('14:00', '15:00')
                ->rrule('FREQ=WEEKLY;INTERVAL=2;BYDAY=TU')
                ->save();

            // Jan 7 = start Tuesday (dtstart derived from schedule start_date)
            expect($user->isAvailableAt('2025-01-07', '14:00', '15:00'))->toBeFalse();
            // Jan 14 = 1 week later (skipped)
            expect($user->isAvailableAt('2025-01-14', '14:00', '15:00'))->toBeTrue();
            // Jan 21 = 2 weeks later
            expect($user->isAvailableAt('2025-01-21', '14:00', '15:00'))->toBeFalse();
        });

    });

    describe('Builder', function () {

        it('sets correct attributes via rrule() method', function () {
            $user = createUser();

            $builder = Zap::for($user)
                ->named('Test')
                ->from('2025-01-01')
                ->addPeriod('09:00', '10:00')
                ->rrule('FREQ=DAILY;INTERVAL=3');

            $attrs = $builder->getAttributes();
            expect($attrs['is_recurring'])->toBeTrue();
            expect($attrs['frequency'])->toBe('rrule');
            expect($attrs['frequency_config'])->toBeInstanceOf(RRuleFrequencyConfig::class);
            expect($attrs['frequency_config']->rrule)->toBe('FREQ=DAILY;INTERVAL=3');
        });

    });

});
