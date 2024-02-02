<?php

namespace Util\Reflection\Wrapper;

use Util\Reflection\MetaObject;
use Util\Reflection\Property\PropertyTokenizer;

abstract class BaseWrapper implements ObjectWrapperInterface
{
    protected static $NO_ARGUMENTS = [];
    protected $metaObject;
    protected $scope;
    protected $propertyName;

    public function __construct(MetaObject $metaObject, ?MetaObject $scope = null, ?string $propertyName = null)
    {
        $this->metaObject = $metaObject;
        $this->scope = $scope;
        $this->propertyName = $propertyName;
    }

    protected function resolveCollection(PropertyTokenizer $prop, &$object)
    {
        if ("" == $prop->getName()) {
            return $object;
        } else {
            return $this->metaObject->getValue($prop->getName());
        }
    }

    protected function getCollectionValue(PropertyTokenizer $prop, &$collection)
    {
        if (is_array($collection) || $collection instanceof \ArrayObject) {
            $i = $prop->getIndex();
            if ((is_array($collection) && array_key_exists($i, $collection)) || ($collection instanceof \ArrayObject && array_key_exists($i, $collection->getArrayCopy()))) {
                return $collection[$i];
            }
            return null;
        } else {
            throw new \ReflectionException("The '" . $prop->getName() . "' property ois not an array.");
        }
    }

    protected function setCollectionValue(PropertyTokenizer $prop, &$collection, $value): void
    {
        if (is_array($collection) || $collection instanceof \ArrayObject) {
            $collection[$prop->getIndex()] = $value;
        } else {
            throw new \ReflectionException("The '" . $prop->getName() . "' property is not an array.");
        }
    }
}
