<?php

namespace Util\Reflection;

use Util\Reflection\Attributes\{
    Impl,
    ParametrizedType,
    Params,
    Prop,
    Returns,
    ResultType
};
use Util\Reflection\Invoker\{
    GetFieldInvoker,
    InvokerInterface,
    MethodInvoker,
    SetFieldInvoker
};
use Util\Reflection\Property\PropertyNamer;

class MetaReflector
{
    private $type;
    private $readablePropertyNames = [];
    private $writablePropertyNames = [];
    private $setMethods = [];
    private $getMethods = [];
    private $setTypes = [];
    private $getTypes = [];
    private $caseInsensitivePropertyMap = [];

    private $isTemplated = false;
    private $templates = [];
    private $templateOwnerCache = [];
    private $templateMethodArgumentsCache = [];
    private $templateMethodOwnersCache = [];

    public function __construct($clazz)
    {
        $this->type = new \ReflectionClass($clazz);
        
        $this->setTemplates();

        $classMethods = $this->type->getMethods();
        $this->addGetMethods($classMethods);
        $this->addSetMethods($classMethods);
        $this->addFields();
        $this->readablePropertyNames = array_keys($this->getMethods);
        $this->writablePropertyNames = array_keys($this->setMethods);
        foreach ($this->readablePropertyNames as $propName) {
            $this->caseInsensitivePropertyMap[strtoupper($propName)] = $propName;
        }             
    }

    private function addGetMethods(array $classMethods): void
    {
        $conflictingGetters = [];
        foreach ($classMethods as $method) {
            if (PropertyNamer::isGetter($method->name)) {
                $this->addMethodConflict($conflictingGetters, PropertyNamer::methodToProperty($method->name), $method);
            }
        }
        $this->resolveGetterConflicts($conflictingGetters);
    }

    private function addSetMethods(array $classMethods): void
    {
        $conflictingGetters = [];
        foreach ($classMethods as $method) {
            if (PropertyNamer::isSetter($method->name)) {
                $this->addMethodConflict($conflictingGetters, PropertyNamer::methodToProperty($method->name), $method);
            }
        }
        $this->resolveSetterConflicts($conflictingGetters);
    }

    private function addMethodConflict(array &$conflictingMethods, string $name, \ReflectionMethod $method): void
    {
        $list = &MapUtil::computeIfAbsent($conflictingMethods, $name, function () {
            return [];
        });
        $list[] = $method;
    }

    private function resolveGetterConflicts(array $conflictingGetters): void
    {
        foreach ($conflictingGetters as $propName => $value) {
            $winner = null;
            $isAmbiguous = false;
            foreach ($value as $candidate) {
                if ($winner === null) {
                    $winner = $candidate;
                    continue;
                }
                $winnerType = null;
                $candidateType = null;
                $winnerTypeRef = $winner->getReturnType();
                $candidateTypeRef = $candidate->getReturnType();
                if ($winnerTypeRef instanceof \ReflectionNamedType && $candidateTypeRef instanceof \ReflectionNamedType) {
                    $winnerType = $winnerTypeRef->getName();
                    $candidateType = $candidateTypeRef->getName();
                }

                if ($candidateType == $winnerType) {
                    if ($candidateType != 'boolean') {
                        $isAmbiguous = true;
                        break;
                    } elseif (strpos($candidate->getName(), "is") === 0) {
                        $winner = $candidate;
                    }
                } elseif (class_exists($winnerType) && class_exists($candidateType) && is_a($winnerType, $candidateType, true)) {
                    // OK getter type is descendant
                } elseif (class_exists($winnerType) && class_exists($candidateType) && is_a($candidateType, $winnerType, true)) {
                    $winner = $candidate;
                } else {
                    $isAmbiguous = true;
                    break;
                }
            }
            $this->addGetMethod($propName, $winner, $isAmbiguous);
        }
    }

    private function resolveSetterConflicts(array $conflictingSetters): void
    {
        foreach ($conflictingSetters as $key => $value) {
            $this->addSetMethod($key, $value[0]);
        }
    }

