<?php

namespace Util\Net;

use Util\Net\NameService\NameServiceInterface;
use Util\Net\Util\IPAddressUtil;

class InetAddress
{
    protected const IPv4 = 1;
    protected const IPv6 = 2;
    protected const PREFER_IPV6_ADDRESS = 'networkaddress.preferIPv6Addresses'; 
    protected InetAddressHolder $holder;

    protected static $nameServices = [];
    protected string $canonicalHostName = "";
    protected static $staticSet = false;
    protected static $preferIPv6Address = false;
    
    protected static $addressCache;// = new Cache(Cache.Type.Positive);
    protected static $negativeCache;// = new Cache(Cache.Type.Negative);
    protected static $addressCacheInit = false;
    protected static $unknownArray = []; // put THIS in cache
    protected static $impl;
    protected static $lookupTable = [];

    public function __construct(?string $resourcePath = 'src/Resources/php.security')
    {
        $this->holder = new InetAddressHolder();
        self::init($resourcePath);      
    }

    private static function init(?string $resourcePath = 'src/Resources/php.security'): void
    {
        if (!self::$staticSet) {
            self::$staticSet = true;
            $props = [];
            if (file_exists($resourcePath)) {                
                $fp = fopen($resourcePath, "r");       
                while (($line = fgets($fp, 4096)) !== false) {
                    $tokens = explode("=", $line);
                    if (count($tokens) == 2) {
                        $props[$tokens[0]] = trim($tokens[1]);
                    }
                }
                fclose($fp);

                if (array_key_exists(self::PREFER_IPV6_ADDRESS, $props)) {
                    self::$preferIPv6Address = boolval($props[self::PREFER_IPV6_ADDRESS]);
                }
            }

            self::$addressCache = new InetAddressCache(InetAddressCacheType::POSITIVE);
            self::$negativeCache = new InetAddressCache(InetAddressCacheType::NEGATIVE);

            self::$impl = InetAddressImplFactory::create();
            // get name service if provided and requested
            $propPrefix = "nameservice.provider.";
            $n = 1;
            $provider = null;
            if (array_key_exists($propPrefix . $n, $props)) {
                $provider = $props[$propPrefix . $n];
            }
            while ($provider != null) {
                $ns = self::createNSProvider($provider);
                if ($ns != null) {
                    self::$nameServices[] = $ns;
                }
                $n += 1;
                $provider = null;
                if (array_key_exists($propPrefix . $n, $props)) {
                    $provider = $props[$propPrefix . $n];
                }
            }

            // if not designate any name services provider,
            // create a default one
            if (count(self::$nameServices) == 0) {
                $ns = self::createNSProvider("default");
                self::$nameServices[] = $ns;
            }

            $preferIPv6Address = null;
            if (array_key_exists('networkaddress.preferIPv6Addresses', $props)) {
                $preferIPv6Address = $props['networkaddress.preferIPv6Addresses'];                
            }  elseif (array_key_exists('net.preferIPv6Addresses', $props)) {
                $preferIPv6Address = $props['net.preferIPv6Addresses'];
            }
            if ($preferIPv6Address !== null) {
                if (is_numeric($preferIPv6Address)) {
                    self::$preferIPv6Address = boolval($preferIPv6Address );
                } else {
                    self::$preferIPv6Address = strtolower($preferIPv6Address ) == 'true';
                }
            }
        }
    }

    public function holder(): InetAddressHolder
    {
        return $this->holder;
    }

    private function readResolve()
    {
        // will replace the deserialized 'this' object
        return new Inet4Address($this->holder()->getHostName(), $this->holder()->getAddress());
    }

    public function isMulticastAddress(): bool
    {
        return false;
    }

    public function isAnyLocalAddress(): bool
    {
        return false;
    }

    public function isLoopbackAddress(): bool
    {
        return false;
    }

    public function isLinkLocalAddress(): bool
    {
        return false;
    }

    public function isSiteLocalAddress(): bool
    {
        return false;
    }

    public function isMCGlobal(): bool
    {
        return false;
    }

    public function isMCNodeLocal(): bool
    {
        return false;
    }

    public function isMCLinkLocal(): bool
    {
        return false;
    }

    public function isMCSiteLocal(): bool
    {
        return false;
    }

    public function isMCOrgLocal(): bool
    {
        return false;
    }

