<?php

namespace Tests\Reflection\Beans;

use Util\Reflection\Generics\{
    Params,
    Returns,
    Template
};

#[Template("T")]
interface BeanInterface
{
    #[Params(id: "T")]
    public function setId($id): void;
}
