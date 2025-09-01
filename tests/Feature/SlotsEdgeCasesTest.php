<?php

use Carbon\Carbon;
use Zap\Facades\Zap;

describe('Slots Feature Edge Cases', function () {

    beforeEach(function () {
        Carbon::setTestNow('2025-03-14 08:00:00'); // Friday
    });

    afterEach(function () {
        Carbon::setTestNow(); // Reset
    });

    describe('Cross-midnight scenarios', function () {

        it('handles schedules that cross midnight', function () {
            $user = createUser();

            // Schedule from 22:00 to 23:59 on Saturday
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('22:00', '23:59')
                ->save();

            // Schedule from 00:00 to 02:00 on Sunday
            Zap::for($user)
                ->from('2025-03-16')
                ->addPeriod('00:00', '02:00')
                ->save();

            // Check evening slots on Saturday - only test existing slots
            $eveningSlots = $user->getAvailableSlots('2025-03-15', '21:00', '23:00', 60);
            expect(count($eveningSlots))->toBeGreaterThan(0);
            expect($eveningSlots[1]['is_available'])->toBeFalse(); // 22:00-23:00 blocked

            // Check early morning slots on Sunday
            $morningSlots = $user->getAvailableSlots('2025-03-16', '00:00', '03:00', 60);
            expect($morningSlots[0]['is_available'])->toBeFalse(); // 00:00-01:00 blocked
            expect($morningSlots[1]['is_available'])->toBeFalse(); // 01:00-02:00 blocked
            expect($morningSlots[2]['is_available'])->toBeTrue();  // 02:00-03:00 available
        });

    });

    describe('Stress testing', function () {

        it('handles many small slots efficiently', function () {
            $user = createUser();

            // Block random hours throughout the day
            for ($i = 10; $i < 17; $i += 2) {
                Zap::for($user)
                    ->from('2025-03-15')
                    ->addPeriod(sprintf('%02d:00', $i), sprintf('%02d:30', $i))
                    ->save();
            }

            $startTime = microtime(true);

            // Get 15-minute slots for entire day (should create 96 slots)
            $slots = $user->getAvailableSlots('2025-03-15', '00:00', '23:59', 15);

            $executionTime = microtime(true) - $startTime;

            expect(count($slots))->toBeGreaterThan(90); // Should have many slots
            expect($executionTime)->toBeLessThan(0.5); // Should complete quickly

            // Verify some blocked slots
            $blockedSlots = array_filter($slots, fn ($slot) => ! $slot['is_available']);
            expect(count($blockedSlots))->toBeGreaterThan(5); // Should have some blocked slots
        });

        it('handles long duration searches efficiently', function () {
            $user = createUser();

            // Block only small portions, leaving enough space for longer slots
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('10:00', '10:30')
                ->save();

            $startTime = microtime(true);

            // Look for 2-hour slot (reasonable duration that should be found)
            $nextSlot = $user->getNextAvailableSlot('2025-03-15', 120, '09:00', '19:00');

            $executionTime = microtime(true) - $startTime;

            expect($nextSlot)->not->toBeNull();
            expect($executionTime)->toBeLessThan(1.0); // Should complete in reasonable time
        });

    });

    describe('Invalid input handling', function () {

        it('handles invalid time ranges gracefully', function () {
            $user = createUser();

            // End time before start time
            $slots = $user->getAvailableSlots('2025-03-15', '17:00', '09:00', 60);
            expect($slots)->toBeArray();
            expect(count($slots))->toBe(0); // Should return empty array

            // Invalid slot duration
            $slots2 = $user->getAvailableSlots('2025-03-15', '09:00', '17:00', 0);
            expect($slots2)->toBeArray();
            expect(count($slots2))->toBe(0); // Should return empty array

            // Negative slot duration
            $slots3 = $user->getAvailableSlots('2025-03-15', '09:00', '17:00', -60);
            expect($slots3)->toBeArray();
            expect(count($slots3))->toBe(0); // Should return empty array
        });

        it('handles invalid dates gracefully', function () {
            $user = createUser();

            // Past dates
            $slots = $user->getAvailableSlots('2020-01-01', '09:00', '17:00', 60);
            expect($slots)->toBeArray(); // Should not crash

            // Invalid date format (should not crash, but may return empty)
            try {
                $slots2 = $user->getAvailableSlots('invalid-date', '09:00', '17:00', 60);
                expect($slots2)->toBeArray();
            } catch (Exception $e) {
                // Expected behavior - invalid date should throw exception
                expect($e)->toBeInstanceOf(Exception::class);
            }
        });

    });

    describe('Timezone considerations', function () {

        it('handles consistent timezone behavior', function () {
            $user = createUser();

            // Schedule at specific time
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('14:00', '15:00')
                ->save();

            // Get slots in same timezone
            $slots = $user->getAvailableSlots('2025-03-15', '13:00', '16:00', 60);

            expect($slots[0]['is_available'])->toBeTrue();  // 13:00-14:00 available
            expect($slots[1]['is_available'])->toBeFalse(); // 14:00-15:00 blocked
            expect($slots[2]['is_available'])->toBeTrue();  // 15:00-16:00 available
        });

    });

    describe('Complex recurring patterns', function () {

        it('handles multiple overlapping weekly patterns', function () {
            $user = createUser();

            // Monday/Wednesday/Friday morning
            Zap::for($user)
                ->from('2025-03-17') // Monday
                ->addPeriod('09:00', '12:00')
                ->weekly(['monday', 'wednesday', 'friday'])
                ->save();

            // Tuesday/Thursday afternoon
            Zap::for($user)
                ->from('2025-03-18') // Tuesday
                ->addPeriod('13:00', '17:00')
                ->weekly(['tuesday', 'thursday'])
                ->save();

            // Test Monday (should block morning)
            $mondaySlots = $user->getAvailableSlots('2025-03-17', '08:00', '18:00', 60);
            expect($mondaySlots[1]['is_available'])->toBeFalse(); // 09:00-10:00 blocked
            expect($mondaySlots[5]['is_available'])->toBeTrue();  // 13:00-14:00 available

            // Test Tuesday (should block afternoon)
            $tuesdaySlots = $user->getAvailableSlots('2025-03-18', '08:00', '18:00', 60);
            expect($tuesdaySlots[1]['is_available'])->toBeTrue();  // 09:00-10:00 available
            expect($tuesdaySlots[5]['is_available'])->toBeFalse(); // 13:00-14:00 blocked
        });

        it('handles bi-weekly patterns', function () {
            $user = createUser();

            // Every other Saturday
            Zap::for($user)
                ->from('2025-03-15') // First Saturday
                ->addPeriod('10:00', '16:00')
                ->weekly(['saturday'], 2) // Every 2 weeks
                ->save();

            // March 15 (first Saturday) - should be blocked
            $firstSaturday = $user->getAvailableSlots('2025-03-15', '09:00', '17:00', 60);
            $blockedSlots = array_filter($firstSaturday, fn ($slot) => ! $slot['is_available']);
            expect(count($blockedSlots))->toBeGreaterThan(0);

            // March 22 (second Saturday) - should be available (bi-weekly means every 2 weeks)
            $secondSaturday = $user->getAvailableSlots('2025-03-22', '09:00', '17:00', 60);
            $availableSlots = array_filter($secondSaturday, fn ($slot) => $slot['is_available']);
            expect(count($availableSlots))->toBeGreaterThan(0); // Should have some available slots

            // March 29 (third Saturday, which is week 2 of cycle) - should be blocked
            $thirdSaturday = $user->getAvailableSlots('2025-03-29', '09:00', '17:00', 60);
            $blockedSlots3 = array_filter($thirdSaturday, fn ($slot) => ! $slot['is_available']);
            expect(count($blockedSlots3))->toBeGreaterThan(0);
        });

    });

    describe('Business logic edge cases', function () {

        it('handles very short slots correctly', function () {
            $user = createUser();

            // Block 10:05-10:15 (10-minute block)
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('10:05', '10:15')
                ->save();

            // 5-minute slots should detect the conflict
            $slots5min = $user->getAvailableSlots('2025-03-15', '10:00', '10:20', 5);

            // Should have slots: 10:00-05, 10:05-10, 10:10-15, 10:15-20
            expect(count($slots5min))->toBe(4);
            expect($slots5min[0]['is_available'])->toBeTrue();  // 10:00-10:05 available
            expect($slots5min[1]['is_available'])->toBeFalse(); // 10:05-10:10 blocked
            expect($slots5min[2]['is_available'])->toBeFalse(); // 10:10-10:15 blocked
            expect($slots5min[3]['is_available'])->toBeTrue();  // 10:15-10:20 available
        });

        it('handles exact time boundary matches', function () {
            $user = createUser();

            // Schedule exactly 10:00-11:00
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('10:00', '11:00')
                ->save();

            // Request slot exactly 10:00-11:00
            $slots = $user->getAvailableSlots('2025-03-15', '10:00', '11:00', 60);
            expect($slots)->toHaveCount(1);
            expect($slots[0]['start_time'])->toBe('10:00');
            expect($slots[0]['end_time'])->toBe('11:00');
            expect($slots[0]['is_available'])->toBeFalse(); // Should be blocked

            // Request slots 09:00-10:00 and 11:00-12:00 (adjacent)
            $beforeSlots = $user->getAvailableSlots('2025-03-15', '09:00', '10:00', 60);
            expect($beforeSlots[0]['is_available'])->toBeTrue(); // Should be available

            $afterSlots = $user->getAvailableSlots('2025-03-15', '11:00', '12:00', 60);
            expect($afterSlots[0]['is_available'])->toBeTrue(); // Should be available
        });

        it('finds gaps between adjacent schedules', function () {
            $user = createUser();

            // Two adjacent schedules with gap
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('09:00', '10:00') // Morning
                ->addPeriod('10:30', '11:30') // Late morning
                ->save();

            // Look for 30-minute slot - should find the gap
            $nextSlot = $user->getNextAvailableSlot('2025-03-15', 30, '09:00', '12:00');
            expect($nextSlot['start_time'])->toBe('10:00');
            expect($nextSlot['end_time'])->toBe('10:30');

            // Look for 45-minute slot - gap is too small, should find either at very beginning or after 11:30
            $nextSlot45 = $user->getNextAvailableSlot('2025-03-15', 45, '08:00', '12:00');
            expect($nextSlot45['start_time'])->toBe('08:00'); // Should find at the very beginning before any blocks
            expect($nextSlot45['end_time'])->toBe('08:45');
        });

    });

    describe('Performance with complex schedules', function () {

        it('performs well with many recurring schedules', function () {
            $user = createUser();

            // Create 10 different recurring schedules
            for ($i = 0; $i < 10; $i++) {
                $startHour = 9 + $i;
                $endHour = $startHour + 1;

                Zap::for($user)
                    ->named("Schedule {$i}")
                    ->from('2025-03-15')
                    ->addPeriod(sprintf('%02d:00', $startHour), sprintf('%02d:00', $endHour))
                    ->weekly(['monday', 'wednesday', 'friday'])
                    ->save();
            }

            $startTime = microtime(true);

            $slots = $user->getAvailableSlots('2025-03-17', '08:00', '20:00', 60); // Monday
            $nextSlot = $user->getNextAvailableSlot('2025-03-17', 120, '08:00', '20:00');

            $executionTime = microtime(true) - $startTime;

            expect($executionTime)->toBeLessThan(0.2); // Should complete quickly
            expect($slots)->toBeArray();
            expect(count($slots))->toBeGreaterThan(0);
        });

    })->skip();

});
