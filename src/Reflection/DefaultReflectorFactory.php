<?php

namespace Util\Reflection;

class DefaultReflectorFactory implements ReflectorFactoryInterface
{
    private $classCacheEnabled = true;
    private $reflectorMap = [];
  
    public function __construct() {
    }
  
    public function isClassCacheEnabled(): bool
    {
        return $this->classCacheEnabled;
    }
  
    public function setClassCacheEnabled(bool $classCacheEnabled): void
    {
        $this->classCacheEnabled = $classCacheEnabled;
    }
  
    public function findForClass(string $type): MetaReflector
    {
        if ($this->classCacheEnabled) {
            return MapUtil::computeIfAbsent($this->reflectorMap, $type, function () use ($type) {
                return new MetaReflector($type);
            });
        } else {
            return new MetaReflector($type);
        }
    }  
}
