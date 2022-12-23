<?php

namespace Util\Reflection\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class ListType implements ParametrizedType
{
    public function __construct(private mixed $value)
    {
    }

    public function value(): mixed
    {
        return $this->value;
    }
}
