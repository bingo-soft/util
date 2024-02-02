<?php

namespace Tests\Reflection;

use Util\Reflection\Attributes\{
    Params,
    Returns,
    Template
};

#[Template("T")]
interface EntityInterface
{
    #[Returns("T")]
    public function getId();

    #[Params(id: "T")]
    public function setId($id): void;
}
