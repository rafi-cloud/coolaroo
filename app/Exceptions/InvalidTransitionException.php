<?php

namespace App\Exceptions;

use BackedEnum;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InvalidTransitionException extends HttpException
{
    public function __construct(BackedEnum $from, BackedEnum $to)
    {
        parent::__construct(409, sprintf(
            'Cannot change %s from %s to %s.',
            class_basename($from),
            $from->value,
            $to->value,
        ));
    }
}
