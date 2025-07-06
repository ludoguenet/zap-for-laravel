<?php

use Zap\Exceptions\ScheduleConflictException;
use Zap\Facades\Zap;
use Zap\Models\Schedule;

it('can create availability schedules that allow overlaps', function () {
    $user = createUser();

    // Create an availability schedule (working hours)
    $availability = Zap::for($user)
        ->named('Working Hours')
        ->description('Available for appointments')
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '17:00')
        ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
        ->save();

    expect($availability->schedule_type)->toBe(Schedule::TYPE_AVAILABILITY);
    expect($availability->allowsOverlaps())->toBeTrue();
    expect($availability->preventsOverlaps())->toBeFalse();

    // Create an appointment within the availability window
    $appointment = Zap::for($user)
        ->named('Client Meeting')
        ->description('Meeting with client')
        ->appointment()
        ->from('2025-01-01')
        ->addPeriod('10:00', '11:00')
        ->save();

    expect($appointment->schedule_type)->toBe(Schedule::TYPE_APPOINTMENT);
    expect($appointment->allowsOverlaps())->toBeFalse();
    expect($appointment->preventsOverlaps())->toBeTrue();

    // Should NOT be able to create another appointment in the same time slot
    expect(function () use ($user) {
        Zap::for($user)
            ->named('Another Meeting')
            ->description('Another meeting')
            ->appointment()
            ->from('2025-01-01')
            ->addPeriod('10:00', '11:00')
            ->save();
    })->toThrow(ScheduleConflictException::class);
});

it('can create appointment schedules that prevent overlaps', function () {
    $user = createUser();

    // Create first appointment
    $appointment1 = Zap::for($user)
        ->named('First Appointment')
        ->appointment()
        ->from('2025-01-01')
        ->addPeriod('10:00', '11:00')
        ->save();

    // Try to create overlapping appointment - should fail
    expect(function () use ($user) {
        Zap::for($user)
            ->named('Conflicting Appointment')
            ->appointment()
            ->from('2025-01-01')
            ->addPeriod('10:30', '11:30')
            ->save();
    })->toThrow(ScheduleConflictException::class);

    // Create non-overlapping appointment - should succeed
    $appointment2 = Zap::for($user)
        ->named('Non-Conflicting Appointment')
        ->appointment()
        ->from('2025-01-01')
        ->addPeriod('11:00', '12:00')
        ->save();

    expect($appointment2->schedule_type)->toBe(Schedule::TYPE_APPOINTMENT);
});

it('can create blocked schedules that prevent overlaps', function () {
    $user = createUser();

    // Create a blocked schedule (lunch break)
    $blocked = Zap::for($user)
        ->named('Lunch Break')
        ->description('Unavailable for appointments')
        ->blocked()
        ->from('2025-01-01')
        ->addPeriod('12:00', '13:00')
        ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
        ->save();

    expect($blocked->schedule_type)->toBe(Schedule::TYPE_BLOCKED);
    expect($blocked->allowsOverlaps())->toBeFalse();
    expect($blocked->preventsOverlaps())->toBeTrue();

    // Try to create appointment during blocked time - should fail
    expect(function () use ($user) {
        Zap::for($user)
            ->named('Lunch Meeting')
            ->appointment()
            ->from('2025-01-01')
            ->addPeriod('12:00', '13:00')
            ->save();
    })->toThrow(ScheduleConflictException::class);
});

it('can use convenience methods for schedule types', function () {
    $user = createUser();

    // Test availability method
    $availability = Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '17:00')
        ->save();

    expect($availability->schedule_type)->toBe(Schedule::TYPE_AVAILABILITY);

    // Test appointment method
    $appointment = Zap::for($user)
        ->appointment()
        ->from('2025-01-01')
        ->addPeriod('10:00', '11:00')
        ->save();

    expect($appointment->schedule_type)->toBe(Schedule::TYPE_APPOINTMENT);

    // Test blocked method
    $blocked = Zap::for($user)
        ->blocked()
        ->from('2025-01-01')
        ->addPeriod('12:00', '13:00')
        ->save();

    expect($blocked->schedule_type)->toBe(Schedule::TYPE_BLOCKED);

    // Test custom method
    $custom = Zap::for($user)
        ->custom()
        ->from('2025-01-01')
        ->addPeriod('14:00', '15:00')
        ->save();

    expect($custom->schedule_type)->toBe(Schedule::TYPE_CUSTOM);
});

it('can use explicit type method', function () {
    $user = createUser();

    $schedule = Zap::for($user)
        ->type('availability')
        ->from('2025-01-01')
        ->addPeriod('09:00', '17:00')
        ->save();

    expect($schedule->schedule_type)->toBe(Schedule::TYPE_AVAILABILITY);

    // Test invalid type
    expect(function () use ($user) {
        Zap::for($user)
            ->type('invalid_type')
            ->from('2025-01-01')
            ->addPeriod('09:00', '17:00')
            ->save();
    })->toThrow(InvalidArgumentException::class);
});

it('can query schedules by type', function () {
    $user = createUser();

    // Create different types of schedules on different dates to avoid conflicts
    $availability = Zap::for($user)->availability()->from('2025-01-01')->addPeriod('09:00', '17:00')->save();
    $appointment = Zap::for($user)->appointment()->from('2025-01-02')->addPeriod('10:00', '11:00')->save();
    $blocked = Zap::for($user)->blocked()->from('2025-01-03')->addPeriod('12:00', '13:00')->save();
    $custom = Zap::for($user)->custom()->from('2025-01-04')->addPeriod('14:00', '15:00')->save();

    // Test individual schedule types
    expect($availability->schedule_type)->toBe(Schedule::TYPE_AVAILABILITY);
    expect($appointment->schedule_type)->toBe(Schedule::TYPE_APPOINTMENT);
    expect($blocked->schedule_type)->toBe(Schedule::TYPE_BLOCKED);
    expect($custom->schedule_type)->toBe(Schedule::TYPE_CUSTOM);

    // Test helper methods
    expect($availability->isAvailability())->toBeTrue();
    expect($appointment->isAppointment())->toBeTrue();
    expect($blocked->isBlocked())->toBeTrue();
    expect($custom->isCustom())->toBeTrue();

    // Test overlap behavior
    expect($availability->allowsOverlaps())->toBeTrue();
    expect($appointment->preventsOverlaps())->toBeTrue();
    expect($blocked->preventsOverlaps())->toBeTrue();
    expect($custom->allowsOverlaps())->toBeTrue();
});

