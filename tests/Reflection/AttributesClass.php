<?php

namespace Tests\Reflection;

use Util\Reflection\Attributes\{
    ListType,
    ResultType
};

class AttributesClass
{
    private int $resources;

    #[ResultType(new ListType(ResourceClass::class))]
    public function getResources(): array
    {
    }
}