    /*public boolean isReachable(int timeout) throws IOException {
        return isReachable(null, 0 , timeout);
    }

    public boolean isReachable(NetworkInterface netif, int ttl,
                               int timeout) throws IOException {
        if (ttl < 0)
            throw new IllegalArgumentException("ttl can't be negative");
        if (timeout < 0)
            throw new IllegalArgumentException("timeout can't be negative");
        return self::$impl->isReachable(this, timeout, netif, ttl);
    }*/

    public function getHostName(bool $check = true): ?string
    {
        if ($this->holder()->getHostName() == null) {
            $this->holder()->hostName = InetAddress::getHostFromNameService($this, $check);
        }
        return $this->holder()->getHostName();
    }

    public function getCanonicalHostName(): ?string
    {
        if ($this->canonicalHostName == null) {
            $this->canonicalHostName =
                InetAddress::getHostFromNameService($this, true);
        }
        return $this->canonicalHostName;
    }
    
    private static function getHostFromNameService(InetAddress $addr, bool $check): ?string
    {
        $host = null;
        foreach (self::$nameServices as $nameService) {
            try {
                // first lookup the hostname
                $host = $nameService->getHostByAddr($addr->getAddress());
                $arr = InetAddress::getAllByName0($host, $check);
                $ok = false;
                if(!empty($arr)) {
                    for ($i = 0; !$ok && $i < count($arr); $i += 1) {
                        $ok = $addr->equals($arr[$i]);
                    }
                }
                //XXX: if it looks a spoof just return the address?
                if (!$ok) {
                    $host = $addr->getHostAddress();
                    return $host;
                }
                break;
            } /*catch (SecurityException e) {
                host = addr.getHostAddress();
                break;
            }*/catch (\Throwable $e) {
                $host = $addr->getHostAddress();
                // let next provider resolve the hostname
            }
        }
        return $host;
    }

    public function getAddress()
    {
        return null;
    }

    public function getHostAddress(): ?string
    {
        return null;
    }

    public function equals($obj): bool
    {
        return false;
    }

    public function __toString(): string
    {
        $hostName = $this->holder()->getHostName();
        return (($hostName != null) ? $hostName : "")
            . "/" . $this->getHostAddress();
    }

    private static function cacheInitIfNeeded(): void
    {
        if (self::$addressCacheInit) {
            return;
        }
        self::$unknownArray = [];
        $unknownArray[0] = self::$impl->anyLocalAddress();
        self::$addressCache->put(
            self::$impl->anyLocalAddress()->getHostName(),
            self::$unknownArray
        );
        self::$addressCacheInit = true;
    }

    private static function cacheAddresses(
        string $hostname,
        ?array &$addresses,
        bool $success
    ): void {
        if ($addresses !== null) {
            $hostname = strtolower($hostname);
            self::cacheInitIfNeeded();
            if ($success) {
                self::$addressCache->put($hostname, $addresses);
            } else {
                self::$negativeCache->put($hostname, $addresses);
            }
        }
    }

    private static function getCachedAddresses(string $hostname): array
    {
        $hostname = strtolower($hostname);
        // search both positive & negative caches
        self::cacheInitIfNeeded();
        $entry = self::$addressCache->get($hostname);
        if ($entry == null) {
            $entry = self::$negativeCache->get($hostname);
        }
        if ($entry != null) {
            return $entry->addresses;
        }
        // not found
        return [];
    }

    private static function createNSProvider(?string $provider = null): ?NameServiceInterface
    {
        if ($provider == null) {
            return null;
        }

        $nameService = null;
        if ($provider == "default") {
            // initialize the default name service
            $impl = self::$impl;
            $nameService = new class($impl) implements NameServiceInterface {
                private $impl;

                public function __construct($impl)
                {
                    $this->impl = $impl;
                }

                public function lookupAllHostAddr(string $host): ?array
                {
                    return $this->impl->lookupAllHostAddr($host);
                }
                
                public function getHostByAddr(array | string $addr): ?string
                {
                    return $this->impl->getHostByAddr($addr);
                }
            };
        } else {
            if (class_exists($provider)) {
                $impl = new $provider();
                $nameService = $impl->createNameService();
            }
        }
        return $nameService;
    }

