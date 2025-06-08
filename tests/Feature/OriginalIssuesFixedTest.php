<?php

use Zap\Exceptions\ScheduleConflictException;
use Zap\Facades\Zap;
use Zap\Models\Schedule;

describe('Original Issues Fixed', function () {

    beforeEach(function () {
        config([
            'zap.conflict_detection.enabled' => true,
            'zap.conflict_detection.buffer_minutes' => 0,
        ]);
    });

    it('FIXED: User working Mon/Wed/Fri conflicts with daily schedule on those days', function () {
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

        // Second user tries to schedule daily from 14:00-18:00 with no overlap
        // This SHOULD trigger an exception because it overlaps on Mon/Wed/Fri
        // BEFORE FIX: This would NOT trigger an exception (false negative)
        // AFTER FIX: This correctly triggers an exception
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

    it('FIXED: User working Mon/Wed/Fri does NOT conflict with Sunday schedule', function () {
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

        // Second user tries to schedule on Sunday from 14:00-18:00 with no overlap
        // This SHOULD NOT trigger an exception because user doesn't work on Sunday
        // BEFORE FIX: This would trigger an exception (false positive)
        // AFTER FIX: This correctly does NOT trigger an exception
        $schedule = Zap::for($user)
            ->named('Sunday Meeting')
            ->from('2024-01-07') // Sunday
            ->addPeriod('14:00', '18:00')
            ->noOverlap()
            ->save();

        expect($schedule)->toBeInstanceOf(Schedule::class);
        expect($schedule->name)->toBe('Sunday Meeting');
    });

    it('demonstrates the fix works across multiple weeks', function () {
        $user = createUser();

        // User works Monday, Wednesday, Friday from 9:00-17:00
        Zap::for($user)
            ->named('Work Schedule')
            ->from('2024-01-01')
            ->to('2024-12-31')
            ->addPeriod('09:00', '17:00')
            ->weekly(['monday', 'wednesday', 'friday'])
            ->save();

        // Should conflict on Monday in week 3
        expect(function () use ($user) {
            Zap::for($user)
                ->from('2024-01-15') // Monday, week 3
                ->addPeriod('10:00', '11:00')
                ->noOverlap()
                ->save();
        })->toThrow(ScheduleConflictException::class);

        // Should NOT conflict on Tuesday in week 3
        $tuesdaySchedule = Zap::for($user)
            ->from('2024-01-16') // Tuesday, week 3
            ->addPeriod('10:00', '11:00')
            ->noOverlap()
            ->save();

        expect($tuesdaySchedule)->toBeInstanceOf(Schedule::class);

        // Should conflict on Wednesday in week 3
        expect(function () use ($user) {
            Zap::for($user)
                ->from('2024-01-17') // Wednesday, week 3
                ->addPeriod('10:00', '11:00')
                ->noOverlap()
                ->save();
        })->toThrow(ScheduleConflictException::class);

        // Should NOT conflict on Thursday in week 3
        $thursdaySchedule = Zap::for($user)
            ->from('2024-01-18') // Thursday, week 3
            ->addPeriod('10:00', '11:00')
            ->noOverlap()
            ->save();

        expect($thursdaySchedule)->toBeInstanceOf(Schedule::class);

        // Should conflict on Friday in week 3
        expect(function () use ($user) {
            Zap::for($user)
                ->from('2024-01-19') // Friday, week 3
                ->addPeriod('10:00', '11:00')
                ->noOverlap()
                ->save();
        })->toThrow(ScheduleConflictException::class);
    });

});
