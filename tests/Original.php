<?php

namespace Tests;

class Original implements InterInterface
{
    public function originalMethod(string $s, $d = null, int $z = 1)
    {
        return $s;
    }
}
