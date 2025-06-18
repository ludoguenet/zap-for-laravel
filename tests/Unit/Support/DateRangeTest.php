<?php

declare(strict_types=1);

use Carbon\Carbon;
use Zap\Support\DateRange;

describe('DateRange', function (): void {
    it('can be instantiated with a valid start date and end date', function (): void {
        $range = new DateRange(
            Carbon::parse('18-06-2025 12:00'),
            Carbon::parse('18-06-2025 18:00'),
        );

        expect($range)->toBeInstanceOf(DateRange::class);
    });

    it('throws if range ends before it starts', function (): void {
        expect(fn () => new DateRange(
            Carbon::parse('18-06-2025 18:00'),
            Carbon::parse('18-06-2025 12:00'),
        ))->toThrow(InvalidArgumentException::class, 'Range date cannot ends before it starts.');
    });

    it('should detect overlap cases', function (): void {
        todo('implement overlapsWith(DateRange $other): bool method');
    });
});