    private function addGetMethod(string $name, \ReflectionMethod $method, bool $isAmbiguous): void
    {
        $invoker = $isAmbiguous
            ? new AmbiguousMethodInvoker($method, "Illegal getter method $name")
            : new MethodInvoker($method);
        $this->getMethods[$name] = $invoker;

        $type = null;
        
        $refType = $method->getReturnType();
        if ($refType instanceof \ReflectionNamedType) {
            $type = $refType->getName();
        } elseif ($refType instanceof \ReflectionUnionType) {
            $type = array_map(function($cur) {
                return $cur->getName();
            }, $refType->getTypes());
        }
        
        $this->getTypes[$name] = $type;
    }

    private function addSetMethod(string $name, \ReflectionMethod $method): void
    {
        $invoker = new MethodInvoker($method);
        $this->setMethods[$name] = $invoker;

        $type = null;

        //from method argument
        $params = $method->getParameters();
        $refType = $params[0]->getType();
     
        if ($refType instanceof \ReflectionNamedType) {
            $type = $refType->getName();
        } elseif ($refType instanceof \ReflectionUnionType) {
            $type = array_map(function($cur) {
                return $cur->getName();
            }, $refType->getTypes());
        }

        //from class property type
        if ($type === null && $this->type->hasProperty($name)) {
            $prop = $this->type->getProperty($name);
            $refType = $prop->getType();
            if ($refType instanceof \ReflectionNamedType) {
                $type = $refType->getName();
            } elseif ($refType instanceof \ReflectionUnionType) {
                $type = array_map(function($cur) {
                    return $cur->getName();
                }, $refType->getTypes());
            }
        }

        //from template lazily on demand

        $this->setTypes[$name] = $type;
    }

    private function addFields(): void
    {
        $properties = $this->type->getProperties();
        $parent = $this->type->getParentClass();
        if ($parent) {
            $privateProps = $parent->getProperties(\ReflectionProperty::IS_PRIVATE);
            $properties = array_merge($properties, $privateProps);
        }
        foreach ($properties as $prop) {
            if (!$prop->isStatic()) {
                if (!array_key_exists($prop->name, $this->getMethods)) {
                    $this->addGetField($prop);
                }
                if (!array_key_exists($prop->name, $this->setMethods)) {
                    $this->addSetField($prop);
                }
            }
        }
    }

    private function addGetField(\ReflectionProperty $field): void
    {
        $this->getMethods[$field->name] = new GetFieldInvoker($field);        
        $this->getTypes[$field->name] = $this->getFieldType($field);
    }

    private function addSetField(\ReflectionProperty $field): void
    {
        $this->setMethods[$field->name] = new SetFieldInvoker($field);        
        $this->setTypes[$field->name] = $this->getFieldType($field);
    }

    private function getFieldType(\ReflectionProperty $field): ?string
    {
        $type = null;
        $refType = $field->getType();
        if ($refType instanceof \ReflectionNamedType) {
            $type = $refType->getName();
        } elseif ($refType instanceof \ReflectionUnionType) {
            $type = array_map(function($cur) {
                return $cur->getName();
            }, $refType->getTypes());
        }

        if ($type == null) {
            $attrs = $field->getAttributes(Prop::class);
            if (!empty($attrs)) {
                $args = $attrs[0]->getArguments();
                if (!empty($args)) {
                    $templateName = $args[0];
                    $target = $field->getDeclaringClass()->name;
                    $type = $this->getTemplateType($target, $templateName);
                }
            }
        }

        return $type;
    }

    public function getSetInvoker(string $propertyName)
    {
        if (array_key_exists($propertyName, $this->setMethods)) {
            return $this->setMethods[$propertyName];
        } else {
            throw new \ReflectionException("There is no setter for property named '" . $propertyName . "' in '" . $this->type . "'");
        }
    }

    public function getGetInvoker(string $propertyName)
    {
        if (array_key_exists($propertyName, $this->getMethods)) {
            return $this->getMethods[$propertyName];
        } else {
            throw new \ReflectionException("There is no getter for property named '" . $propertyName . "' in '" . $this->type . "'");
        }
    }

    public function getGetablePropertyNames(): array
    {
        return $this->readablePropertyNames;
    }

    public function getSetablePropertyNames(): array
    {
        return $this->writablePropertyNames;
    }

    public function hasSetter(string $propertyName): bool
    {
        return array_key_exists($propertyName, $this->setMethods);
    }

