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
interface InterInterface
{
    public function originalMethod(string $s, $d = null, int $z = 1);
}

interface SecondInterface
{
}

class Original implements InterInterface
{
    public function originalMethod(string $s, $d = null, int $z = 1)
    {
        return $s;
    }
}
//////////////////////////////////////////
use Util\Proxy\InvocationHandlerInterface;

class Handler implements InvocationHandlerInterface
{
    private $original;

    public function __construct($original)
    {
        $this->original = $original;
    }

    public function invoke($proxy, \ReflectionMethod $method, array $args)
    {
        return ["Before" , $method->invoke($this->original, ...$args), "After"];
    }
}

$original = new Original();
$handler = new Handler($original);
//Create proxy class, implementing two interfaces
$proxy = Proxy::newProxyInstance([ InterInterface::class, SecondInterface::class ], $handler);
//$proxy instanceof InterInterface -> true
$res = $proxy->originalMethod('Hello');
var_dump($res); // prints array ["Before", "Hello", "After"]
```

# Running tests

```
./vendor/bin/phpunit ./tests
```