    /**
     * Creates an InetAddress based on the provided host name and IP address.
     * No name service is checked for the validity of the address.
     *
     * <p> The host name can either be a machine name, such as
     * "{@code java.sun.com}", or a textual representation of its IP
     * address.
     * <p> No validity checking is done on the host name either.
     *
     * <p> If addr specifies an IPv4 address an instance of Inet4Address
     * will be returned; otherwise, an instance of Inet6Address
     * will be returned.
     *
     * <p> IPv4 address byte array must be 4 bytes long and IPv6 byte array
     * must be 16 bytes long
     *
     * @param host the specified host
     * @param addr the raw IP address in network byte order
     * @return  an InetAddress object created from the raw IP address.
     * @exception  UnknownHostException  if IP address is of illegal length
     * @since 1.4
     */
    public static function getByAddress(...$args): InetAddress
    {
        self::init();
        //String host, byte[] addr
        if (count($args) == 1) {
            $host = null;
        } else {
            $host = $args[0];
            $addr = $args[1];
        }
        if (is_string($addr)) {
            $addr = IPAddressUtil::textToNumericFormatV4($addr);
        }
        if ($host != null && strlen($host) > 0 && $host[0] == '[') {
            if ($host[strlen($host) - 1] == ']') {
                $host = substr($host, 1, strlen($host) - 2);
            }
        }
        if (!empty($addr)) {
            if (count($addr) == Inet4Address::INADDRSZ) {
                return new Inet4Address($host, $addr);
            } elseif (count($addr) == Inet6Address::INADDRSZ) {
                $newAddr  = IPAddressUtil::convertFromIPv4MappedAddress($addr);
                if ($newAddr != null) {
                    return new Inet4Address($host, $newAddr);
                } else {
                    return new Inet6Address($host, $addr);
                }
            }
        }
        throw new \Exception("addr is of illegal length");
    }

    // called from deployment cache manager
    public static function getByName(?string $host = null, InetAddress $reqAddr = null): ?InetAddress
    {
        self::init();
        return InetAddress::getAllByName($host, $reqAddr)[0];
    }

    /**
     * Given the name of a host, returns an array of its IP addresses,
     * based on the configured name service on the system.
     *
     * <p> The host name can either be a machine name, such as
     * "{@code java.sun.com}", or a textual representation of its IP
     * address. If a literal IP address is supplied, only the
     * validity of the address format is checked.
     *
     * <p> For {@code host} specified in <i>literal IPv6 address</i>,
     * either the form defined in RFC 2732 or the literal IPv6 address
     * format defined in RFC 2373 is accepted. A literal IPv6 address may
     * also be qualified by appending a scoped zone identifier or scope_id.
     * The syntax and usage of scope_ids is described
     * <a href="Inet6Address.html#scoped">here</a>.
     * <p> If the host is {@code null} then an {@code InetAddress}
     * representing an address of the loopback interface is returned.
     * See <a href="http://www.ietf.org/rfc/rfc3330.txt">RFC&nbsp;3330</a>
     * section&nbsp;2 and <a href="http://www.ietf.org/rfc/rfc2373.txt">RFC&nbsp;2373</a>
     * section&nbsp;2.5.3. </p>
     *
     * <p> If there is a security manager and {@code host} is not
     * null and {@code host.length() } is not equal to zero, the
     * security manager's
     * {@code checkConnect} method is called
     * with the hostname and {@code -1}
     * as its arguments to see if the operation is allowed.
     *
     * @param      host   the name of the host, or {@code null}.
     * @return     an array of all the IP addresses for a given host name.
     *
     * @exception  UnknownHostException  if no IP address for the
     *               {@code host} could be found, or if a scope_id was specified
     *               for a global IPv6 address.
     * @exception  SecurityException  if a security manager exists and its
     *               {@code checkConnect} method doesn't allow the operation.
     *
     * @see SecurityManager#checkConnect
     */
    public static function getAllByName(?string $host = null, InetAddress $reqAddr = null): array
    {
        self::init();

        if ($host == null || strlen($host) == 0) {
            return [ self::$impl->loopbackAddress() ];
        }

        $ipv6Expected = false;
        if ($host[0] == '[') {
            // This is supposed to be an IPv6 literal
            if (strlen($host) > 2 && $host[strlen($host) - 1] == ']') {
                $host = substr($host, 1, strlen($host) - 2);
                $ipv6Expected = true;
            } else {
                // This was supposed to be a IPv6 address, but it's not!
                throw new \Exception($host . ": invalid IPv6 address");
            }
        }

        // if host is an IP address, we won't do further lookup
        $val = @hexdec($host[0]);
        if (!(strtolower($host[0]) != '0' && $val == 0) || ($host[0] == ':')) {
            $numericZone = -1;
            $ifname = null;
            // see if it is IPv4 address
            $addr = IPAddressUtil::textToNumericFormatV4($host);
            if ($addr == null) {
                // This is supposed to be an IPv6 literal
                // Check if a numeric or string zone id is present
                $pos = 0;
                if (($pos = strpos($host, "%")) !== false) {
                    $numericZone = self::checkNumericZone($host);
                    if ($numericZone == -1) { /* remainder of string must be an ifname */
                        $ifname = substr($host, $pos + 1);
                    }
                }
                if (($addr = IPAddressUtil::textToNumericFormatV6($host)) == null && strpos($host,":") !== false) {
                    throw new \Exception($host . ": invalid IPv6 address");
                }
            } elseif ($ipv6Expected) {
                // Means an IPv4 litteral between brackets!
                throw new \Exception("Unknown host [" . $host . "]");
            }
            $ret = [];
            if (!empty($addr)) {
                if (count($addr) == Inet4Address::INADDRSZ) {
                    $ret[0] = new Inet4Address(null, $addr);
                } else {
                    if ($ifname != null) {
                        $ret[0] = new Inet6Address(null, $addr, $ifname);
                    } else {
                        $ret[0] = new Inet6Address(null, $addr, $numericZone);
                    }
                }
                return $ret;
            }
        } elseif ($ipv6Expected) {
            // We were expecting an IPv6 Litteral, but got something else
            throw new \Exception("Unknown host [" . $host . "]");
        }
        return self::getAllByName0($host, $reqAddr, true);
    }