    public function hasGetter(string $propertyName): bool
    {
        return array_key_exists($propertyName, $this->getMethods);
    }

    public function findPropertyName(string $name): ?string
    {
        $key = strtoupper($name);
        if (array_key_exists($key, $this->caseInsensitivePropertyMap)) {
            return $this->caseInsensitivePropertyMap[$key];
        }
        return null;
    }

    public function getSetterType(string $propertyName)
    {
        if (array_key_exists($propertyName, $this->setTypes)) {
            $type = $this->setTypes[$propertyName];
            if (empty($type) && $this->isTemplated) {
                $invoker = $this->setMethods[$propertyName];
                //if method invoker
                if (method_exists($invoker, 'getOriginalMethod')) {
                    $method = $invoker->getOriginalMethod();
                    $this->setTemplateMethodCache($method, Params::class);
                    if (array_key_exists($method->name, $this->templateMethodArgumentsCache) && array_key_exists($propertyName, $this->templateMethodArgumentsCache[$method->name])) {
                        $target = $this->templateMethodOwnersCache[$method->name];
                        $templateName = $this->templateMethodArgumentsCache[$method->name][$propertyName];
                        return $this->getTemplateType($target, $templateName);
                    }
                }
            }
            return $type;
        } else {
            throw new \ReflectionException("There is no setter for property named '" . $propertyName . "' in '" . $this->type . "'");
        }
    }

    public function getGetterType(string $propertyName)
    {
        if (array_key_exists($propertyName, $this->getTypes)) {
            $type = $this->getTypes[$propertyName];
            if (empty($type) && $this->isTemplated) {
                $invoker = $this->getMethods[$propertyName];
                //if method invoker
                if (method_exists($invoker, 'getOriginalMethod')) {
                    $method = $invoker->getOriginalMethod();
                    $this->setTemplateMethodCache($method, Returns::class);
                    if (array_key_exists($method->name, $this->templateMethodArgumentsCache) && !empty($this->templateMethodArgumentsCache[$method->name])) {
                        $target = $this->templateMethodOwnersCache[$method->name];
                        $templateName = $this->templateMethodArgumentsCache[$method->name][0];
                        return $this->getTemplateType($target, $templateName);
                    }
                }
            }
            return $type;
        } else {
            throw new \ReflectionException("There is no getter for property named '" . $propertyName . "' in '" . $this->type . "'");
        }
    }

    private function setTemplateMethodCache(\ReflectionMethod $method, string $attributeType): void
    {
        if (!array_key_exists($method->name, $this->templateMethodArgumentsCache)) {
            foreach (array_keys($this->templates) as $templateOwner) {
                if (!array_key_exists($templateOwner, $this->templateOwnerCache)) {
                    $ownerImpl = new \ReflectionClass($templateOwner);
                    $this->templateOwnerCache[$templateOwner] = $ownerImpl;                        
                } else {
                    $ownerImpl = $this->templateOwnerCache[$templateOwner];
                }
                if ($ownerImpl->hasMethod($method->name)) {
                    $method = $ownerImpl->getMethod($method->name);
                    $attrs = $method->getAttributes($attributeType);
                    if (!empty($attrs)) {
                        $args = $attrs[0]->getArguments();
                        $this->templateMethodArgumentsCache[$method->name] = $args;
                        $this->templateMethodOwnersCache[$method->name] = $templateOwner;
                    }
                    break;
                }
            }
        }
    }

    private function setTemplates(): void
    {
        $classAttributes = $this->type->getAttributes(Impl::class);
        if (empty($this->type->getAttributes())) {
            $parentType = $this->type->getParentClass();
            if ($parentType) {
                $classAttributes = $parentType->getAttributes(Impl::class);
            }
        }
        if (!empty($classAttributes)) {
            $arguments = $classAttributes[0]->getArguments();
            if (!empty($arguments[0])) {
                $this->isTemplated = true;
                $this->templates = $arguments[0];
            }
        }
    }

    private function getTemplateType(string $target, string $templateName): ?string
    {
        if (array_key_exists($target, $this->templates) && array_key_exists($templateName, $this->templates[$target])) {
            return $this->templates[$target][$templateName];
        }
        return null;
    } 

    public function isTemplated(): bool
    {
        return $this->isTemplated;
    }
}
