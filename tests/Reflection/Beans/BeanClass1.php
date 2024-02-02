<?php

namespace Tests\Reflection\Beans;

use Util\Reflection\Attributes\Impl;

#[Impl([BeanInterface::class => ["T" => "string"]])]
class BeanClass1 implements BeanInterface
{
    public function setId($id): void
    {
        // Do nothing
    }
}