it('handles availability checking correctly with new schedule types', function () {
    $user = createUser();

    // Create availability schedule (working hours)
    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '17:00')
        ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
        ->save();

    // Create appointment
    Zap::for($user)
        ->appointment()
        ->from('2025-01-01')
        ->addPeriod('10:00', '11:00')
        ->save();

    // Create blocked time
    Zap::for($user)
        ->blocked()
        ->from('2025-01-01')
        ->addPeriod('12:00', '13:00')
        ->save();

    // Test availability checking
    expect($user->isAvailableAt('2025-01-01', '09:00', '10:00'))->toBeTrue();  // Available (before appointment)
    expect($user->isAvailableAt('2025-01-01', '10:00', '11:00'))->toBeFalse(); // Appointment blocks
    expect($user->isAvailableAt('2025-01-01', '11:00', '12:00'))->toBeTrue();  // Available
    expect($user->isAvailableAt('2025-01-01', '12:00', '13:00'))->toBeFalse(); // Blocked
    expect($user->isAvailableAt('2025-01-01', '13:00', '14:00'))->toBeTrue();  // Available
    expect($user->isAvailableAt('2025-01-01', '17:00', '18:00'))->toBeTrue();  // Outside working hours but no blocking schedules
});

it('can create complex scheduling scenarios', function () {
    $doctor = createUser();

    // Doctor's working hours (availability)
    $availability = Zap::for($doctor)
        ->named('Office Hours')
        ->availability()
        ->from('2025-01-01')
        ->to('2025-12-31')
        ->addPeriod('09:00', '12:00') // Morning session
        ->addPeriod('14:00', '17:00') // Afternoon session
        ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
        ->save();

    // Lunch break (blocked)
    $lunchBreak = Zap::for($doctor)
        ->named('Lunch Break')
        ->blocked()
        ->from('2025-01-01')
        ->to('2025-12-31')
        ->addPeriod('12:00', '13:00')
        ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
        ->save();

    // Patient appointments (non-overlapping)
    $appointment1 = Zap::for($doctor)
        ->named('Patient A - Checkup')
        ->appointment()
        ->from('2025-01-01')
        ->addPeriod('10:00', '11:00')
        ->withMetadata(['patient_id' => 1, 'type' => 'checkup'])
        ->save();

    $appointment2 = Zap::for($doctor)
        ->named('Patient B - Consultation')
        ->appointment()
        ->from('2025-01-01')
        ->addPeriod('15:00', '16:00')
        ->withMetadata(['patient_id' => 2, 'type' => 'consultation'])
        ->save();

    // Verify each schedule was created successfully
    expect($availability)->toBeInstanceOf(Schedule::class);
    expect($lunchBreak)->toBeInstanceOf(Schedule::class);
    expect($appointment1)->toBeInstanceOf(Schedule::class);
    expect($appointment2)->toBeInstanceOf(Schedule::class);

    // Verify schedule types
    expect($availability->schedule_type)->toBe(Schedule::TYPE_AVAILABILITY);
    expect($lunchBreak->schedule_type)->toBe(Schedule::TYPE_BLOCKED);
    expect($appointment1->schedule_type)->toBe(Schedule::TYPE_APPOINTMENT);
    expect($appointment2->schedule_type)->toBe(Schedule::TYPE_APPOINTMENT);

    // Test availability
    expect($doctor->isAvailableAt('2025-01-01', '09:00', '10:00'))->toBeTrue();  // Available (before appointment)
    expect($doctor->isAvailableAt('2025-01-01', '10:00', '11:00'))->toBeFalse(); // Appointment
    expect($doctor->isAvailableAt('2025-01-01', '11:00', '12:00'))->toBeTrue();  // Available
    expect($doctor->isAvailableAt('2025-01-01', '12:00', '13:00'))->toBeFalse(); // Lunch break
    expect($doctor->isAvailableAt('2025-01-01', '13:00', '14:00'))->toBeTrue();  // Available
    expect($doctor->isAvailableAt('2025-01-01', '15:00', '16:00'))->toBeFalse(); // Appointment
    expect($doctor->isAvailableAt('2025-01-01', '16:00', '17:00'))->toBeTrue();  // Available
});

it('maintains backward compatibility with existing code', function () {
    $user = createUser();

    // Old way of creating schedules (should default to 'custom' type)
    $schedule = Zap::for($user)
        ->from('2025-01-01')
        ->addPeriod('09:00', '10:00')
        ->save();

    expect($schedule->schedule_type)->toBe(Schedule::TYPE_CUSTOM);
    expect($schedule->isCustom())->toBeTrue();

    // Old way with noOverlap() should still work
    $appointment = Zap::for($user)
        ->from('2025-01-01')
        ->addPeriod('10:00', '11:00')
        ->noOverlap()
        ->save();

    expect($appointment->schedule_type)->toBe(Schedule::TYPE_CUSTOM);
    expect($appointment->preventsOverlaps())->toBeFalse(); // Custom type doesn't prevent overlaps by default
});
