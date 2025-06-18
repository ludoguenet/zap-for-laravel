<?php

declare(strict_types=1);

use Zap\Facades\Zap;

describe('DateRange Overlapping', function (): void {
    it('detects overlapping schedules via `overlapsWithDateRange`', function (): void {
        $user = createUser();

        $schedule1 = Zap::for($user)
            ->from('2025-06-18')
            ->addPeriod('08:00', '10:00')
            ->save();

        $schedule2 = Zap::for($user)
            ->from('2025-06-18')
            ->addPeriod('09:30', '11:00')
            ->save();

        expect($schedule1->overlapsWithDateRange($schedule2))->toBeTrue();
    });

    it('detects non-overlapping schedules via `overlapsWithDateRange`', function (): void {
        $user = createUser();
        $schedule1 = Zap::for($user)
            ->from('2025-06-18')
            ->addPeriod('08:00', '10:00')
            ->save();

        $schedule2 = Zap::for($user)
            ->from('2025-06-18')
            ->addPeriod('10:05', '11:30')
            ->save();

        expect($schedule1->overlapsWithDateRange($schedule2))->toBeFalse();
    });
});
