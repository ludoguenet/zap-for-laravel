<?php

use Zap\Builders\ScheduleBuilder;
use Zap\Facades\Zap;
use Zap\Models\Schedule;

describe('Schedule', function () {
    it('filters schedules correctly using forDate scope with different start and end date', function () {
        $user = createUser();

        $schedule = Zap::for($user)
            ->from('2025-11-11')
            ->to('2025-12-12')
            ->addPeriod('09:00', '10:00')
            ->save();

        $savedSchedule = Schedule::forDate('2025-11-11')->first();

        expect($savedSchedule)->toBeInstanceOf(Schedule::class)
            ->and($schedule->id)->toEqual($savedSchedule->id);
    });

    it('filters schedules correctly using forDate scope with different start and end date and query outside upper bound', function () {
        $user = createUser();

        Zap::for($user)
            ->from('2025-11-11')
            ->to('2025-11-12')
            ->addPeriod('09:00', '10:00')
            ->save();

        $savedSchedule = Schedule::forDate('2025-11-15')->first();

        expect($savedSchedule)->toBeNull();
    });

    it('filters schedules correctly using forDate scope with different start and end date and query outside lower bound', function () {
        $user = createUser();

        Zap::for($user)
            ->from('2025-11-11')
            ->to('2025-11-12')
            ->addPeriod('09:00', '10:00')
            ->save();

        $savedSchedule = Schedule::forDate('2025-05-15')->first();

        expect($savedSchedule)->toBeNull();
    });

    it('filters schedules correctly using forDate scope with start date only', function () {
        $user = createUser();

        $schedule = Zap::for($user)
            ->from('2025-11-11')
            ->addPeriod('09:00', '10:00')
            ->save();

        $savedSchedule = Schedule::forDate('2025-11-11')->first();

        expect($savedSchedule)->toBeInstanceOf(Schedule::class)
            ->and($schedule->id)->toEqual($savedSchedule->id);
    });

    it('filters schedules correctly using forDate scope with start date only and query outside upper bound', function () {
        $user = createUser();

        Zap::for($user)
            ->from('2025-11-11')
            ->addPeriod('09:00', '10:00')
            ->save();

        $savedSchedule = Schedule::forDate('2025-11-12')->first();

        expect($savedSchedule)->toBeNull();
    });

    it('filters schedules correctly using forDate scope with start date only and query outside lower bound', function () {
        $user = createUser();

        Zap::for($user)
            ->from('2025-11-11')
            ->addPeriod('09:00', '10:00')
            ->save();

        $savedSchedule = Schedule::forDate('2025-05-11')->first();

        expect($savedSchedule)->toBeNull();
    });
});
