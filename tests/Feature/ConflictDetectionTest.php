<?php

use Zap\Exceptions\ScheduleConflictException;
use Zap\Facades\Zap;
use Zap\Models\Schedule;

describe('Conflict Detection', function () {

    it('detects overlapping time periods on same date', function () {
        $user = createUser();

        // Create first schedule
        Zap::for($user)
            ->from('2025-01-01')
            ->addPeriod('09:00', '11:00')
            ->save();

        // This should conflict
        expect(function () use ($user) {
            Zap::for($user)
                ->from('2025-01-01')
                ->addPeriod('10:00', '12:00') // Overlaps with 09:00-11:00
                ->noOverlap()
                ->save();
        })->toThrow(ScheduleConflictException::class);
    });

    it('allows non-overlapping periods on same date', function () {
        $user = createUser();

        // Create first schedule
        $schedule1 = Zap::for($user)
            ->from('2025-01-01')
            ->addPeriod('09:00', '10:00')
            ->save();

        // This should not conflict
        $schedule2 = Zap::for($user)
            ->from('2025-01-01')
            ->addPeriod('11:00', '12:00') // No overlap with 09:00-10:00
            ->save();

        expect($schedule1)->toBeInstanceOf(Schedule::class);
        expect($schedule2)->toBeInstanceOf(Schedule::class);
    });

    it('allows overlapping periods on different dates', function () {
        $user = createUser();

        // Create first schedule
        $schedule1 = Zap::for($user)
            ->from('2025-01-01')
            ->addPeriod('09:00', '11:00')
            ->save();

        // This should not conflict (different date)
        $schedule2 = Zap::for($user)
            ->from('2025-01-02')
            ->addPeriod('09:00', '11:00') // Same time, different date
            ->save();

        expect($schedule1)->toBeInstanceOf(Schedule::class);
        expect($schedule2)->toBeInstanceOf(Schedule::class);
    });

    it('detects conflicts with buffer time', function () {
        config(['zap.conflict_detection.buffer_minutes' => 15]);

        $user = createUser();

        // Create first schedule
        Zap::for($user)
            ->from('2025-01-01')
            ->addPeriod('09:00', '10:00')
            ->save();

        // This should conflict due to buffer
        expect(function () use ($user) {
            Zap::for($user)
                ->from('2025-01-01')
                ->addPeriod('10:00', '11:00') // Would normally be OK, but buffer makes it conflict
                ->noOverlap()
                ->save();
        })->toThrow(ScheduleConflictException::class);
    });

    it('finds all conflicting schedules', function () {
        $user = createUser();

        // Create multiple existing appointment schedules
        $schedule1 = Zap::for($user)
            ->named('Meeting 1')
            ->appointment()
            ->from('2025-01-01')
            ->addPeriod('09:00', '10:00')
            ->save();

        $schedule2 = Zap::for($user)
            ->named('Meeting 2')
            ->appointment()
            ->from('2025-01-01')
            ->addPeriod('10:30', '11:30')
            ->save();

        // Create a new appointment schedule that overlaps with both
        $newSchedule = new Schedule([
            'schedulable_type' => get_class($user),
            'schedulable_id' => $user->getKey(),
            'start_date' => '2025-01-01',
            'name' => 'Conflicting Meeting',
            'schedule_type' => Schedule::TYPE_APPOINTMENT,
        ]);

        // Add periods that overlap with both existing schedules
        $newSchedule->setRelation('periods', collect([
            new \Zap\Models\SchedulePeriod([
                'date' => '2025-01-01',
                'start_time' => '09:30', // Overlaps with Meeting 1 (09:00-10:00)
                'end_time' => '11:00',   // Overlaps with Meeting 2 (10:30-11:30)
            ]),
        ]));

        $conflicts = Zap::findConflicts($newSchedule);
        expect($conflicts)->toHaveCount(2);
    });

    it('ignores conflicts when detection is disabled', function () {
        config(['zap.conflict_detection.enabled' => false]);

        $user = createUser();

        // Create first schedule
        Zap::for($user)
            ->from('2025-01-01')
            ->addPeriod('09:00', '11:00')
            ->save();

        // This should succeed when conflict detection is disabled
        $schedule2 = Zap::for($user)
            ->from('2025-01-01')
            ->addPeriod('10:00', '12:00') // Would normally conflict
            ->save();

        expect($schedule2)->toBeInstanceOf(Schedule::class);
    });

    it('handles complex recurring schedule conflicts', function () {
        $user = createUser();

        // Create recurring schedule
        Zap::for($user)
            ->named('Weekly Meeting')
            ->from('2025-01-01')
            ->to('2025-12-31')
            ->addPeriod('09:00', '10:00')
            ->weekly(['monday'])
            ->save();

        // Try to create conflicting one-time event
        expect(function () use ($user) {
            Zap::for($user)
                ->from('2025-01-06') // This is a Monday (Jan 6, 2025)
                ->addPeriod('09:30', '10:30')
                ->noOverlap()
                ->save();
        })->toThrow(ScheduleConflictException::class);
    });

    it('provides detailed conflict information in exceptions', function () {
        $user = createUser();

        // Create first schedule
        $conflictingSchedule = Zap::for($user)
            ->named('Existing Meeting')
            ->from('2025-01-01')
            ->addPeriod('09:00', '10:00')
            ->save();

        // Try to create conflicting schedule
        try {
            Zap::for($user)
                ->from('2025-01-01')
                ->addPeriod('09:30', '10:30')
                ->noOverlap()
                ->save();
        } catch (ScheduleConflictException $e) {
            expect($e->getConflictingSchedules())->toHaveCount(1);
            expect($e->getConflictingSchedules()[0]->name)->toBe('Existing Meeting');
        }
    });

});

