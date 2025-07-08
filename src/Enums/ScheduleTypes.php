<?php

namespace Zap\Enums;

enum ScheduleTypes: string
{
    case AVAILABILITY = 'availability';

    case APPOINTMENT = 'appointment';

    case BLOCKED = 'blocked';

    case CUSTOM = 'custom';

    /**
     * Get all available schedule types.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return collect(self::cases())
            ->map(fn (ScheduleTypes $type): string => $type->value)
            ->all();
    }

    /**
     * Check this schedule type is of a specific availability type.
     */
    public function is(ScheduleTypes $type): bool
    {
        return $this->value === $type->value;
    }

    /**
     * Get the types that allow overlaps.
     */
    public function allowsOverlaps(): bool
    {
        return match ($this) {
            self::AVAILABILITY, self::CUSTOM => true,
            default => false,
        };
    }

    /**
     * Get types that prevent overlaps.
     */
    public function preventsOverlaps(): bool
    {
        return match ($this) {
            self::APPOINTMENT, self::BLOCKED => true,
            default => false,
        };
    }
}
