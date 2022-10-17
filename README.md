[![Latest Stable Version](https://poser.pugx.org/bingo-soft/util/v/stable.png)](https://packagist.org/packages/bingo-soft/util)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.4-8892BF.svg)](https://php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/bingo-soft/util/badges/quality-score.png?b=main)](https://scrutinizer-ci.com/g/bingo-soft/util/?branch=main)

# util

Utility classes for PHP


# Installation

Install library, using Composer:

```
composer require bingo-soft/util
```

# Proxy class

```php
/* Original class */

interface BusinessServiceInterface
{
}

class BusinessServiceImpl
{
    public function doSomething(int $id, string $name)
    {
        return "$id - $name";
    }
}

/* Handler */

use Util\Proxy\MethodHandlerInterface;

class DoSomethingMethodHandler implements MethodHandlerInterface
{
    public function invoke($proxy, \ReflectionMethod $thisMethod, \ReflectionMethod $proceed, array $args)
    {
        return 'prepend - ' . $proceed->invoke($proxy, ...$args);
    }
}

/* Custom proxy factory*/

use Util\Proxy\{
    MethodHandlerInterface,
    ProxyFactory
};

class MyProxyFactory
{
    public static function createProxy(string $type, MethodHandlerInterface $method, array $args = [])
    {
        $enhancer = new ProxyFactory();
        $enhancer->setSuperclass($type);
        $enhancer->setInterfaces([ BusinessServiceInterface::class ]);
        return $enhancer->create($args);
    }
}

/* Creation and test of proxy class*/

$method = new DoSomethingMethodHandler();
$proxy = MyProxyFactory::createProxy(BusinessServiceImpl::class, $method);
$proxy->setHandler($method);
echo $proxy->doSomething(1, "curitis"); // will print "prepend - 1 - curitis"

```

# Enhanced reflection with meta objects

```php

/* Set nested property */

use Tests\Domain\Misc\RichType;
use Util\Reflection\{
    MetaClass,
    MetaObject,
    SystemMetaObject
};

$rich = new RichType();
$meta = SystemMetaObject::forObject($rich);
$meta->setValue("richType.richField", "foo");

echo $meta->getValue("richType.richField"); // new RichType( richType => new RichType ( richField => "foo" )) 

/* Create meta object from array */

$map = [];
$metaMap = SystemMetaObject::forObject($map);
$metaMap->setValue("id", "100");
$metaMap->setValue("name.first", "Clinton");
print_r($map); // [ "id" => 100, "name" => [ "first" => "Clinton" ]]

```

# Running tests

```
./vendor/bin/phpunit ./tests
```
