<?php

namespace Tests;

class BusinessServiceImpl
{
    public function doSomething(int $id, string $name)
    {
        return "$id - $name";
    }
}
