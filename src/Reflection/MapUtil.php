<?php

namespace Util\Reflection;

class MapUtil
{
    public static function &computeIfAbsent(mixed &$map, $key, $mappingFunction)
    {
        if ((is_array($map) && array_key_exists($key, $map)) || ($map instanceof \ArrayObject && array_key_exists($key, $map->getArrayCopy())) && $map[$key] !== null) {
            return $map[$key];
        }
        $map[$key] = $mappingFunction();
        return $map[$key];
    }
}
