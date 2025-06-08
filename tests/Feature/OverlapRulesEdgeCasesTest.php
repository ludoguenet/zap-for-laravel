<?php

use Zap\Exceptions\ScheduleConflictException;
use Zap\Facades\Zap;
use Zap\Models\Schedule;

describe('Overlap Rules Edge Cases', function () {

    beforeEach(function () {
        // Reset config to ensure consistent test environment
        config([
            'zap.conflict_detection.enabled' => true,
            'zap.conflict_detection.buffer_minutes' => 0,
        ]);
    });

    describe('Weekly Recurring Schedule Conflicts', function () {

        it('should detect overlap when second user schedules daily on recurring schedule days', function () {
            $userA = createUser();
            $userB = createUser();

            // User A works Monday, Wednesday, Friday from 8:00-12:00 and 14:00-18:00
            Zap::for($userA)
                ->named('User A Work Schedule')
                ->from('2024-01-01')
                ->to('2024-12-31')
                ->addPeriod('08:00', '12:00')
                ->addPeriod('14:00', '18:00')
                ->weekly(['monday', 'wednesday', 'friday'])
                ->save();

            // User B tries to schedule daily from 14:00-18:00 with no overlap
            // This SHOULD throw an exception because it overlaps on Mon/Wed/Fri
            expect(function () use ($userA) {
                Zap::for($userA) // Same user, should conflict
                    ->named('User B Daily Schedule')
                    ->from('2024-01-01')
                    ->to('2024-12-31')
                    ->addPeriod('14:00', '18:00')
                    ->daily()
                    ->noOverlap()
                    ->save();
            })->toThrow(ScheduleConflictException::class);
        });

        it('should NOT detect overlap when second user schedules on non-recurring days only', function () {
            $userA = createUser();

            // User A works Monday, Wednesday, Friday from 8:00-12:00 and 14:00-18:00
            Zap::for($userA)
                ->named('User A Work Schedule')
                ->from('2024-01-01')
                ->to('2024-12-31')
                ->addPeriod('08:00', '12:00')
                ->addPeriod('14:00', '18:00')
                ->weekly(['monday', 'wednesday', 'friday'])
                ->save();

            // User A tries to schedule on Sunday (non-recurring day) with no overlap
            // This SHOULD NOT throw an exception because User A doesn't work on Sunday
            $schedule = Zap::for($userA) // Same user
                ->named('Sunday Schedule')
                ->from('2024-01-07') // Sunday, January 7, 2024
                ->addPeriod('14:00', '18:00')
                ->noOverlap()
                ->save();

            expect($schedule)->toBeInstanceOf(Schedule::class);
            expect($schedule->name)->toBe('Sunday Schedule');
        });

        it('should handle multiple period conflicts correctly', function () {
            $user = createUser();

            // User has recurring schedule with multiple periods on specific days
            Zap::for($user)
                ->named('Work Schedule')
                ->from('2024-01-01')
                ->to('2024-12-31')
                ->addPeriod('08:00', '12:00') // Morning
                ->addPeriod('14:00', '18:00') // Afternoon
                ->weekly(['monday', 'wednesday', 'friday'])
                ->save();

            // Try to schedule overlapping with morning period on a working day
            expect(function () use ($user) {
                Zap::for($user)
                    ->from('2024-01-01') // Monday
                    ->addPeriod('10:00', '11:00') // Overlaps with 08:00-12:00
                    ->noOverlap()
                    ->save();
            })->toThrow(ScheduleConflictException::class);

            // Try to schedule overlapping with afternoon period on a working day
            expect(function () use ($user) {
                Zap::for($user)
                    ->from('2024-01-03') // Wednesday
                    ->addPeriod('15:00', '16:00') // Overlaps with 14:00-18:00
                    ->noOverlap()
                    ->save();
            })->toThrow(ScheduleConflictException::class);

            // Schedule during lunch break on working day should succeed
            $lunchSchedule = Zap::for($user)
                ->from('2024-01-05') // Friday
                ->addPeriod('12:00', '14:00') // Lunch break
                ->noOverlap()
                ->save();

            expect($lunchSchedule)->toBeInstanceOf(Schedule::class);
        });

        it('should correctly identify conflicts across different weeks', function () {
            $user = createUser();

            // Recurring schedule for several weeks
            Zap::for($user)
                ->named('Weekly Meeting')
                ->from('2024-01-01')
                ->to('2024-02-29')
                ->addPeriod('10:00', '11:00')
                ->weekly(['tuesday'])
                ->save();

            // Try to schedule on a Tuesday 3 weeks later - should conflict
            expect(function () use ($user) {
                Zap::for($user)
                    ->from('2024-01-23') // Tuesday, 3 weeks later
                    ->addPeriod('10:30', '11:30') // Overlaps with 10:00-11:00
                    ->noOverlap()
                    ->save();
            })->toThrow(ScheduleConflictException::class);

            // Schedule on Wednesday same week should succeed
            $wednesdaySchedule = Zap::for($user)
                ->from('2024-01-24') // Wednesday
                ->addPeriod('10:00', '11:00') // Same time, different day
                ->noOverlap()
                ->save();

            expect($wednesdaySchedule)->toBeInstanceOf(Schedule::class);
        });

    });

    describe('Daily Recurring Schedule Conflicts', function () {

        it('should detect conflicts with daily recurring schedules', function () {
            $user = createUser();

            // Daily work schedule
            Zap::for($user)
                ->named('Daily Work')
                ->from('2024-01-01')
                ->to('2024-12-31')
                ->addPeriod('09:00', '17:00')
                ->daily()
                ->save();

            // Try to schedule during work hours - should conflict
            expect(function () use ($user) {
                Zap::for($user)
                    ->from('2024-01-15')
                    ->addPeriod('10:00', '11:00')
                    ->noOverlap()
                    ->save();
            })->toThrow(ScheduleConflictException::class);
        });

        it('should handle weekday-only recurring schedules', function () {
            $user = createUser();

            // Weekday work schedule (Monday through Friday)
            Zap::for($user)
                ->named('Weekday Work')
                ->from('2024-01-01')
                ->to('2024-12-31')
                ->addPeriod('09:00', '17:00')
                ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
                ->save();

            // Try to schedule on a Saturday - should succeed
            $weekendSchedule = Zap::for($user)
                ->from('2024-01-06') // Saturday
                ->addPeriod('10:00', '11:00')
                ->noOverlap()
                ->save();

            expect($weekendSchedule)->toBeInstanceOf(Schedule::class);

            // Try to schedule on a weekday - should conflict
            expect(function () use ($user) {
                Zap::for($user)
                    ->from('2024-01-08') // Monday
                    ->addPeriod('10:00', '11:00')
                    ->noOverlap()
                    ->save();
            })->toThrow(ScheduleConflictException::class);
        });

    });

    describe('Monthly Recurring Schedule Conflicts', function () {

        it('should detect conflicts with monthly recurring schedules', function () {
            $user = createUser();

            // Monthly meeting on the 15th
            Zap::for($user)
                ->named('Monthly Meeting')
                ->from('2024-01-15')
                ->to('2024-12-31')
                ->addPeriod('14:00', '16:00')
                ->monthly(['day_of_month' => 15])
                ->save();

            // Try to schedule on March 15th - should conflict
            expect(function () use ($user) {
                Zap::for($user)
                    ->from('2024-03-15')
                    ->addPeriod('15:00', '17:00')
                    ->noOverlap()
                    ->save();
            })->toThrow(ScheduleConflictException::class);

            // Schedule on March 16th should succeed
            $nextDaySchedule = Zap::for($user)
                ->from('2024-03-16')
                ->addPeriod('14:00', '16:00')
                ->noOverlap()
                ->save();

            expect($nextDaySchedule)->toBeInstanceOf(Schedule::class);
        });

    });

    describe('Complex Overlapping Scenarios', function () {

        it('should handle overlapping schedules with different frequencies', function () {
            $user = createUser();

            // Weekly recurring schedule
            Zap::for($user)
                ->named('Weekly Team Meeting')
                ->from('2024-01-01')
                ->to('2024-12-31')
                ->addPeriod('10:00', '11:00')
                ->weekly(['monday'])
                ->save();

            // Daily recurring schedule that should conflict on Mondays
            expect(function () use ($user) {
                Zap::for($user)
                    ->named('Daily Standup')
                    ->from('2024-01-01')
                    ->to('2024-12-31')
                    ->addPeriod('10:30', '11:30')
                    ->daily()
                    ->noOverlap()
                    ->save();
            })->toThrow(ScheduleConflictException::class);
        });

        it('should correctly handle end date boundaries in recurring schedules', function () {
            $user = createUser();

            // Short-term recurring schedule
            Zap::for($user)
                ->named('Short Term Project')
                ->from('2024-01-01')
                ->to('2024-01-31') // Only January
                ->addPeriod('09:00', '10:00')
                ->weekly(['monday'])
                ->save();

            // Try to schedule on a Monday in February - should succeed (no conflict)
            $februarySchedule = Zap::for($user)
                ->from('2024-02-05') // Monday in February
                ->addPeriod('09:00', '10:00')
                ->noOverlap()
                ->save();

            expect($februarySchedule)->toBeInstanceOf(Schedule::class);

            // Try to schedule on a Monday in January - should conflict
            expect(function () use ($user) {
                Zap::for($user)
                    ->from('2024-01-08') // Monday in January
                    ->addPeriod('09:30', '10:30')
                    ->noOverlap()
                    ->save();
            })->toThrow(ScheduleConflictException::class);
        });

    });

    describe('Edge Cases with Time Boundaries', function () {

        it('should handle adjacent time periods correctly', function () {
            $user = createUser();

            // Morning block
            Zap::for($user)
                ->from('2024-01-01')
                ->addPeriod('09:00', '12:00')
                ->save();

            // Adjacent afternoon block should succeed
            $afternoonSchedule = Zap::for($user)
                ->from('2024-01-01')
                ->addPeriod('12:00', '15:00') // Starts exactly when morning ends
                ->noOverlap()
                ->save();

            expect($afternoonSchedule)->toBeInstanceOf(Schedule::class);

            // Overlapping by one minute should fail
            expect(function () use ($user) {
                Zap::for($user)
                    ->from('2024-01-01')
                    ->addPeriod('11:59', '13:00') // Overlaps by 1 minute
                    ->noOverlap()
                    ->save();
            })->toThrow(ScheduleConflictException::class);
        });

        it('should handle midnight boundary correctly', function () {
            $user = createUser();

            // Late evening schedule
            Zap::for($user)
                ->from('2024-01-01')
                ->addPeriod('23:00', '23:59')
                ->save();

            // Early morning next day should succeed
            $morningSchedule = Zap::for($user)
                ->from('2024-01-02')
                ->addPeriod('00:00', '01:00')
                ->noOverlap()
                ->save();

            expect($morningSchedule)->toBeInstanceOf(Schedule::class);
        });

    });

});
