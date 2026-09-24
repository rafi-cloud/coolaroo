<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class AiUnavailableException extends HttpException
{
    public function __construct()
    {
        parent::__construct(503, 'The assistant is busy right now — please try again in a moment.');
    }
}
