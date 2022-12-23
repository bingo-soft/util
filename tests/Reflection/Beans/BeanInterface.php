<?php

namespace Tests\Reflection\Beans;

use Util\Reflection\Attributes\{
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
