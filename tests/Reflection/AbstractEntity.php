<?php

namespace Tests\Reflection;

use Util\Reflection\Generics\Impl;

#[Impl([EntityInterface::class => ["T" => "int"]])]
class AbstractEntity implements EntityInterface
{
    protected $id;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id): void
    {
        $this->id = $id;
    }
}
