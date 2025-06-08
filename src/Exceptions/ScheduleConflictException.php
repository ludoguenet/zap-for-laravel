<?php

namespace Zap\Exceptions;

class ScheduleConflictException extends ZapException
{
    /**
     * The conflicting schedules.
     */
    protected array $conflictingSchedules = [];

    /**
     * Create a new schedule conflict exception.
     */
    public function __construct(
        string $message = 'Schedule conflicts detected',
        int $code = 409,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Set the conflicting schedules.
     */
    public function setConflictingSchedules(array $schedules): self
    {
        $this->conflictingSchedules = $schedules;

        return $this;
    }

    /**
     * Get the conflicting schedules.
     */
    public function getConflictingSchedules(): array
    {
        return $this->conflictingSchedules;
    }

    /**
     * Get the exception as an array with conflict details.
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'conflicting_schedules' => $this->getConflictingSchedules(),
            'conflict_count' => count($this->conflictingSchedules),
        ]);
    }
}
