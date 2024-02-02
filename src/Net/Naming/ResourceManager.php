<?php

namespace Util\Net\Naming;

class ResourceManager
{
    /*
     * Name of provider resource files (without the package-name prefix.)
     */
    private const PROVIDER_RESOURCE_FILE_NAME = "src/Resources/ndiprovider.properties";

    /*
     * Name of application resource files.
     */
    private const APP_RESOURCE_FILE_NAME = "src/Resources/ndi.properties";

    /*
     * The standard NDI properties that specify colon-separated lists.
     */
    private static $listProperties = [
        ContextInterface::OBJECT_FACTORIES,
        ContextInterface::URL_PKG_PREFIXES,
        ContextInterface::STATE_FACTORIES
    ];

    private static $propertiesCache = [];

    private static $factoryCache = [];

    private function __construct()
    {
    }

    public static function getInitialEnvironment(array $env, ?string $appResourceFileName = null): array
    {
        $sys = getenv();
        $app = $this->readPropertiesFromFile($appResourceFileName ?? self::APP_RESOURCE_FILE_NAME);
        return array_merge($sys, $app, $env);
    }

    private function readPropertiesFromFile(string $path): array
    {
        $props = [];
        if (file_exists($path)) {                
            $fp = fopen($path, "r");       
            while (($line = fgets($fp, 4096)) !== false) {
                $tokens = explode("=", $line);
                if (count($tokens) == 2) {
                    $props[$tokens[0]] = trim($tokens[1]);
                }
            }
            fclose($fp);
        }
        return $props;
    }

    private static function getProviderResource($obj = null, ?string $providerResourceFileName = null): array
    {
        if ($obj == null) {
            return [];
        }
        $c = get_class($obj);

        if (array_key_exists($c, self::$propertiesCache)) {
            return self::$propertiesCache[$c];
        }

        $props = $this->readPropertiesFromFile($providerResourceFileName ?? self::PROVIDER_RESOURCE_FILE_NAME);
        self::$propertiesCache[$c] = $props;
        return $props;
    }

    public static function getProperty(string $propName, array $env, ?ContextInterface $ctx, ?bool $concat = false): ?string
    {
        $val1 = null;
        if (array_key_exists($propName, $env)) {
            $val1 = $env[$propName];
        }

        if ($ctx == null || (($val1 !== null) && !$concat)) {
            return $val1;
        }

        $res = self::getProviderResource($ctx);
        if (array_key_exists($propName, $res) && $val1 == null) {
            return $res[$propName];
        } elseif (($val2 == null) || !$concat) {
            return $val1;
        } else {
            return $val1 . ":" . $val2;
        }
    }

    public static function getFactories(string $propName, array $env, ContextInterface $ctx, ?string $providerResourceFileName = null): array
    {
        $facProp = self::getProperty($propName, $env, $ctx, true);
        if ($facProp == null) {
            return [];
        }
        $factories = [];
        if (!empty(self::$factoryCache) && array_key_exists($facProp, self::$factoryCache)) {
            return self::$factoryCache[$facProp];
        }
    
        $props = $this->readPropertiesFromFile($providerResourceFileName ?? self::PROVIDER_RESOURCE_FILE_NAME);
        if (array_key_exists('naming.factory.object', $props)) {
            $parser = explode(':', $props['naming.factory.object']);
            $factories = [];
            foreach ($parser as $clazz) {
                $factories[] = new $clazz(); 
            }
            self::$factoryCache[$facProp] = $factories;
            return $factories;
        }

        return [];
    }
}
