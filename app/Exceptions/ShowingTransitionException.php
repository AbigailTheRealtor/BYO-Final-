<?php

namespace App\Exceptions;

use RuntimeException;

class ShowingTransitionException extends RuntimeException
{
    private string $fromStatus;
    private string $toStatus;

    public function __construct(string $fromStatus, string $toStatus)
    {
        $this->fromStatus = $fromStatus;
        $this->toStatus   = $toStatus;

        parent::__construct(
            "Cannot transition showing from '{$fromStatus}' to '{$toStatus}'."
        );
    }

    public function getFromStatus(): string
    {
        return $this->fromStatus;
    }

    public function getToStatus(): string
    {
        return $this->toStatus;
    }
}
