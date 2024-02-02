<?php

namespace Tests\Proxy;

class BusinessServiceImpl
{
    public function doSomething(int $id, string $name)
    {
        return "$id - $name";
    }
}
