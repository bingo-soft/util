<?php

namespace Util\Reflection;

use Util\Reflection\Property\PropertyTokenizer;
use Util\Reflection\Wrapper\{
    ArrayWrapper,
    ObjectWrapper,
    ObjectWrapperInterface
};

class MetaObject extends \ReflectionObject
{
    private $originalObject;
    private $objectWrapper;
    private $initializedProperties = [];

    public function __construct(&$object = null, ?MetaObject $scope = null, ?string $propertyName = null)
    {
        $this->originalObject = &$object;
        if ($object instanceof ObjectWrapperInterface) {
            $this->objectWrapper = $object;
            parent::__construct($this->objectWrapper);
        } elseif (is_array($object) || $object instanceof \ArrayObject) {
            $this->objectWrapper = new ArrayWrapper($this, $object, $scope, $propertyName);
            parent::__construct($this->objectWrapper);
        } elseif ($object === null) {
            $null = new NullObject();
            self::__construct($null);
        } else {
            $this->objectWrapper = new ObjectWrapper($this, $object, $scope, $propertyName);
            parent::__construct($object);
        }
    }

    public function getOriginalObject()
    {
        return $this->originalObject;
    }

    public function findProperty(string $propName, bool $useCamelCaseMapping = false): ?string
    {
        return $this->objectWrapper->findProperty($propName, $useCamelCaseMapping);
    }

    public function hasSetter(string $name): bool
    {
        return $this->objectWrapper->hasSetter($name);
    }

    public function hasGetter(string $name): bool
    {
        return $this->objectWrapper->hasGetter($name);
    }

    public function getSetterType(string $name): string
    {
        return $this->objectWrapper->getSetterType($name);
    }

    public function getGetterType(string $name): string
    {
        return $this->objectWrapper->getGetterType($name);
    }

    public function getValue(string $name)
    {
        $prop = new PropertyTokenizer($name);
        if ($prop->valid()) {
            $metaValue = $this->metaObjectForProperty($prop->getIndexedName());
            if ($metaValue->getOriginalObject() instanceof NullObject) {
                return null;
            } else {
                return $metaValue->getValue($prop->getChildren());
            }
        } else {
            return $this->objectWrapper->get($prop);
        }
    }

    public function setValue(string $name, $value): void
    {
        $prop = new PropertyTokenizer($name);
        if ($prop->valid()) {
            $metaValue = $this->metaObjectForProperty($prop->getIndexedName());
            if ($metaValue->getOriginalObject() instanceof NullObject) {
                if ($value === null) {
                    return;
                } else {
                    $metaValue = $this->objectWrapper->instantiatePropertyValue($name, $prop);
                }
            }
            if (!in_array($name, $this->initializedProperties)) {
                $this->initializedProperties[] = $name;
            }            
            $metaValue->setValue($prop->getChildren(), $value);
        } else {
            $this->objectWrapper->set($prop, $value);
        }
    }

    public function metaObjectForProperty(string $name): MetaObject
    {
        $value = $this->getValue($name);
        return new MetaObject($value, $this, $name);
    }

    public function getGetterNames(): array
    {
        return $this->objectWrapper->getGetterNames();
    }

    public function getSetterNames(): array
    {
        return $this->objectWrapper->getSetterNames();
    }

    public function isCollection(): bool
    {
        return $this->objectWrapper->isCollection();
    }

    public function add($element): void
    {
        $this->objectWrapper->add($element);
    }

    public function addAll(array $list): void
    {
        $this->objectWrapper->addAll($list);
    }

    public function isPropertyInitialized(string $property): bool
    {
        return in_array($property, $this->initializedProperties);
    }
}
