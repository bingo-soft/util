<?php

namespace Util\Proxy;

class UnexpectedValueException extends \Exception
{
    public static function invalidParameterTypeHint(
        $className,
        $methodName,
        $parameterName,
        ?Throwable $previous = null
    ) {
        return new self(
            sprintf(
                'The type hint of parameter "%s" in method "%s" in class "%s" is invalid.',
                $parameterName,
                $methodName,
                $className
            ),
            0,
            $previous
        );
    }

    public static function invalidReturnTypeHint($className, $methodName, ?Throwable $previous = null)
    {
        return new self(
            sprintf(
                'The return type of method "%s" in class "%s" is invalid.',
                $methodName,
                $className
            ),
            0,
            $previous
        );
    }
}
