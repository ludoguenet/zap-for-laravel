<?php

use Zap\Facades\Zap;

it('returns null when no availability schedules exist', function () {
    $user = createUser();

    $nextSlot = $user->getNextBookableSlot('2025-01-01');

    expect($nextSlot)->toBeNull();
});

it('finds next bookable slot within availability schedules', function () {
    $user = createUser();

    // Create availability schedule
    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '17:00')
        ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
        ->save();

    $nextSlot = $user->getNextBookableSlot('2025-01-01');

    expect($nextSlot)->not()->toBeNull();
    expect($nextSlot['date'])->toBe('2025-01-01');
    expect($nextSlot['start_time'])->toBe('09:00');
    expect($nextSlot['is_available'])->toBeTrue();
});

it('skips days without availability schedules', function () {
    $user = createUser();

    // Create availability only for Friday
    Zap::for($user)
        ->availability()
        ->from('2025-01-01') // Wednesday
        ->addPeriod('09:00', '17:00')
        ->weekly(['friday'])
        ->save();

    // Starting from Wednesday, should find Friday
    $nextSlot = $user->getNextBookableSlot('2025-01-01');

    expect($nextSlot)->not()->toBeNull();
    expect($nextSlot['date'])->toBe('2025-01-03'); // Friday
});

it('skips past occupied slots', function () {
    $user = createUser();

    // Create availability
    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '12:00')
        ->save();

    // Create appointment for first slot
    Zap::for($user)
        ->appointment()
        ->from('2025-01-01')
        ->addPeriod('09:00', '10:00')
        ->save();

    $nextSlot = $user->getNextBookableSlot('2025-01-01');

    expect($nextSlot)->not()->toBeNull();
    expect($nextSlot['start_time'])->toBe('10:00'); // Should skip 09:00 slot
    expect($nextSlot['is_available'])->toBeTrue();
});

it('searches multiple days ahead', function () {
    $user = createUser();

    // Create availability only for day 3 days ahead
    Zap::for($user)
        ->availability()
        ->from('2025-01-04') // Saturday
        ->addPeriod('09:00', '17:00')
        ->save();

    $nextSlot = $user->getNextBookableSlot('2025-01-01');

    expect($nextSlot)->not()->toBeNull();
    expect($nextSlot['date'])->toBe('2025-01-04');
});

it('returns null when no slots found within 30 days', function () {
    $user = createUser();

    // Create availability far in the future
    Zap::for($user)
        ->availability()
        ->from('2025-02-15') // More than 30 days ahead
        ->addPeriod('09:00', '17:00')
        ->save();

    $nextSlot = $user->getNextBookableSlot('2025-01-01');

    expect($nextSlot)->toBeNull();
});

it('respects buffer time when finding next slot', function () {
    $user = createUser();

    // Create availability
    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '10:30')
        ->save();

    // With 15-minute buffer, only one 60-minute slot should fit
    $nextSlot = $user->getNextBookableSlot('2025-01-01', 60, 15);

    expect($nextSlot)->not()->toBeNull();
    expect($nextSlot['start_time'])->toBe('09:00');
    expect($nextSlot['buffer_minutes'])->toBe(15);
});

it('validates duration parameter', function () {
    $user = createUser();

    $nextSlot = $user->getNextBookableSlot('2025-01-01', 0);

    expect($nextSlot)->toBeNull();
});

it('uses current date when afterDate is null', function () {
    $user = createUser();

    // Create availability for today
    Zap::for($user)
        ->availability()
        ->from(now()->format('Y-m-d'))
        ->addPeriod('09:00', '17:00')
        ->save();

    $nextSlot = $user->getNextBookableSlot();

    expect($nextSlot)->not()->toBeNull();
    expect($nextSlot['date'])->toBe(now()->format('Y-m-d'));
});
