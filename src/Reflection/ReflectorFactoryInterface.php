<?php

namespace Util\Reflection;

interface ReflectorFactoryInterface
{
    public function isClassCacheEnabled(): bool;
  
    public function setClassCacheEnabled(bool $classCacheEnabled): void;
  
    public function findForClass(string $type): MetaReflector;
}
