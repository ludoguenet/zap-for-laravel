<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Zap\Facades\Zap;

describe('Recurring Schedule Availability', function () {

    beforeEach(function () {
        // Set a known date for testing
        Carbon::setTestNow('2025-03-14 08:00:00'); // Friday
    });

    afterEach(function () {
        Carbon::setTestNow(); // Reset
    });

    it('user with recurring schedule starting tomorrow afternoon should not be available during scheduled time', function () {
        $user = createUser();

        // Create a recurring schedule that starts tomorrow (Saturday) afternoon
        $schedule = Zap::for($user)
            ->named('Weekend Work')
            ->from('2025-03-15') // Tomorrow (Saturday)
            ->to('2025-12-31')
            ->addPeriod('14:00', '18:00') // Afternoon only
            ->weekly(['saturday', 'sunday'])
            ->save();

        // User should be available tomorrow morning (before the schedule starts)
        expect($user->isAvailableAt('2025-03-15', '09:00', '12:00'))->toBeTrue(
            'User should be available in the morning since schedule only covers afternoon'
        );

        // User should NOT be available during the scheduled time
        expect($user->isAvailableAt('2025-03-15', '14:00', '16:00'))->toBeFalse(
            'User should NOT be available during scheduled work hours'
        );

        // User should be available after the scheduled time
        expect($user->isAvailableAt('2025-03-15', '19:00', '20:00'))->toBeTrue(
            'User should be available after scheduled work hours'
        );

        // User should also be blocked on Sunday (next day in the weekly schedule)
        expect($user->isAvailableAt('2025-03-16', '14:00', '16:00'))->toBeFalse(
            'User should NOT be available on Sunday during scheduled hours'
        );

        // User should be available on Monday (not in the weekly schedule)
        expect($user->isAvailableAt('2025-03-17', '14:00', '16:00'))->toBeTrue(
            'User should be available on Monday (not in weekend schedule)'
        );
    });

    it('demonstrates the exact scenario from the user report', function () {
        $user = createUser();

        // Create a user with a recurring schedule which starts tomorrow in the afternoon
        $schedule = Zap::for($user)
            ->named('Afternoon Meetings')
            ->from('2025-03-15') // Tomorrow
            ->addPeriod('14:00', '17:00')
            ->weekly(['saturday'])
            ->save();

        // isAvailableAt should return false for the scheduled time, not true
        expect($user->isAvailableAt('2025-03-15', '14:00', '16:00'))->toBeFalse(
            'User should NOT be available during recurring schedule time'
        );

        // But should be available before the schedule starts
        expect($user->isAvailableAt('2025-03-15', '10:00', '12:00'))->toBeTrue(
            'User should be available before the recurring schedule starts'
        );
    });

    it('handles complex weekly recurring schedules correctly', function () {
        $user = createUser();

        // Monday, Wednesday, Friday schedule
        $schedule = Zap::for($user)
            ->named('MWF Classes')
            ->from('2025-03-17') // Next Monday
            ->addPeriod('10:00', '12:00')
            ->weekly(['monday', 'wednesday', 'friday'])
            ->save();

        // Test each day of the week
        expect($user->isAvailableAt('2025-03-17', '10:00', '11:00'))->toBeFalse('Monday should be blocked');
        expect($user->isAvailableAt('2025-03-18', '10:00', '11:00'))->toBeTrue('Tuesday should be available');
        expect($user->isAvailableAt('2025-03-19', '10:00', '11:00'))->toBeFalse('Wednesday should be blocked');
        expect($user->isAvailableAt('2025-03-20', '10:00', '11:00'))->toBeTrue('Thursday should be available');
        expect($user->isAvailableAt('2025-03-21', '10:00', '11:00'))->toBeFalse('Friday should be blocked');
        expect($user->isAvailableAt('2025-03-22', '10:00', '11:00'))->toBeTrue('Saturday should be available');
        expect($user->isAvailableAt('2025-03-23', '10:00', '11:00'))->toBeTrue('Sunday should be available');
    });

    it('handles daily recurring schedules correctly', function () {
        $user = createUser();

        $schedule = Zap::for($user)
            ->named('Daily Standup')
            ->from('2025-03-15') // Tomorrow
            ->addPeriod('09:00', '09:30')
            ->daily()
            ->save();

        // Should be blocked every day during the standup time
        expect($user->isAvailableAt('2025-03-15', '09:00', '09:30'))->toBeFalse('Saturday should be blocked');
        expect($user->isAvailableAt('2025-03-16', '09:00', '09:30'))->toBeFalse('Sunday should be blocked');
        expect($user->isAvailableAt('2025-03-17', '09:00', '09:30'))->toBeFalse('Monday should be blocked');

        // But available at other times
        expect($user->isAvailableAt('2025-03-15', '10:00', '11:00'))->toBeTrue('Should be available after standup');
    });

    it('handles monthly recurring schedules correctly', function () {
        $user = createUser();

        // First day of every month
        $schedule = Zap::for($user)
            ->named('Monthly Review')
            ->from('2025-04-01') // First day of April
            ->addPeriod('14:00', '16:00')
            ->monthly(['day_of_month' => 1])
            ->save();

        // Should be blocked on the 1st of each month
        expect($user->isAvailableAt('2025-04-01', '14:00', '16:00'))->toBeFalse('April 1st should be blocked');
        expect($user->isAvailableAt('2025-05-01', '14:00', '16:00'))->toBeFalse('May 1st should be blocked');
        expect($user->isAvailableAt('2025-06-01', '14:00', '16:00'))->toBeFalse('June 1st should be blocked');

        // But available on other days
        expect($user->isAvailableAt('2025-04-02', '14:00', '16:00'))->toBeTrue('April 2nd should be available');
        expect($user->isAvailableAt('2025-04-15', '14:00', '16:00'))->toBeTrue('April 15th should be available');
    });

    it('getAvailableSlots works correctly with recurring schedules', function () {
        $user = createUser();

        // Block afternoon every day
        $schedule = Zap::for($user)
            ->named('Afternoon Block')
            ->from('2025-03-15')
            ->addPeriod('13:00', '17:00')
            ->daily()
            ->save();

        $slots = $user->getAvailableSlots('2025-03-15', '08:00', '18:00', 60);

        // Check that morning slots are available
        $morningSlots = array_filter($slots, fn ($slot) => $slot['start_time'] < '13:00');
        foreach ($morningSlots as $slot) {
            expect($slot['is_available'])->toBeTrue(
                "Morning slot {$slot['start_time']}-{$slot['end_time']} should be available"
            );
        }

        // Check that afternoon slots are blocked
        $afternoonSlots = array_filter($slots, fn ($slot) => $slot['start_time'] >= '13:00' && $slot['start_time'] < '17:00');
        foreach ($afternoonSlots as $slot) {
            expect($slot['is_available'])->toBeFalse(
                "Afternoon slot {$slot['start_time']}-{$slot['end_time']} should be blocked"
            );
        }

        // Check that evening slots are available
        $eveningSlots = array_filter($slots, fn ($slot) => $slot['start_time'] >= '17:00');
        foreach ($eveningSlots as $slot) {
            expect($slot['is_available'])->toBeTrue(
                "Evening slot {$slot['start_time']}-{$slot['end_time']} should be available"
            );
        }
    });

    it('should make only two queries when execute getAvailableSlots', function () {
        $user = createUser();

        // Block afternoon every day
        Zap::for($user)
            ->named('Afternoon Block')
            ->from('2025-03-15')
            ->addPeriod('13:00', '17:00')
            ->daily()
            ->save();

        DB::enableQueryLog();
        $user->getAvailableSlots('2025-03-15', '08:00', '18:00', 60);
        $queries = DB::getQueryLog();
        expect(count($queries))->toBe(2);
    });

    it('getNextAvailableSlot works correctly with recurring schedules', function () {
        $user = createUser();

        // Block all working hours on weekdays
        $schedule = Zap::for($user)
            ->named('Weekday Work')
            ->from('2025-03-17') // Next Monday
            ->addPeriod('09:00', '17:00')
            ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
            ->save();

        // Looking for next available slot during working hours should find weekend
        $nextSlot = $user->getNextAvailableSlot('2025-03-17', 60, '09:00', '17:00');

        expect($nextSlot)->not->toBeNull('Should find an available slot');

        // Should be on a weekend
        $slotDate = Carbon::parse($nextSlot['date']);
        expect($slotDate->isWeekend())->toBeTrue(
            'Next available slot should be on weekend when weekdays are blocked'
        );
    });

    it('handles overlapping time periods correctly with recurring schedules', function () {
        $user = createUser();

        $schedule = Zap::for($user)
            ->named('Work Block')
            ->from('2025-03-15')
            ->addPeriod('09:00', '12:00')
            ->daily()
            ->save();

        // Partial overlap scenarios
        expect($user->isAvailableAt('2025-03-15', '08:00', '10:00'))->toBeFalse(
            'Should be blocked when overlapping with scheduled time'
        );
        expect($user->isAvailableAt('2025-03-15', '11:00', '13:00'))->toBeFalse(
            'Should be blocked when overlapping with scheduled time'
        );
        expect($user->isAvailableAt('2025-03-15', '08:00', '13:00'))->toBeFalse(
            'Should be blocked when completely overlapping with scheduled time'
        );
        expect($user->isAvailableAt('2025-03-15', '07:00', '08:00'))->toBeTrue(
            'Should be available when completely before scheduled time'
        );
        expect($user->isAvailableAt('2025-03-15', '13:00', '14:00'))->toBeTrue(
            'Should be available when completely after scheduled time'
        );
    });

    it('handles schedule end dates correctly', function () {
        $user = createUser();

        // Limited duration recurring schedule
        $schedule = Zap::for($user)
            ->named('Short Term Project')
            ->from('2025-03-15')
            ->to('2025-03-21') // Only one week
            ->addPeriod('09:00', '17:00')
            ->daily()
            ->save();

        // Should be blocked during the schedule period
        expect($user->isAvailableAt('2025-03-17', '10:00', '11:00'))->toBeFalse(
            'Should be blocked during active schedule period'
        );

        // Should be available after the schedule ends
        expect($user->isAvailableAt('2025-03-22', '10:00', '11:00'))->toBeTrue(
            'Should be available after schedule end date'
        );
    });

    it('handles multiple overlapping recurring schedules', function () {
        $user = createUser();

        // Morning recurring schedule
        $morningSchedule = Zap::for($user)
            ->named('Morning Routine')
            ->from('2025-03-15')
            ->addPeriod('08:00', '10:00')
            ->daily()
            ->save();

        // Evening recurring schedule (different frequency)
        $eveningSchedule = Zap::for($user)
            ->named('Evening Classes')
            ->from('2025-03-15')
            ->addPeriod('18:00', '20:00')
            ->weekly(['monday', 'wednesday', 'friday'])
            ->save();

        // Check availability on a Wednesday
        expect($user->isAvailableAt('2025-03-19', '08:00', '10:00'))->toBeFalse(
            'Morning should be blocked by daily schedule'
        );
        expect($user->isAvailableAt('2025-03-19', '12:00', '14:00'))->toBeTrue(
            'Midday should be available'
        );
        expect($user->isAvailableAt('2025-03-19', '18:00', '20:00'))->toBeFalse(
            'Evening should be blocked by weekly schedule on Wednesday'
        );

        // Check availability on a Tuesday (only morning blocked)
        expect($user->isAvailableAt('2025-03-18', '08:00', '10:00'))->toBeFalse(
            'Morning should be blocked by daily schedule'
        );
        expect($user->isAvailableAt('2025-03-18', '18:00', '20:00'))->toBeTrue(
            'Evening should be available on Tuesday (not in weekly schedule)'
        );
    });

    it('handles edge case time boundaries correctly', function () {
        $user = createUser();

        // Create schedule with specific times
        $schedule = Zap::for($user)
            ->named('Precise Schedule')
            ->from('2025-03-15')
            ->addPeriod('09:00', '17:00')
            ->daily()
            ->save();

        // Test various time formats and edge cases
        expect($user->isAvailableAt('2025-03-15', '08:59', '09:00'))->toBeTrue(
            'Should be available right before schedule starts'
        );
        expect($user->isAvailableAt('2025-03-15', '09:00', '09:01'))->toBeFalse(
            'Should be blocked right when schedule starts'
        );
        expect($user->isAvailableAt('2025-03-15', '16:59', '17:00'))->toBeFalse(
            'Should be blocked right before schedule ends'
        );
        expect($user->isAvailableAt('2025-03-15', '17:00', '17:01'))->toBeTrue(
            'Should be available right when schedule ends'
        );
    });

    it('isAvailableAt returns true for time slots after schedule ends', function () {
        // Issue #36: https://github.com/ludoguenet/zap-for-laravel/issues/36
        $start = Carbon::parse('2025-11-02 01:00:00');
        $end = Carbon::parse('2025-11-05 01:00:00');

        $schedule = Zap::for($user = createUser())
            ->named('Test Booking')
            ->appointment()
            ->from($start->toDateString())
            ->to($end->toDateString());

        // Add periods for the booking date range
        $schedule->addPeriod('01:00', '23:59', $start);
        $schedule->addPeriod('00:00', '23:59', Carbon::parse('2025-11-03'));
        $schedule->addPeriod('00:00', '23:59', Carbon::parse('2025-11-04'));
        $schedule->addPeriod('00:00', '01:00', $end); // Booking ends at 01:00 on 2025-11-05

        $schedule->save();

        $isAvailable = $user->isAvailableAt('2025-11-05', '02:00', '02:01');

        expect($isAvailable)->toBeTrue();
    });

});
