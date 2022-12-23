<?php

namespace Tests\Reflection;

use Util\Reflection\Attributes\Impl;

#[Impl([MetaParent::class => ["T" => "string"]])]
class Child extends MetaParent
{
    private Child $child;

    public function setChild(Child $child): void
    {
        $this->child = $child;
    }
}