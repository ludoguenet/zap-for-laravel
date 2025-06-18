<?php

declare(strict_types=1);

namespace Zap\Support;

use Carbon\Carbon;
use InvalidArgumentException;

final readonly class DateRange
{
    public function __construct(
        private Carbon $startDate,
        private Carbon $endDate,
    ) {
        if ($this->endDate->lte($this->startDate)) {
            throw new InvalidArgumentException('Range date cannot ends before it starts.');
        }
    }

    public function overlapsWith(DateRange $otherRange): bool
    {
        return true;
    }
}
