<?php

use Zap\Exceptions\InvalidScheduleException;
use Zap\Exceptions\ScheduleConflictException;
use Zap\Facades\Zap;
use Zap\Models\Schedule;

describe('Rule Control', function () {

    describe('Individual Rule Control', function () {

        it('can disable working hours rule', function () {
            $user = createUser();

            // This should succeed even though it's outside working hours when rule is explicitly disabled
            $schedule = Zap::for($user)
                ->from('2025-01-01')
                ->addPeriod('18:00', '19:00') // Outside normal working hours
                ->withRule('working_hours', [
                    'enabled' => false,
                    'start_time' => '09:00',
                    'end_time' => '17:00',
                ])
                ->save();

            expect($schedule)->toBeInstanceOf(Schedule::class);
        });

        it('can enable working hours rule', function () {
            config(['zap.default_rules.working_hours.enabled' => true]);

            $user = createUser();

            // This should fail when working hours rule is enabled
            expect(function () use ($user) {
                Zap::for($user)
                    ->from('2025-01-01')
                    ->addPeriod('18:00', '19:00') // Outside working hours
                    ->workingHoursOnly('09:00', '17:00')
                    ->save();
            })->toThrow(InvalidScheduleException::class);
        });

        it('can disable max duration rule', function () {
            $user = createUser();

            // This should succeed even though it exceeds max duration when rule is explicitly disabled
            $schedule = Zap::for($user)
                ->from('2025-01-01')
                ->addPeriod('09:00', '18:00') // 9 hours
                ->withRule('max_duration', [
                    'enabled' => false,
                    'minutes' => 480,
                ])
                ->save();

            expect($schedule)->toBeInstanceOf(Schedule::class);
        });

        it('can enable max duration rule', function () {
            config(['zap.default_rules.max_duration.enabled' => true]);

            $user = createUser();

            // This should fail when max duration rule is enabled
            expect(function () use ($user) {
                Zap::for($user)
                    ->from('2025-01-01')
                    ->addPeriod('09:00', '18:00') // 9 hours
                    ->maxDuration(480) // 8 hours max
                    ->save();
            })->toThrow(InvalidScheduleException::class);
        });

        it('can disable no weekends rule', function () {
            config(['zap.default_rules.no_weekends.enabled' => false]);

            $user = createUser();

            // This should succeed even though it's a weekend
            $schedule = Zap::for($user)
                ->from('2025-01-05') // Sunday
                ->addPeriod('09:00', '10:00')
                ->noWeekends()
                ->save();

            expect($schedule)->toBeInstanceOf(Schedule::class);
        });

        it('can enable no weekends rule', function () {
            config(['zap.default_rules.no_weekends.enabled' => true]);

            $user = createUser();

            // This should fail when no weekends rule is enabled
            expect(function () use ($user) {
                Zap::for($user)
                    ->from('2025-01-05') // Sunday
                    ->addPeriod('09:00', '10:00')
                    ->noWeekends()
                    ->save();
            })->toThrow(InvalidScheduleException::class);
        });

    });

    describe('No Overlap Rule Control', function () {

        it('can disable no_overlap rule for appointment schedules', function () {
            config(['zap.default_rules.no_overlap.enabled' => false]);

            $user = createUser();

            // Create first appointment
            Zap::for($user)
                ->named('First Appointment')
                ->appointment()
                ->from('2025-01-01')
                ->addPeriod('09:00', '10:00')
                ->save();

            // This should succeed when no_overlap rule is disabled
            $schedule = Zap::for($user)
                ->named('Second Appointment')
                ->appointment()
                ->from('2025-01-01')
                ->addPeriod('09:30', '10:30') // Overlaps with first appointment
                ->save();

            expect($schedule)->toBeInstanceOf(Schedule::class);
        });

        it('can enable no_overlap rule for appointment schedules', function () {
            config(['zap.default_rules.no_overlap.enabled' => true]);

            $user = createUser();

            // Create first appointment
            Zap::for($user)
                ->named('First Appointment')
                ->appointment()
                ->from('2025-01-01')
                ->addPeriod('09:00', '10:00')
                ->save();

            // This should fail when no_overlap rule is enabled
            expect(function () use ($user) {
                Zap::for($user)
                    ->named('Second Appointment')
                    ->appointment()
                    ->from('2025-01-01')
                    ->addPeriod('09:30', '10:30') // Overlaps with first appointment
                    ->save();
            })->toThrow(ScheduleConflictException::class);
        });

        it('can disable no_overlap rule for blocked schedules', function () {
            config(['zap.default_rules.no_overlap.enabled' => false]);

            $user = createUser();

            // Create first blocked schedule
            Zap::for($user)
                ->named('First Block')
                ->blocked()
                ->from('2025-01-01')
                ->addPeriod('09:00', '10:00')
                ->save();

            // This should succeed when no_overlap rule is disabled
            $schedule = Zap::for($user)
                ->named('Second Block')
                ->blocked()
                ->from('2025-01-01')
                ->addPeriod('09:30', '10:30') // Overlaps with first block
                ->save();

            expect($schedule)->toBeInstanceOf(Schedule::class);
        });

        it('respects applies_to configuration for no_overlap rule', function () {
            config([
                'zap.default_rules.no_overlap.enabled' => true,
                'zap.default_rules.no_overlap.applies_to' => ['appointment'], // Only apply to appointments
            ]);

            $user = createUser();

            // Create first blocked schedule
            Zap::for($user)
                ->named('First Block')
                ->blocked()
                ->from('2025-01-01')
                ->addPeriod('09:00', '10:00')
                ->save();

            // This should succeed because no_overlap rule doesn't apply to blocked schedules
            $schedule = Zap::for($user)
                ->named('Second Block')
                ->blocked()
                ->from('2025-01-01')
                ->addPeriod('09:30', '10:30') // Overlaps with first block
                ->save();

            expect($schedule)->toBeInstanceOf(Schedule::class);
        });

        it('can override no_overlap rule explicitly', function () {
            config(['zap.default_rules.no_overlap.enabled' => true]);

            $user = createUser();

            // Create first appointment
            Zap::for($user)
                ->named('First Appointment')
                ->appointment()
                ->from('2025-01-01')
                ->addPeriod('09:00', '10:00')
                ->save();

            // This should succeed when explicitly disabling no_overlap
            $schedule = Zap::for($user)
                ->named('Second Appointment')
                ->appointment()
                ->from('2025-01-01')
                ->addPeriod('09:30', '10:30') // Overlaps with first appointment
                ->withRule('no_overlap', ['enabled' => false])
                ->save();

            expect($schedule)->toBeInstanceOf(Schedule::class);
        });

    });

    describe('Rule Merging', function () {

        it('merges provided rules with default rules', function () {
            config([
                'zap.default_rules.working_hours.enabled' => true,
                'zap.default_rules.max_duration.enabled' => true,
                'zap.default_rules.no_weekends.enabled' => false,
            ]);

            $user = createUser();

            // This should fail due to default working hours rule
            expect(function () use ($user) {
                Zap::for($user)
                    ->from('2025-01-01')
                    ->addPeriod('18:00', '19:00') // Outside working hours
                    ->save();
            })->toThrow(InvalidScheduleException::class);
        });

        it('allows overriding default rules with explicit rules', function () {
            config([
                'zap.default_rules.working_hours.enabled' => true,
                'zap.default_rules.working_hours.start' => '09:00',
                'zap.default_rules.working_hours.end' => '17:00',
            ]);

            $user = createUser();

            // This should succeed with custom working hours
            $schedule = Zap::for($user)
                ->from('2025-01-01')
                ->addPeriod('18:00', '19:00') // Outside default working hours
                ->workingHoursOnly('17:00', '20:00') // Custom working hours
                ->save();

            expect($schedule)->toBeInstanceOf(Schedule::class);
        });

        it('can disable default rules with explicit false', function () {
            config([
                'zap.default_rules.working_hours.enabled' => true,
                'zap.default_rules.working_hours.start' => '09:00',
                'zap.default_rules.working_hours.end' => '17:00',
            ]);

            $user = createUser();

            // This should succeed by explicitly disabling working hours
            $schedule = Zap::for($user)
                ->from('2025-01-01')
                ->addPeriod('18:00', '19:00') // Outside working hours
                ->withRule('working_hours', ['enabled' => false])
                ->save();

            expect($schedule)->toBeInstanceOf(Schedule::class);
        });

    });

    describe('Global Conflict Detection Control', function () {

        it('can disable all conflict detection', function () {
            config(['zap.conflict_detection.enabled' => false]);

            $user = createUser();

            // Create first appointment
            Zap::for($user)
                ->named('First Appointment')
                ->appointment()
                ->from('2025-01-01')
                ->addPeriod('09:00', '10:00')
                ->save();

            // This should succeed when conflict detection is disabled
            $schedule = Zap::for($user)
                ->named('Second Appointment')
                ->appointment()
                ->from('2025-01-01')
                ->addPeriod('09:30', '10:30') // Overlaps with first appointment
                ->save();

            expect($schedule)->toBeInstanceOf(Schedule::class);
        });

        it('respects global conflict detection setting over rule settings', function () {
            config([
                'zap.conflict_detection.enabled' => false,
                'zap.default_rules.no_overlap.enabled' => true,
            ]);

            $user = createUser();

            // Create first appointment
            Zap::for($user)
                ->named('First Appointment')
                ->appointment()
                ->from('2025-01-01')
                ->addPeriod('09:00', '10:00')
                ->save();

            // This should succeed because global conflict detection is disabled
            $schedule = Zap::for($user)
                ->named('Second Appointment')
                ->appointment()
                ->from('2025-01-01')
                ->addPeriod('09:30', '10:30') // Overlaps with first appointment
                ->save();

            expect($schedule)->toBeInstanceOf(Schedule::class);
        });

    });

});
