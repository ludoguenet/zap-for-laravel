<?php

use Carbon\Carbon;
use Zap\Facades\Zap;

describe('Buffer Time Availability Tests', function () {

    beforeEach(function () {
        Carbon::setTestNow('2025-03-14 08:00:00'); // Friday

        // Reset buffer time config for each test
        config(['zap.time_slots.buffer_minutes' => 0]);
    });

    afterEach(function () {
        Carbon::setTestNow(); // Reset
    });

    describe('getAvailableSlots with buffer time', function () {

        it('generates slots with 10-minute buffer time from config', function () {
            config(['zap.time_slots.buffer_minutes' => 10]);

            $user = createUser();

            // Get 50-minute slots with 10-minute buffer
            $slots = $user->getAvailableSlots('2025-03-15', '09:00', '12:00', 50);

            // Should generate: 9:00-9:50, 10:00-10:50, 11:00-11:50
            expect($slots)->toHaveCount(3);

            expect($slots[0]['start_time'])->toBe('09:00');
            expect($slots[0]['end_time'])->toBe('09:50');
            expect($slots[0]['buffer_minutes'])->toBe(10);

            expect($slots[1]['start_time'])->toBe('10:00');
            expect($slots[1]['end_time'])->toBe('10:50');
            expect($slots[1]['buffer_minutes'])->toBe(10);

            expect($slots[2]['start_time'])->toBe('11:00');
            expect($slots[2]['end_time'])->toBe('11:50');
            expect($slots[2]['buffer_minutes'])->toBe(10);
        });

        it('generates slots with buffer time passed as parameter', function () {
            $user = createUser();

            // Get 30-minute slots with 15-minute buffer (passed as parameter)
            $slots = $user->getAvailableSlots('2025-03-15', '09:00', '11:00', 30, 15);

            // Should generate: 9:00-9:30, 9:45-10:15, 10:30-11:00
            expect($slots)->toHaveCount(3);

            expect($slots[0]['start_time'])->toBe('09:00');
            expect($slots[0]['end_time'])->toBe('09:30');
            expect($slots[0]['buffer_minutes'])->toBe(15);

            expect($slots[1]['start_time'])->toBe('09:45');
            expect($slots[1]['end_time'])->toBe('10:15');
            expect($slots[1]['buffer_minutes'])->toBe(15);

            expect($slots[2]['start_time'])->toBe('10:30');
            expect($slots[2]['end_time'])->toBe('11:00');
            expect($slots[2]['buffer_minutes'])->toBe(15);
        });

        it('non-zero parameter overrides config buffer time', function () {
            config(['zap.time_slots.buffer_minutes' => 5]);

            $user = createUser();

            // Non-zero parameter (10 minutes) should override config (5 minutes)
            $slots = $user->getAvailableSlots('2025-03-15', '09:00', '10:30', 60, 10);

            expect($slots[0]['buffer_minutes'])->toBe(10);
            expect($slots[0]['start_time'])->toBe('09:00');
            expect($slots[0]['end_time'])->toBe('10:00');
        });

        it('uses config when buffer parameter is null', function () {
            config(['zap.time_slots.buffer_minutes' => 5]);

            $user = createUser();

            // Not passing buffer parameter should use config value (5 minutes)
            $slots = $user->getAvailableSlots('2025-03-15', '09:00', '12:00', 60);

            expect($slots[0]['buffer_minutes'])->toBe(5);
            expect($slots[0]['start_time'])->toBe('09:00');

            // Should be at least 2 slots in a 3-hour window with 60+5 minute intervals
            expect(count($slots))->toBeGreaterThanOrEqual(2);
            if (count($slots) >= 2) {
                expect($slots[1]['start_time'])->toBe('10:05'); // 60 + 5 minute gap
            }
        });

        it('respects explicit zero buffer parameter', function () {
            config(['zap.time_slots.buffer_minutes' => 5]);

            $user = createUser();

            // Explicitly passing 0 should override config and use 0
            $slots = $user->getAvailableSlots('2025-03-15', '09:00', '11:00', 60, 0);

            expect($slots[0]['buffer_minutes'])->toBe(0);
            expect($slots[0]['start_time'])->toBe('09:00');
            expect($slots[1]['start_time'])->toBe('10:00'); // No gap
        });

        it('ignores negative buffer time', function () {
            $user = createUser();

            // Negative buffer should be treated as 0
            $slots = $user->getAvailableSlots('2025-03-15', '09:00', '11:00', 60, -5);

            expect($slots[0]['buffer_minutes'])->toBe(0);
            expect($slots[0]['start_time'])->toBe('09:00');
            expect($slots[1]['start_time'])->toBe('10:00'); // No gap
        });

        it('handles large buffer time that reduces available slots', function () {
            $user = createUser();

            // 60-minute slots with 30-minute buffer in 3-hour window
            $slots = $user->getAvailableSlots('2025-03-15', '09:00', '12:00', 60, 30);

            // Should generate: 9:00-10:00, 10:30-11:30
            expect($slots)->toHaveCount(2);

            expect($slots[0]['start_time'])->toBe('09:00');
            expect($slots[0]['end_time'])->toBe('10:00');

            expect($slots[1]['start_time'])->toBe('10:30');
            expect($slots[1]['end_time'])->toBe('11:30');
        });

        it('handles buffer time that prevents any slots from fitting', function () {
            $user = createUser();

            // 60-minute slots with 120-minute buffer in 2-hour window
            $slots = $user->getAvailableSlots('2025-03-15', '09:00', '11:00', 60, 120);

            // Only first slot should fit
            expect($slots)->toHaveCount(1);
            expect($slots[0]['start_time'])->toBe('09:00');
            expect($slots[0]['end_time'])->toBe('10:00');
        });

        it('respects existing blocked schedules with buffer time', function () {
            $user = createUser();

            // Block 10:00-11:00
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('10:00', '11:00')
                ->save();

            $slots = $user->getAvailableSlots('2025-03-15', '09:00', '13:00', 50, 10);

            // Find slots that should be available/blocked
            $slot1 = collect($slots)->firstWhere('start_time', '09:00'); // Should be available
            $slot2 = collect($slots)->firstWhere('start_time', '10:00'); // Should be blocked
            $slot3 = collect($slots)->firstWhere('start_time', '11:00'); // Should be available
            $slot4 = collect($slots)->firstWhere('start_time', '12:00'); // Should be available

            expect($slot1['is_available'])->toBeTrue();
            expect($slot2['is_available'])->toBeFalse(); // Blocked by schedule
            expect($slot3['is_available'])->toBeTrue();
            expect($slot4['is_available'])->toBeTrue();
        });

    });

    describe('getNextAvailableSlot with buffer time', function () {

        it('finds next slot considering buffer time', function () {
            $user = createUser();

            // Block 09:00-10:00
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('09:00', '10:00')
                ->save();

            // Look for 50-minute slot with 10-minute buffer
            $nextSlot = $user->getNextAvailableSlot('2025-03-15', 50, '09:00', '17:00', 10);

            // Should find 10:00-10:50 slot (next available after blocked 9:00)
            expect($nextSlot['start_time'])->toBe('10:00');
            expect($nextSlot['end_time'])->toBe('10:50');
            expect($nextSlot['buffer_minutes'])->toBe(10);
        });

        it('finds slot on next day when buffer prevents fitting', function () {
            $user = createUser();

            // Block most of the day leaving only small gap
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('09:00', '16:30')
                ->save();

            // Look for 60-minute slot with 30-minute buffer
            $nextSlot = $user->getNextAvailableSlot('2025-03-15', 60, '09:00', '17:00', 30);

            // Should find slot on next day
            expect($nextSlot['date'])->toBe('2025-03-16');
            expect($nextSlot['start_time'])->toBe('09:00');
            expect($nextSlot['buffer_minutes'])->toBe(30);
        });

    });

    describe('buffer time edge cases', function () {

        it('handles very small slot durations with buffer', function () {
            $user = createUser();

            // 15-minute slots with 5-minute buffer
            $slots = $user->getAvailableSlots('2025-03-15', '09:00', '10:00', 15, 5);

            // Should generate: 9:00-9:15, 9:20-9:35, 9:40-9:55
            expect($slots)->toHaveCount(3);

            expect($slots[0]['start_time'])->toBe('09:00');
            expect($slots[1]['start_time'])->toBe('09:20');
            expect($slots[2]['start_time'])->toBe('09:40');
        });

        it('handles buffer time equal to slot duration', function () {
            $user = createUser();

            // 30-minute slots with 30-minute buffer
            $slots = $user->getAvailableSlots('2025-03-15', '09:00', '12:00', 30, 30);

            // Should generate: 9:00-9:30, 10:00-10:30, 11:00-11:30
            expect($slots)->toHaveCount(3);

            expect($slots[0]['start_time'])->toBe('09:00');
            expect($slots[1]['start_time'])->toBe('10:00');
            expect($slots[2]['start_time'])->toBe('11:00');
        });

        it('handles buffer time larger than slot duration', function () {
            $user = createUser();

            // 30-minute slots with 45-minute buffer
            $slots = $user->getAvailableSlots('2025-03-15', '09:00', '12:00', 30, 45);

            // Should generate: 9:00-9:30, 10:15-10:45, 11:30-12:00
            expect($slots)->toHaveCount(3);

            expect($slots[0]['start_time'])->toBe('09:00');
            expect($slots[1]['start_time'])->toBe('10:15');
            expect($slots[2]['start_time'])->toBe('11:30');
        });

    });

    describe('backward compatibility', function () {

        it('maintains existing behavior when buffer is 0', function () {
            $user = createUser();

            // Test with both config and parameter set to 0
            config(['zap.time_slots.buffer_minutes' => 0]);

            $slotsDefault = $user->getAvailableSlots('2025-03-15', '09:00', '11:00', 60);
            $slotsExplicit = $user->getAvailableSlots('2025-03-15', '09:00', '11:00', 60, 0);

            // Both should generate same result: 9:00-10:00, 10:00-11:00
            expect($slotsDefault)->toHaveCount(2);
            expect($slotsExplicit)->toHaveCount(2);

            expect($slotsDefault[0]['start_time'])->toBe('09:00');
            expect($slotsDefault[1]['start_time'])->toBe('10:00');
            expect($slotsDefault[0]['buffer_minutes'])->toBe(0);

            expect($slotsExplicit[0]['start_time'])->toBe('09:00');
            expect($slotsExplicit[1]['start_time'])->toBe('10:00');
            expect($slotsExplicit[0]['buffer_minutes'])->toBe(0);
        });

        it('works correctly when no buffer config is set', function () {
            $user = createUser();

            // Don't set any buffer config, should default to 0
            $slots = $user->getAvailableSlots('2025-03-15', '09:00', '11:00', 60);

            // Should generate: 9:00-10:00, 10:00-11:00 (no gaps)
            expect($slots)->toHaveCount(2);
            expect($slots[0]['buffer_minutes'])->toBe(0);
            expect($slots[0]['start_time'])->toBe('09:00');
            expect($slots[1]['start_time'])->toBe('10:00');
        });

        it('includes buffer_minutes in slot data for consistency', function () {
            $user = createUser();

            $slots = $user->getAvailableSlots('2025-03-15', '09:00', '10:00', 60, 5);

            foreach ($slots as $slot) {
                expect($slot)->toHaveKey('start_time');
                expect($slot)->toHaveKey('end_time');
                expect($slot)->toHaveKey('is_available');
                expect($slot)->toHaveKey('buffer_minutes');
                expect($slot['buffer_minutes'])->toBe(5);
            }
        });

    });

});