    /**
     * Returns the loopback address.
     * <p>
     * The InetAddress returned will represent the IPv4
     * loopback address, 127.0.0.1, or the IPv6 loopback
     * address, ::1. The IPv4 loopback address returned
     * is only one of many in the form 127.*.*.*
     *
     * @return  the InetAddress loopback instance.
     * @since 1.7
     */
    public static function getLoopbackAddress(): InetAddress
    {
        self::init();
        return self::$impl->loopbackAddress();
    }

    /**
     * check if the literal address string has %nn appended
     * returns -1 if not, or the numeric value otherwise.
     *
     * %nn may also be a string that represents the displayName of
     * a currently available NetworkInterface.
     */
    private static function checkNumericZone(string $s): int
    {
        $percent = strpos($s, '%');
        $slen = strlen($s);
        $digit = 0;
        $zone = 0;
        if ($percent === false) {
            return -1;
        }
        for ($i = $percent + 1; $i < $slen; $i += 1) {
            $c = $s[$i];
            if ($c == ']') {
                if ($i == $percent + 1) {
                    /* empty per-cent field */
                    return -1;
                }
                break;
            }
            if (!ctype_digit($c)) {
                return -1;
            }
            $zone = ($zone * 10) + $digit;
        }
        return $zone;
    }

    private static function getAllByName0(...$args): array
    {
        $host = $args[0];
        $reqAddr = null;
        if (count($args) == 1) {
            $check = true;
        } elseif (count($args) == 2) {
            $check = $args[1];
        } else {
            $reqAddr = $args[1];
            $check = $args[2];
        }

        $addresses = self::getCachedAddresses($host);

        /* If no entry in cache, then do the host lookup */
        if (empty($addresses)) {
            $addresses = self::getAddressesFromNameService($host, $reqAddr);
        }

        if ($addresses === null) {
            throw new \Exception("Unknown host $host");
        }

        return $addresses;
    }

