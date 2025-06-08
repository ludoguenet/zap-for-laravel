<?php

namespace Zap\Exceptions;

class InvalidScheduleException extends ZapException
{
    /**
     * The validation errors.
     */
    protected array $errors = [];

    /**
     * Create a new invalid schedule exception.
     */
    public function __construct(
        string $message = 'Invalid schedule data provided',
        int $code = 422,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Set the validation errors.
     */
    public function setErrors(array $errors): self
    {
        $this->errors = $errors;

        return $this;
    }

    /**
     * Get the validation errors.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get the exception as an array with validation errors.
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'errors' => $this->getErrors(),
            'error_count' => count($this->errors),
        ]);
    }
}
