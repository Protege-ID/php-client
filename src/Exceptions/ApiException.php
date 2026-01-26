<?php

namespace ProtegeId\Exceptions;

use RuntimeException;

class ApiException extends RuntimeException
{
    private ?array $errorBody;

    public function __construct(
        string $message,
        int $code = 0,
        ?array $errorBody = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorBody = $errorBody;
    }

    public function getErrorBody(): ?array
    {
        return $this->errorBody;
    }
}
