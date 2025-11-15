<?php

/**
 * Tests for Issue #31: Recurring schedule querying
 *
 * Issue: When querying recurring schedules with forDate(), the query:
 * 1. Returns schedules even when the query date doesn't match the recurrence pattern
 *    (e.g., returns Monday/Friday schedule when querying for Wednesday)
 * 2. Returns periods with the wrong date (start_date instead of the actual recurring date)
 *
 * These tests reproduce the bugs and should pass once the issue is fixed.
 *
 * @see https://github.com/ludoguenet/zap-for-laravel/issues/31
 */

use Zap\Facades\Zap;
use Zap\Models\Schedule;

describe('Recurring Schedule Query Issue #31', function () {

    it('should not return schedule when querying for date that does not match weekly recurrence', function () {
        // Issue: Schedule with weekly recurrence on Monday and Friday
        // Querying for Wednesday (2025-10-15) should return no schedules
        // But currently returns schedule with period date '2025-10-01' (start date)

        $worker = createUser();

        Zap::for($worker)
            ->named('Availability')
            ->availability()
            ->from('2025-10-01')
            ->to('2025-12-31')
            ->addPeriod('09:00', '13:00')
            ->weekly(['monday', 'friday'])
            ->noOverlap()
            ->save();

        // 2025-10-15 is a Wednesday, should NOT match Monday/Friday schedule
        $schedules = Schedule::active()
            ->where('schedule_type', 'availability')
            ->forDate('2025-10-15')
            ->whereHas('periods', function ($query) {
                $query->where('start_time', '<=', '10:00')
                    ->where('end_time', '>=', '11:00');
            })
            ->with('periods')
            ->get();

        // Should return no schedules since Wednesday is not in the weekly recurrence
        expect($schedules)->toHaveCount(0, 'Should not return schedule for Wednesday when recurrence is Monday/Friday');

        // Verify the periods that would be returned have the wrong date
        if ($schedules->isNotEmpty()) {
            foreach ($schedules as $schedule) {
                foreach ($schedule->periods as $period) {
                    // This should fail - period date should not be '2025-10-01' when querying for '2025-10-15'
                    expect($period->date->format('Y-m-d'))->not->toBe('2025-10-01', 'Period date should match query date, not start date');
                }
            }
        }
    });

    it('should return schedule with correct period date when querying for matching weekly recurrence', function () {
        // When schedule is set to recur on Wednesday, querying for Wednesday should return schedule
        // But period date should be '2025-10-15' (query date), not '2025-10-01' (start date)

        $worker = createUser();

        Zap::for($worker)
            ->named('Availability')
            ->availability()
            ->from('2025-10-01')
            ->to('2025-12-31')
            ->addPeriod('09:00', '13:00')
            ->weekly(['wednesday'])
            ->noOverlap()
            ->save();

        // 2025-10-15 is a Wednesday, SHOULD match Wednesday schedule
        $schedules = Schedule::active()
            ->where('schedule_type', 'availability')
            ->forDate('2025-10-15')
            ->whereHas('periods', function ($query) {
                $query->where('start_time', '<=', '10:00')
                    ->where('end_time', '>=', '11:00');
            })
            ->with('periods')
            ->get();

        // Should return schedule since Wednesday matches
        expect($schedules)->toHaveCount(1, 'Should return schedule for Wednesday when recurrence is Wednesday');

        // Verify the periods have the correct date (query date, not start date)
        foreach ($schedules as $schedule) {
            foreach ($schedule->periods as $period) {
                // Period date should be '2025-10-15' (query date), not '2025-10-01' (start date)
                expect($period->date->format('Y-m-d'))->toBe('2025-10-15', 'Period date should be query date, not start date');
            }
        }
    })->todo();

    it('should correctly filter recurring schedules by day of week', function () {
        $worker = createUser();

        // Create schedule that recurs on Monday and Friday only
        Zap::for($worker)
            ->named('MWF Availability')
            ->availability()
            ->from('2025-10-01')
            ->to('2025-12-31')
            ->addPeriod('09:00', '13:00')
            ->weekly(['monday', 'friday'])
            ->save();

        // Test Monday (2025-10-06 is a Monday)
        $mondaySchedules = Schedule::active()
            ->where('schedule_type', 'availability')
            ->forDate('2025-10-06')
            ->whereHas('periods', function ($query) {
                $query->where('start_time', '<=', '10:00')
                    ->where('end_time', '>=', '11:00');
            })
            ->get();

        expect($mondaySchedules)->toHaveCount(1, 'Should return schedule for Monday');

        // Test Tuesday (2025-10-07 is a Tuesday) - should NOT match
        $tuesdaySchedules = Schedule::active()
            ->where('schedule_type', 'availability')
            ->forDate('2025-10-07')
            ->whereHas('periods', function ($query) {
                $query->where('start_time', '<=', '10:00')
                    ->where('end_time', '>=', '11:00');
            })
            ->get();

        expect($tuesdaySchedules)->toHaveCount(0, 'Should not return schedule for Tuesday');

        // Test Friday (2025-10-10 is a Friday) - should match
        $fridaySchedules = Schedule::active()
            ->where('schedule_type', 'availability')
            ->forDate('2025-10-10')
            ->whereHas('periods', function ($query) {
                $query->where('start_time', '<=', '10:00')
                    ->where('end_time', '>=', '11:00');
            })
            ->get();

        expect($fridaySchedules)->toHaveCount(1, 'Should return schedule for Friday');
    });

    it('should return periods with correct date for recurring schedules', function () {
        $worker = createUser();

        // Create schedule that recurs on Wednesday
        Zap::for($worker)
            ->named('Wednesday Availability')
            ->availability()
            ->from('2025-10-01')
            ->to('2025-12-31')
            ->addPeriod('09:00', '13:00')
            ->weekly(['wednesday'])
            ->save();

        // Query for different Wednesdays
        $dates = ['2025-10-15', '2025-10-22', '2025-10-29'];

        foreach ($dates as $queryDate) {
            $schedules = Schedule::active()
                ->where('schedule_type', 'availability')
                ->forDate($queryDate)
                ->whereHas('periods', function ($query) {
                    $query->where('start_time', '<=', '10:00')
                        ->where('end_time', '>=', '11:00');
                })
                ->with('periods')
                ->get();

            expect($schedules)->toHaveCount(1, "Should return schedule for {$queryDate}");

            foreach ($schedules as $schedule) {
                foreach ($schedule->periods as $period) {
                    // Period date should match the query date, not the start date
                    expect($period->date->format('Y-m-d'))->toBe($queryDate, "Period date should be {$queryDate}, not start date");
                }
            }
        }
    })->todo();

    it('should handle multiple recurring schedules correctly', function () {
        $worker = createUser();

        // Create Monday/Friday schedule
        Zap::for($worker)
            ->named('MWF Schedule')
            ->availability()
            ->from('2025-10-01')
            ->to('2025-12-31')
            ->addPeriod('09:00', '13:00')
            ->weekly(['monday', 'friday'])
            ->save();

        // Create Tuesday/Thursday schedule
        Zap::for($worker)
            ->named('TTh Schedule')
            ->availability()
            ->from('2025-10-01')
            ->to('2025-12-31')
            ->addPeriod('14:00', '18:00')
            ->weekly(['tuesday', 'thursday'])
            ->save();

        // Query for Monday - should only return MWF schedule
        $mondaySchedules = Schedule::active()
            ->where('schedule_type', 'availability')
            ->forDate('2025-10-06')
            ->whereHas('periods', function ($query) {
                $query->where('start_time', '<=', '10:00')
                    ->where('end_time', '>=', '11:00');
            })
            ->get();

        expect($mondaySchedules)->toHaveCount(1, 'Should return only MWF schedule for Monday');
        expect($mondaySchedules->first()->name)->toBe('MWF Schedule');

        // Query for Tuesday - should only return TTh schedule
        $tuesdaySchedules = Schedule::active()
            ->where('schedule_type', 'availability')
            ->forDate('2025-10-07')
            ->whereHas('periods', function ($query) {
                $query->where('start_time', '<=', '15:00')
                    ->where('end_time', '>=', '16:00');
            })
            ->get();

        expect($tuesdaySchedules)->toHaveCount(1, 'Should return only TTh schedule for Tuesday');
        expect($tuesdaySchedules->first()->name)->toBe('TTh Schedule');

        // Query for Wednesday - should return no schedules
        $wednesdaySchedules = Schedule::active()
            ->where('schedule_type', 'availability')
            ->forDate('2025-10-08')
            ->whereHas('periods', function ($query) {
                $query->where('start_time', '<=', '10:00')
                    ->where('end_time', '>=', '11:00');
            })
            ->get();

        expect($wednesdaySchedules)->toHaveCount(0, 'Should return no schedules for Wednesday');
    });

});
