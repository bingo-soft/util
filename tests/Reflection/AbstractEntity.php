<?php

namespace Tests\Reflection;

use Util\Reflection\Attributes\Impl;

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
