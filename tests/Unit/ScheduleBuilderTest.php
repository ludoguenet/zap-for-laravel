<?php

use Zap\Builders\ScheduleBuilder;
use Zap\Models\Schedule;

describe('ScheduleBuilder', function () {

    it('can build schedule attributes correctly', function () {
        $user = createUser();

        $builder = new ScheduleBuilder;
        $built = $builder
            ->for($user)
            ->named('Test Meeting')
            ->description('A test meeting')
            ->from('2025-01-01')
            ->to('2025-12-31')
            ->addPeriod('09:00', '10:00')
            ->weekly(['monday'])
            ->withMetadata(['room' => 'A'])
            ->build();

        expect($built['attributes'])->toHaveKey('name', 'Test Meeting');
        expect($built['attributes'])->toHaveKey('description', 'A test meeting');
        expect($built['attributes'])->toHaveKey('start_date', '2025-01-01');
        expect($built['attributes'])->toHaveKey('end_date', '2025-12-31');
        expect($built['attributes'])->toHaveKey('is_recurring', true);
        expect($built['attributes'])->toHaveKey('frequency', 'weekly');
        expect($built['periods'])->toHaveCount(1);
        expect($built['periods'][0])->toMatchArray([
            'start_time' => '09:00',
            'end_time' => '10:00',
            'date' => '2025-01-01',
        ]);
    });

    it('can add multiple periods', function () {
        $user = createUser();

        $builder = new ScheduleBuilder;
        $built = $builder
            ->for($user)
            ->from('2025-01-01')
            ->addPeriod('09:00', '10:00')
            ->addPeriod('14:00', '15:00')
            ->addPeriods([
                ['start_time' => '16:00', 'end_time' => '17:00'],
            ])
            ->build();

        expect($built['periods'])->toHaveCount(3);
        expect($built['periods'][0]['start_time'])->toBe('09:00');
        expect($built['periods'][1]['start_time'])->toBe('14:00');
        expect($built['periods'][2]['start_time'])->toBe('16:00');
    });

    it('can set different recurring frequencies', function () {
        $user = createUser();

        $builder = new ScheduleBuilder;

        // Test daily
        $daily = $builder->for($user)->from('2025-01-01')->daily()->build();
        expect($daily['attributes']['frequency'])->toBe('daily');

        // Test weekly
        $builder->reset();
        $weekly = $builder->for($user)->from('2025-01-01')->weekly(['monday', 'friday'])->build();
        expect($weekly['attributes']['frequency'])->toBe('weekly');
        expect($weekly['attributes']['frequency_config'])->toBe(['days' => ['monday', 'friday']]);

        // Test monthly
        $builder->reset();
        $monthly = $builder->for($user)->from('2025-01-01')->monthly(['day_of_month' => 15])->build();
        expect($monthly['attributes']['frequency'])->toBe('monthly');
        expect($monthly['attributes']['frequency_config'])->toBe(['day_of_month' => 15]);
    });

    it('can add validation rules', function () {
        $user = createUser();

        $builder = new ScheduleBuilder;
        $built = $builder
            ->for($user)
            ->from('2025-01-01')
            ->noOverlap()
            ->workingHoursOnly('09:00', '17:00')
            ->maxDuration(480)
            ->noWeekends()
            ->withRule('custom_rule', ['param' => 'value'])
            ->build();

        expect($built['rules'])->toHaveKey('no_overlap');
        expect($built['rules'])->toHaveKey('working_hours');
        expect($built['rules']['working_hours'])->toBe(['start' => '09:00', 'end' => '17:00']);
        expect($built['rules'])->toHaveKey('max_duration');
        expect($built['rules']['max_duration'])->toBe(['minutes' => 480]);
        expect($built['rules'])->toHaveKey('no_weekends');
        expect($built['rules'])->toHaveKey('custom_rule');
        expect($built['rules']['custom_rule'])->toBe(['param' => 'value']);
    });

    it('can handle metadata', function () {
        $user = createUser();

        $builder = new ScheduleBuilder;
        $built = $builder
            ->for($user)
            ->from('2025-01-01')
            ->withMetadata(['location' => 'Room A'])
            ->withMetadata(['priority' => 'high']) // Should merge
            ->build();

        expect($built['attributes']['metadata'])->toBe([
            'location' => 'Room A',
            'priority' => 'high',
        ]);
    });

    it('can set active/inactive status', function () {
        $user = createUser();

        $builder = new ScheduleBuilder;

        // Test active (default)
        $active = $builder->for($user)->from('2025-01-01')->active()->build();
        expect($active['attributes']['is_active'])->toBe(true);

        // Test inactive
        $builder->reset();
        $inactive = $builder->for($user)->from('2025-01-01')->inactive()->build();
        expect($inactive['attributes']['is_active'])->toBe(false);
    });

    it('can clone builder with same configuration', function () {
        $user = createUser();

        $builder = new ScheduleBuilder;
        $builder
            ->for($user)
            ->named('Original')
            ->from('2025-01-01')
            ->addPeriod('09:00', '10:00')
            ->weekly(['monday']);

        $clone = $builder->clone();
        $clone->named('Cloned');

        $original = $builder->build();
        $cloned = $clone->build();

        expect($original['attributes']['name'])->toBe('Original');
        expect($cloned['attributes']['name'])->toBe('Cloned');
        expect($original['attributes']['start_date'])->toBe($cloned['attributes']['start_date']);
        expect($original['periods'])->toEqual($cloned['periods']);
    });

    it('validates required fields', function () {
        $builder = new ScheduleBuilder;

        // Missing schedulable
        expect(fn () => $builder->from('2025-01-01')->build())
            ->toThrow(\InvalidArgumentException::class, 'Schedulable model must be set');

        // Missing start date
        $user = createUser();
        $builder->reset(); // Reset builder state
        expect(fn () => $builder->for($user)->build())
            ->toThrow(\InvalidArgumentException::class, 'Start date must be set');
    });

    it('can use between method for date range', function () {
        $user = createUser();

        $builder = new ScheduleBuilder;
        $built = $builder
            ->for($user)
            ->between('2025-01-01', '2025-12-31')
            ->build();

        expect($built['attributes']['start_date'])->toBe('2025-01-01');
        expect($built['attributes']['end_date'])->toBe('2025-12-31');
    });

    it('provides getter methods for current state', function () {
        $user = createUser();

        $builder = new ScheduleBuilder;
        $builder
            ->for($user)
            ->named('Test')
            ->from('2025-01-01')
            ->addPeriod('09:00', '10:00')
            ->noOverlap();

        expect($builder->getAttributes())->toHaveKey('name', 'Test');
        expect($builder->getPeriods())->toHaveCount(1);
        expect($builder->getRules())->toHaveKey('no_overlap');
    });

});

describe('ScheduleBuilder Integration', function () {

    it('integrates with ScheduleService for saving', function () {
        $user = createUser();

        $schedule = (new ScheduleBuilder)
            ->for($user)
            ->named('Integration Test')
            ->from('2025-01-01')
            ->addPeriod('09:00', '10:00')
            ->save();

        expect($schedule)->toBeInstanceOf(Schedule::class);
        expect($schedule->name)->toBe('Integration Test');
    });

});
