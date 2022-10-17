<?php

namespace Util\Reflection\Wrapper;

use Util\Reflection\MetaObject;
use Util\Reflection\Property\PropertyTokenizer;

interface ObjectWrapperInterface
{
    public function get(PropertyTokenizer $prop);

    public function set(PropertyTokenizer $prop, &$value): void;

    public function findProperty(string $name, bool $useCamelCaseMapping = false): ?string;

    public function instantiatePropertyValue(string $name, PropertyTokenizer $prop): MetaObject;
}