    private static function getAddressesFromNameService(string $host, ?InetAddress $reqAddr): ?array
    {
        $addresses = [];
        $success = false;
        $ex = null;
        // Check whether the host is in the lookupTable.
        // 1) If the host isn't in the lookupTable when
        //    checkLookupTable() is called, checkLookupTable()
        //    would add the host in the lookupTable and
        //    return null. So we will do the lookup.
        // 2) If the host is in the lookupTable when
        //    checkLookupTable() is called, the current thread
        //    would be blocked until the host is removed
        //    from the lookupTable. Then this thread
        //    should try to look up the addressCache.
        //     i) if it found the addresses in the
        //        addressCache, checkLookupTable()  would
        //        return the addresses.
        //     ii) if it didn't find the addresses in the
        //         addressCache for any reason,
        //         it should add the host in the
        //         lookupTable and return null so the
        //         following code would do  a lookup itself.
        if (empty(($addresses = self::checkLookupTable($host)))) {
            try {
                // This is the first thread which looks up the addresses
                // this host or the cache entry for this host has been
                // expired so this thread should do the lookup.
                foreach (self::$nameServices as $nameService) {
                    try {
                        /*
                         * Do not put the call to lookup() inside the
                         * constructor.  if you do you will still be
                         * allocating space when the lookup fails.
                         */

                        $addresses = $nameService->lookupAllHostAddr($host);
                        $success = true;
                        break;
                    } catch (\Throwable $uhe) {
                        if (strtolower($host) == "localhost") {
                            $local = [ self::$impl->loopbackAddress() ];
                            $addresses = $local;
                            $success = true;
                            break;
                        } else {
                            $addresses = [];
                            $success = false;
                            $ex = $uhe;
                        }
                    }
                }

                // More to do?
                if ($reqAddr != null && count($addresses) > 1 && $addresses[0] != $reqAddr) {
                    // Find it?
                    $i = 1;
                    for (; $i < count($addresses); $i += 1) {
                        if ($addresses[$i] == $reqAddr) {
                            break;
                        }
                    }
                    // Rotate
                    if ($i < count($addresses)) {
                        $tmp = null;
                        $tmp2 = $reqAddr;
                        for ($j = 0; $j < $i; $j += 1) {
                            $tmp = $addresses[$j];
                            $addresses[$j] = $tmp2;
                            $tmp2 = $tmp;
                        }
                        $addresses[$i] = $tmp2;
                    }
                }
                // Cache the address.
                self::cacheAddresses($host, $addresses, $success);

                if (!$success && $ex != null) {
                    throw $ex;
                }
            } finally {
                // Delete host from the lookupTable and notify
                // all threads waiting on the lookupTable monitor.
                self::updateLookupTable($host);
            }
        }

        return $addresses;
    }


    private static function checkLookupTable(string $host): array
    {
        $addresses = self::getCachedAddresses($host);
        if (empty($addresses)) {
            self::$lookupTable[$host] = null;
            return [];
        }

        return $addresses;
    }

    private static function updateLookupTable(string $host): void
    {
        unset(self::$lookupTable[$host]);
    }

    private static $cachedLocalHost = null;
    private static $cacheTime = 0;
    private static $maxCacheTime = 5;
    private static $cacheLock;

    /**
     * Returns the address of the local host. This is achieved by retrieving
     * the name of the host from the system, then resolving that name into
     * an {@code InetAddress}.
     *
     * <P>Note: The resolved address may be cached for a short period of time.
     * </P>
     *
     * <p>If there is a security manager, its
     * {@code checkConnect} method is called
     * with the local host name and {@code -1}
     * as its arguments to see if the operation is allowed.
     * If the operation is not allowed, an InetAddress representing
     * the loopback address is returned.
     *
     * @return     the address of the local host.
     *
     * @exception  UnknownHostException  if the local host name could not
     *             be resolved into an address.
     *
     * @see SecurityManager#checkConnect
     * @see java.net.InetAddress#getByName(java.lang.String)
     */
    public static function getLocalHost(): InetAddress
    {
        self::init();
        try {
            $local = self::$impl->getLocalHostName();

            if ($local == "localhost") {
                return self::$impl->loopbackAddress();
            }

            $ret = null;
            $now = time();
            if (self::$cachedLocalHost != null) {
                if (($now - $cacheTime) < self::$maxCacheTime) {// Less than 5s old?
                    $ret = self::$cachedLocalHost;
                } else {
                    self::$cachedLocalHost = null;
                }
            }

            // we are calling getAddressesFromNameService directly
            // to avoid getting localHost from cache
            if ($ret == null) {
                $localAddrs = [];
                try {
                    $localAddrs =
                        InetAddress::getAddressesFromNameService($local, null);
                } catch (\Throwable $uhe) {
                    // Rethrow with a more informative error message.
                    throw new \Exception($local . ": " . $uhe->getMessage());
                    throw $uhe2;
                }
                self::$cachedLocalHost = $localAddrs[0];
                self::$cacheTime = $now;
                $ret = $localAddrs[0];
            }
            return $ret;
        } catch (\Throwable $e) {
            return self::$impl->loopbackAddress();
        }
    }

    /*
     * Returns the InetAddress representing anyLocalAddress
     * (typically 0.0.0.0 or ::0)
     */
    public static function anyLocalAddress(): InetAddress
    {
        self::init();
        return self::$impl->anyLocalAddress();
    }

    /*
     * Load and instantiate an underlying impl class
     */
    public static function loadImpl(string $implName): InetAddressImplInterface
    {
        self::init();
        if (class_exists($implName)) {
            return new $implName();
        }
        $className = 'Util\Net\\' . $implName;
        if (class_exists($className)) {
            return new $className();
        }
        return null;        
    }
}
