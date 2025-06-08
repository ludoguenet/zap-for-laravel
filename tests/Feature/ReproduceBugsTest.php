<?php

use Zap\Exceptions\ScheduleConflictException;
use Zap\Facades\Zap;
use Zap\Models\Schedule;

describe('Reproduce Specific Bugs', function () {

    beforeEach(function () {
        config([
            'zap.conflict_detection.enabled' => true,
            'zap.conflict_detection.buffer_minutes' => 0,
        ]);
    });

    describe('Bug 1: False negative - should trigger exception but does not', function () {

        it('should detect overlap when daily schedule conflicts with weekly recurring on specific days', function () {
            $user = createUser();

            // User works Monday, Wednesday, Friday from 8:00-12:00 and 14:00-18:00
            Zap::for($user)
                ->named('Work Schedule')
                ->from('2024-01-01')
                ->to('2024-12-31')
                ->addPeriod('08:00', '12:00')
                ->addPeriod('14:00', '18:00')
                ->weekly(['monday', 'wednesday', 'friday'])
                ->save();

            // This SHOULD throw an exception because it overlaps on Mon/Wed/Fri
            // But currently it doesn't (FALSE NEGATIVE)
            expect(function () use ($user) {
                Zap::for($user)
                    ->named('Daily Afternoon')
                    ->from('2024-01-01')
                    ->to('2024-12-31')
                    ->addPeriod('14:00', '18:00')
                    ->daily()
                    ->noOverlap()
                    ->save();
            })->toThrow(ScheduleConflictException::class);
        });

    });

    describe('Bug 2: False positive - should NOT trigger exception but does', function () {

        it('should NOT detect overlap when scheduling on non-recurring days', function () {
            $user = createUser();

            // User works Monday, Wednesday, Friday from 8:00-12:00 and 14:00-18:00
            Zap::for($user)
                ->named('Work Schedule')
                ->from('2024-01-01')
                ->to('2024-12-31')
                ->addPeriod('08:00', '12:00')
                ->addPeriod('14:00', '18:00')
                ->weekly(['monday', 'wednesday', 'friday'])
                ->save();

            // This SHOULD NOT throw an exception because user doesn't work on Sunday
            // But currently it does (FALSE POSITIVE)
            $schedule = Zap::for($user)
                ->named('Sunday Meeting')
                ->from('2024-01-07') // Sunday
                ->addPeriod('14:00', '18:00')
                ->noOverlap()
                ->save();

            expect($schedule)->toBeInstanceOf(Schedule::class);
            expect($schedule->name)->toBe('Sunday Meeting');
        });

    });

    describe('Additional tests to validate fixes', function () {

        it('should detect overlap on Tuesday when weekly recurring includes Tuesday', function () {
            $user = createUser();

            // User works Tuesday, Thursday from 9:00-17:00
            Zap::for($user)
                ->named('Work Schedule')
                ->from('2024-01-01')
                ->to('2024-12-31')
                ->addPeriod('09:00', '17:00')
                ->weekly(['tuesday', 'thursday'])
                ->save();

            // This should conflict on Tuesday
            expect(function () use ($user) {
                Zap::for($user)
                    ->from('2024-01-02') // Tuesday
                    ->addPeriod('10:00', '11:00')
                    ->noOverlap()
                    ->save();
            })->toThrow(ScheduleConflictException::class);
        });

        it('should NOT detect overlap on Wednesday when weekly recurring excludes Wednesday', function () {
            $user = createUser();

            // User works Tuesday, Thursday from 9:00-17:00
            Zap::for($user)
                ->named('Work Schedule')
                ->from('2024-01-01')
                ->to('2024-12-31')
                ->addPeriod('09:00', '17:00')
                ->weekly(['tuesday', 'thursday'])
                ->save();

            // This should NOT conflict on Wednesday
            $schedule = Zap::for($user)
                ->from('2024-01-03') // Wednesday
                ->addPeriod('10:00', '11:00')
                ->noOverlap()
                ->save();

            expect($schedule)->toBeInstanceOf(Schedule::class);
        });

    });

});