describe('Availability Checking', function () {

    it('correctly identifies available time slots', function () {
        $user = createUser();

        // Block morning
        Zap::for($user)
            ->from('2025-01-01')
            ->addPeriod('09:00', '12:00')
            ->save();

        // Check various time slots
        expect($user->isAvailableAt('2025-01-01', '08:00', '09:00'))->toBeTrue();  // Before
        expect($user->isAvailableAt('2025-01-01', '09:00', '10:00'))->toBeFalse(); // During
        expect($user->isAvailableAt('2025-01-01', '10:00', '11:00'))->toBeFalse(); // During
        expect($user->isAvailableAt('2025-01-01', '12:00', '13:00'))->toBeTrue();  // After
    });

    it('generates accurate available slots', function () {
        $user = createUser();

        // Block 10:00-11:00
        Zap::for($user)
            ->from('2025-01-01')
            ->addPeriod('10:00', '11:00')
            ->save();

        $slots = $user->getAvailableSlots('2025-01-01', '09:00', '13:00', 60);

        expect($slots)->toHaveCount(4);
        expect($slots[0]['is_available'])->toBeTrue();  // 09:00-10:00
        expect($slots[1]['is_available'])->toBeFalse(); // 10:00-11:00 (blocked)
        expect($slots[2]['is_available'])->toBeTrue();  // 11:00-12:00
        expect($slots[3]['is_available'])->toBeTrue();  // 12:00-13:00
    });

    it('finds next available slot across multiple days', function () {
        $user = createUser();

        // Block entire first day
        Zap::for($user)
            ->from('2025-01-01')
            ->addPeriod('09:00', '17:00')
            ->save();

        $nextSlot = $user->getNextAvailableSlot('2025-01-01', 60, '09:00', '17:00');

        expect($nextSlot)->toBeArray();
        expect($nextSlot['date'])->toBe('2025-01-02'); // Should find slot on next day
        expect($nextSlot['start_time'])->toBe('09:00');
    });

});
