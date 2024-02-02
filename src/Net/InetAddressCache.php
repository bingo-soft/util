<?php

namespace Util\Net;

class InetAddressCache
{
    private $cache;
    private InetAddressCacheType $type;

    public function __construct(InetAddressCacheType $type)
    {
        $this->type = $type;
        $this->cache = [];
    }

    private function getPolicy(): int
    {
        if ($this->type == InetAddressCacheType::POSITIVE) {
            return (new InetAddressCachePolicy())->get();
        } else {
            return (new InetAddressCachePolicy())->getNegative();
        }
    }

    public function put(string $host, array &$addresses): InetAddressCache
    {
        $policy = $this->getPolicy();
        if ($policy == InetAddressCachePolicy::NEVER) {
            return $this;
        }
        // purge any expired entries
        if ($policy != InetAddressCachePolicy::FOREVER) {
            // As we iterate in insertion order we can
            // terminate when a non-expired entry is found.
            $expired = [];
            $now = floor(microtime(true) * 1000);
            foreach ($this->cache as $key => $entry) {
                if ($entry->expiration >= 0 && $entry->expiration < $now) {
                    $expired[] = $key;
                } else {
                    break;
                }
            }
            foreach ($expired as $key) {
                unset($this->cache[$key]);
            }
        }
        // create new entry and add it to the cache
        // -- as a HashMap replaces existing entries we
        //    don't need to explicitly check if there is
        //    already an entry for this host.
        $expiration = 0;
        if ($policy == InetAddressCachePolicy::FOREVER) {
            $expiration = -1;
        } else {
            $expiration = floor(microtime(true) * 1000) + $policy * 1000;
        }
        $entry = new InetAddressCacheEntry($addresses, $expiration);
        $this->cache[$host] = $entry;
        return $this;
    }
    
    public function get(string $host): ?InetAddressCacheEntry
    {
        $policy = $this->getPolicy();
        if ($policy == InetAddressCachePolicy::NEVER) {
            return null;
        }
        $entry = null;
        if (array_key_exists($host, $this->cache)) {
            $entry = $this->cache[$host];
        }
        // check if entry has expired
        if ($entry != null && $policy != InetAddressCachePolicy::FOREVER) {
            if (
                $entry->expiration >= 0 &&
                $entry->expiration < floor(microtime(true) * 1000)
            ) {
                unset($this->cache[$host]);
                $entry = null;
            }
        }
        return $entry;
    }
}
