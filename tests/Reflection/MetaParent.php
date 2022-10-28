<?php

namespace Tests\Reflection;

use Util\Reflection\Generics\{
    Params,
    Prop,
    Returns,
    Template
};

#[Template("T")]
abstract class MetaParent
{
    protected $id;
    protected $list = [];
    protected $array = [];

    #[Prop("T")]
    private $fld;
    
    public $pubFld;

    #[Returns("T")]
    public function getId()
    {
        return $this->id;
    }

    #[Params(id: "T")]
    public function setId($id)
    {
        $this->id = $id;
    }

    public function getList(): array
    {
        return $this->list;
    }

    public function setList(array $list): void
    {
        $this->list = $list;
    }

    public function getArray(): array
    {
        return $this->array;
    }

    public function setArray(array $array): void
    {
        $this->array = $array;
    }

    #[Returns("T")]
    public function getFld()
    {
        return $this->fld;
    }
}
