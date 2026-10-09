<?php

namespace Simple\Validation;

use Simple\Http\HttpException;

class ValidationException extends HttpException
{
    /** @param array<string, string[]> $errors */
    public function __construct(array $errors, string $message = 'Validation failed')
    {
        parent::__construct(422, $message, $errors);
    }
}
