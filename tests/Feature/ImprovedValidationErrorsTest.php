<?php

use Zap\Exceptions\InvalidScheduleException;
use Zap\Facades\Zap;

describe('Improved Validation Error Messages', function () {

    beforeEach(function () {
        config([
            'zap.validation.require_future_dates' => true,
            'zap.validation.min_period_duration' => 15,
            'zap.validation.max_period_duration' => 480,
            'zap.validation.allow_overlapping_periods' => false,
        ]);
    });

    it('provides clear error message for missing start date', function () {
        $user = createUser();

        try {
            Zap::for($user)
                ->named('Test Schedule')
                ->addPeriod('09:00', '10:00')
                ->save();
        } catch (\InvalidArgumentException $e) {
            // ScheduleBuilder throws InvalidArgumentException before validation
            expect($e->getMessage())->toBe('Start date must be set using from() method');
        }
    });

    it('provides clear error message for invalid time format', function () {
        $user = createUser();

        try {
            Zap::for($user)
                ->from(now()->addDay()->toDateString())
                ->addPeriod('9am', '10am') // Invalid format
                ->save();
        } catch (InvalidScheduleException $e) {
            expect($e->getMessage())->toContain('Invalid start time format');
            expect($e->getMessage())->toContain('9am');
            expect($e->getMessage())->toContain('Please use HH:MM format');

            $errors = $e->getErrors();
            expect($errors)->toHaveKey('periods.0.start_time');
            expect($errors['periods.0.start_time'])->toContain('9am');
        }
    });

    it('provides clear error message for end time before start time', function () {
        $user = createUser();

        try {
            Zap::for($user)
                ->from(now()->addDay()->toDateString())
                ->addPeriod('10:00', '09:00') // End before start
                ->save();
        } catch (InvalidScheduleException $e) {
            expect($e->getMessage())->toContain('End time (09:00) must be after start time (10:00)');

            $errors = $e->getErrors();
            expect($errors)->toHaveKey('periods.0.end_time');
            expect($errors['periods.0.end_time'])->toBe('End time (09:00) must be after start time (10:00)');
        }
    });

    it('provides clear error message for period too short', function () {
        $user = createUser();

        try {
            Zap::for($user)
                ->from(now()->addDay()->toDateString())
                ->addPeriod('09:00', '09:10') // Only 10 minutes, minimum is 15
                ->save();
        } catch (InvalidScheduleException $e) {
            expect($e->getMessage())->toContain('Period is too short (10 minutes)');
            expect($e->getMessage())->toContain('Minimum duration is 15 minutes');

            $errors = $e->getErrors();
            expect($errors)->toHaveKey('periods.0.duration');
        }
    });

    it('provides clear error message for period too long', function () {
        $user = createUser();

        try {
            Zap::for($user)
                ->from(now()->addDay()->toDateString())
                ->addPeriod('09:00', '17:30') // 8.5 hours = 510 minutes, maximum is 8 hours (480 minutes)
                ->maxDuration(480) // Set max duration rule
                ->save();
        } catch (InvalidScheduleException $e) {
            expect($e->getMessage())->toContain('Period 09:00-17:30 is too long (8.5 hours)');
            expect($e->getMessage())->toContain('Maximum allowed is 8 hours');

            $errors = $e->getErrors();
            expect($errors)->toHaveKey('periods.0.max_duration');
        }
    });

    it('provides clear error message for overlapping periods within same schedule', function () {
        $user = createUser();

        try {
            Zap::for($user)
                ->from(now()->addDay()->toDateString())
                ->addPeriod('09:00', '11:00')
                ->addPeriod('10:00', '12:00') // Overlaps with first period
                ->save();
        } catch (InvalidScheduleException $e) {
            expect($e->getMessage())->toContain('Period 0 (09:00-11:00) overlaps with period 1 (10:00-12:00)');

            $errors = $e->getErrors();
            expect($errors)->toHaveKey('periods.0.overlap');
        }
    });

    it('provides clear error message for working hours violation', function () {
        $user = createUser();

        try {
            Zap::for($user)
                ->from(now()->addDay()->toDateString())
                ->addPeriod('08:00', '09:00') // Before working hours
                ->workingHoursOnly('09:00', '17:00')
                ->save();
        } catch (InvalidScheduleException $e) {
            expect($e->getMessage())->toContain('Period 08:00-09:00 is outside working hours (09:00-17:00)');

            $errors = $e->getErrors();
            expect($errors)->toHaveKey('periods.0.working_hours');
        }
    });

    it('provides clear error message for weekend violation', function () {
        config(['zap.default_rules.no_weekends.enabled' => true]);

        $user = createUser();

        // Find the next Saturday
        $nextSaturday = now()->next(\Carbon\Carbon::SATURDAY);

        try {
            Zap::for($user)
                ->from($nextSaturday->toDateString())
                ->addPeriod('09:00', '10:00')
                ->noWeekends()
                ->save();
        } catch (InvalidScheduleException $e) {
            expect($e->getMessage())->toContain('Schedule cannot start on Saturday');
            expect($e->getMessage())->toContain('Weekend schedules are not allowed');

            $errors = $e->getErrors();
            expect($errors)->toHaveKey('start_date');
        }
    });

    it('provides clear error message for past date', function () {
        $user = createUser();

        try {
            Zap::for($user)
                ->from(now()->subDay()->toDateString()) // Yesterday (past date)
                ->addPeriod('09:00', '10:00')
                ->save();
        } catch (InvalidScheduleException $e) {
            expect($e->getMessage())->toContain('The schedule cannot be created in the past');
            expect($e->getMessage())->toContain('Please choose a future date');

            $errors = $e->getErrors();
            expect($errors)->toHaveKey('start_date');
        }
    });

    it('provides clear error message for schedule conflicts', function () {
        $user = createUser();
        $futureDate = now()->addWeek()->toDateString();

        // Create existing schedule
        Zap::for($user)
            ->named('Existing Meeting')
            ->from($futureDate)
            ->addPeriod('09:00', '10:00')
            ->save();

        // Try to create conflicting schedule
        try {
            Zap::for($user)
                ->named('Conflicting Meeting')
                ->from($futureDate)
                ->addPeriod('09:30', '10:30') // Overlaps with existing
                ->noOverlap()
                ->save();
        } catch (\Zap\Exceptions\ScheduleConflictException $e) {
            expect($e->getMessage())->toContain('Schedule conflict detected!');
            expect($e->getMessage())->toContain('New schedule'); // The temp schedule doesn't have the name set
            expect($e->getMessage())->toContain('Existing Meeting');
            expect($e->getConflictingSchedules())->toHaveCount(1);
            expect($e->getConflictingSchedules()[0]->name)->toBe('Existing Meeting');
        }
    });

    it('provides detailed error summary with multiple errors', function () {
        $user = createUser();

        try {
            Zap::for($user)
                ->from(now()->addDay()->toDateString())
                ->addPeriod('24:00', '09:00') // Invalid time format (error 1) - 24:00 is invalid
                ->addPeriod('', '10:00') // Missing start time (error 2)
                ->save();
        } catch (InvalidScheduleException $e) {
            $message = $e->getMessage();

            // Should mention multiple errors
            expect($message)->toContain('Schedule validation failed with');
            expect($message)->toContain('errors:');

            // Should list all errors with bullet points
            expect($message)->toContain('•');
            expect($message)->toContain('Invalid start time format');
            expect($message)->toContain('A start time is required');

            // Should have all errors in the errors array
            $errors = $e->getErrors();
            expect(count($errors))->toBeGreaterThanOrEqual(2);
        }
    });

});
