<?php

namespace Util\Reflection\Wrapper;

use Util\Reflection\{
    MetaObject,
    NullObject
};
use Util\Reflection\Property\PropertyTokenizer;

class ArrayWrapper extends BaseWrapper
{
    private $map;
    private $id;

    public function __construct(MetaObject $metaObject, &$map, ?MetaObject $scope = null, ?string $propertyName = null)
    {
        parent::__construct($metaObject, $scope, $propertyName);
        $this->map = &$map;
        $this->id = hrtime(true);
    }

    public function get(PropertyTokenizer $prop)
    {
        if ($prop->getIndex() !== null) {
            $collection = $this->resolveCollection($prop, $this->map);
            return $this->getCollectionValue($prop, $collection);
        } else {
            $key = $prop->getName();
            if (is_array($this->map) && array_key_exists($key, $this->map)) {
                return $this->map[$key];
            } elseif ($this->map instanceof \ArrayObject && method_exists($this->map, 'get')) {
                return $this->map->get($key);
            }
            return null;
        }
    }

    private function containsKey(string $key): bool
    {
        return (is_array($this->map) && array_key_exists($key, $this->map)) || ($this->map instanceof \ArrayObject && array_key_exists($key, $this->map->getArrayCopy()));
    }

    public function set(PropertyTokenizer $prop, &$value): void
    {
        if ($prop->getIndex() !== null) {
            $collection = $this->resolveCollection($prop, $this->map);
            $this->setCollectionValue($prop, $collection, $value);
            if ($this->map instanceof \ArrayObject) {
                $this->map[$prop->getName()] = $collection;
            }
        } else {
            $this->map[$prop->getName()] = &$value;
            if ($this->scope !== null && $this->propertyName !== null) {
                $this->scope->setValue($this->propertyName, $this->map);
            }
        }
    }

    public function findProperty(string $name, bool $useCamelCaseMapping = false): ?string
    {
        return $name;
    }

    public function getGetterNames(): array
    {
        return array_keys($this->map);
    }

    public function getSetterNames(): array
    {
        return array_keys($this->map);
    }

    public function getSetterType(string $name) {
        $prop = new PropertyTokenizer($name);
        if ($prop->valid()) {
            $metaValue = $this->metaObject->metaObjectForProperty($prop->getIndexedName());
            if ($metaValue->getOriginalObject() instanceof NullObject) {
                return "object";
            } else {
                return $metaValue->getSetterType($prop->getChildren());
            }
        } else {
            if ($this->containsKey($name)) {
                return is_object($map[$name]) ? get_class($map[$name]) : gettype($map[$name]);
            } else {
                return "object";
            }
        }
    }
    
    public function getGetterType(string $name) {
        $prop = new PropertyTokenizer($name);
        if ($prop->valid()) {
            $metaValue = $this->metaObject->metaObjectForProperty($prop->getIndexedName());
            if ($metaValue->getOriginalObject() instanceof NullObject) {
                return "object";
            } else {
                return $metaValue->getGetterType($prop->getChildren());
            }
        } else {
            if ($this->containsKey($name)) {
                return is_object($this->map[$name]) ? get_class($this->map[$name]) : gettype($this->map[$name]);
            } else {
                return "object";
            }
        }
    }

    public function add($element): void
    {
        $this->map[] = $element;
    }

    public function addAll(array $element): void
    {
        $this->map = array_merge($this->map, $element);
    }

    public function hasSetter(string $name): bool
    {
        return true;
    }

    public function hasGetter(string $name): bool
    {
        $prop = new PropertyTokenizer($name);
        if ($prop->valid()) {
            if ($this->containsKey($prop->getIndexedName())) {
                $metaValue = $this->metaObject->metaObjectForProperty($prop->getIndexedName());
                if ($metaValue->getOriginalObject() instanceof NullObject) {
                    return true;
                } else {
                    return $metaValue->hasGetter($prop->getChildren());
                }
            } else {
                return false;
            }
        } else {
            return $this->containsKey($prop->getName());
        }
    }

    public function instantiatePropertyValue(string $name, PropertyTokenizer $prop): MetaObject
    {
        $map = [];
        $this->set($prop, $map);
        return new MetaObject($map, null, $prop->getName());
    }

    public function isCollection(): bool
    {
        return true;
    }
}
