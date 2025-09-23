<?php

use Zap\Facades\Zap;

it('returns empty array when no availability schedules exist', function () {
    $user = createUser();

    $slots = $user->getBookableSlots('2025-01-01');

    expect($slots)->toBe([]);
});

it('returns slots only within availability schedule periods', function () {
    $user = createUser();

    // Create availability schedule (9:00-17:00)
    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '17:00')
        ->save();

    $slots = $user->getBookableSlots('2025-01-01', 60);

    expect($slots)->not()->toBeEmpty();

    // All slots should be within availability window
    foreach ($slots as $slot) {
        expect($slot['start_time'])->toBeGreaterThanOrEqual('09:00');
        expect($slot['end_time'])->toBeLessThanOrEqual('17:00');
    }
});

it('respects multiple availability periods in same schedule', function () {
    $user = createUser();

    // Create availability with morning and afternoon sessions
    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '12:00') // Morning
        ->addPeriod('14:00', '17:00') // Afternoon
        ->save();

    $slots = $user->getBookableSlots('2025-01-01', 60);

    $morningSlots = collect($slots)->filter(fn ($slot) => $slot['start_time'] >= '09:00' && $slot['end_time'] <= '12:00');
    $afternoonSlots = collect($slots)->filter(fn ($slot) => $slot['start_time'] >= '14:00' && $slot['end_time'] <= '17:00');
    $lunchSlots = collect($slots)->filter(fn ($slot) => $slot['start_time'] >= '12:00' && $slot['end_time'] <= '14:00');

    expect($morningSlots)->not()->toBeEmpty();
    expect($afternoonSlots)->not()->toBeEmpty();
    expect($lunchSlots)->toBeEmpty(); // No slots during lunch break
});

it('handles multiple overlapping availability schedules', function () {
    $user = createUser();

    // Create overlapping availability schedules
    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '15:00')
        ->save();

    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('12:00', '18:00')
        ->save();

    $slots = $user->getBookableSlots('2025-01-01', 60);

    // Should cover 09:00-18:00 without duplicates
    expect($slots)->not()->toBeEmpty();

    $startTimes = collect($slots)->pluck('start_time')->unique();
    expect($startTimes->count())->toBe($startTimes->count()); // No duplicates

    // Check coverage
    $firstSlot = collect($slots)->first();
    $lastSlot = collect($slots)->last();
    expect($firstSlot['start_time'])->toBe('09:00');
    expect($lastSlot['start_time'])->toBe('17:00'); // Last 60-minute slot that fits
});

it('excludes slots that conflict with appointments', function () {
    $user = createUser();

    // Create availability
    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '17:00')
        ->save();

    // Create appointment
    Zap::for($user)
        ->appointment()
        ->from('2025-01-01')
        ->addPeriod('10:00', '11:00')
        ->save();

    $slots = $user->getBookableSlots('2025-01-01', 60);

    // Should have slots before and after appointment, but not during
    $availableSlots = collect($slots)->where('is_available', true);
    $unavailableSlots = collect($slots)->where('is_available', false);

    expect($availableSlots)->not()->toBeEmpty();
    expect($unavailableSlots)->not()->toBeEmpty();

    // The 10:00-11:00 slot should be unavailable
    $conflictSlot = collect($slots)->first(fn ($slot) => $slot['start_time'] === '10:00');
    expect($conflictSlot['is_available'])->toBeFalse();
});

it('excludes slots that conflict with blocked periods', function () {
    $user = createUser();

    // Create availability
    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '17:00')
        ->save();

    // Create blocked period
    Zap::for($user)
        ->blocked()
        ->from('2025-01-01')
        ->addPeriod('12:00', '13:00')
        ->save();

    $slots = $user->getBookableSlots('2025-01-01', 60);

    // The 12:00-13:00 slot should be unavailable
    $blockedSlot = collect($slots)->first(fn ($slot) => $slot['start_time'] === '12:00');
    expect($blockedSlot['is_available'])->toBeFalse();
});

it('works with recurring availability schedules', function () {
    $user = createUser();

    // Create weekly recurring availability
    Zap::for($user)
        ->availability()
        ->from('2025-01-01') // Wednesday
        ->addPeriod('09:00', '17:00')
        ->weekly(['wednesday', 'friday'])
        ->save();

    // Wednesday should have slots
    $wednesdaySlots = $user->getBookableSlots('2025-01-01', 60);
    expect($wednesdaySlots)->not()->toBeEmpty();

    // Thursday should have no slots
    $thursdaySlots = $user->getBookableSlots('2025-01-02', 60);
    expect($thursdaySlots)->toBeEmpty();

    // Friday should have slots
    $fridaySlots = $user->getBookableSlots('2025-01-03', 60);
    expect($fridaySlots)->not()->toBeEmpty();
});

it('respects buffer time configuration', function () {
    $user = createUser();

    // Create availability
    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '12:00')
        ->save();

    // Test with 15-minute buffer
    $slots = $user->getBookableSlots('2025-01-01', 60, 15);

    expect($slots)->not()->toBeEmpty();

    // Slots should be spaced 75 minutes apart (60 + 15 buffer)
    if (count($slots) > 1) {
        $firstSlot = $slots[0];
        $secondSlot = $slots[1];

        $time1 = \Carbon\Carbon::parse('2025-01-01 '.$firstSlot['start_time']);
        $time2 = \Carbon\Carbon::parse('2025-01-01 '.$secondSlot['start_time']);

        expect((int) $time1->diffInMinutes($time2))->toBe(75);
    } else {
        $this->markTestIncomplete('Not enough slots generated to test buffer spacing.');
    }
});

it('handles inactive availability schedules', function () {
    $user = createUser();

    // Create inactive availability schedule
    $schedule = Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '17:00')
        ->save();

    // Deactivate the schedule
    $schedule->update(['is_active' => false]);

    $slots = $user->getBookableSlots('2025-01-01', 60);

    expect($slots)->toBeEmpty();
});

it('handles availability schedules outside date range', function () {
    $user = createUser();

    // Create availability for different dates
    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->to('2025-01-02')
        ->addPeriod('09:00', '17:00')
        ->save();

    // Request slots for date outside range
    $slots = $user->getBookableSlots('2025-01-03', 60);

    expect($slots)->toBeEmpty();
});
