<?php

namespace Util\Net;

use Util\Net\Url\Uri;

class DefaultProxySelector extends ProxySelector
{
     /**
     * This is where we define all the valid System Properties we have to
     * support for each given protocol.
     * The format of this 2 dimensional array is :
     * - 1 row per protocol (http, ftp, ...)
     * - 1st element of each row is the protocol name
     * - subsequent elements are prefixes for Host & Port properties
     *   listed in order of priority.
     * Example:
     * {"ftp", "ftp.proxy", "ftpProxy", "proxy", "socksProxy"},
     * means for FTP we try in that oder:
     *          + ftp.proxyHost & ftp.proxyPort
     *          + ftpProxyHost & ftpProxyPort
     *          + proxyHost & proxyPort
     *          + socksProxyHost & socksProxyPort
     *
     * Note that the socksProxy should *always* be the last on the list
     */
    private static $props = [
        /*
         * protocol, Property prefix 1, Property prefix 2, ...
         */
        ["http", "http.proxy", "proxy", "socksProxy"],
        ["https", "https.proxy", "proxy", "socksProxy"],
        ["ftp", "ftp.proxy", "ftpProxy", "proxy", "socksProxy"],
        ["gopher", "gopherProxy", "socksProxy"],
        ["socket", "socksProxy"]
    ];

    private const SOCKS_PROXY_VERSION = "socksProxyVersion";

    private static $hasSystemProxies = false;

    private $proto;
    private $nprop;
    private $urlhost;

    /**
     * select() method. Where all the hard work is done.
     * Build a list of proxies depending on URI.
     * Since we're only providing compatibility with the system properties
     * from previous releases (see list above), that list will always
     * contain 1 single proxy, default being NO_PROXY.
     */
    public function select(URI $uri, ?array $defProps = []): array
    {
        $protocol = $uri->getScheme();
        $host = $uri->getHost();

        if ($protocol === null || $host === null) {
            throw new \Exception("Illegal arguments: protocol = " . $protocol . " host = " . $host);
        }
        $proxyl = [];

        $pinfo = null;

        if ("http" == strtolower($protocol) || "https" == strtolower($protocol)) {
            $pinfo = NonProxyInfo::httpNonProxyInfo();
        } elseif ("ftp" == strtolower($protocol)) {
            $pinfo = NonProxyInfo::ftpNonProxyInfo();
        } elseif ("socket" == strtolower($protocol)) {
            $pinfo = NonProxyInfo::socksNonProxyInfo();
        }

        /**
         * Let's check the System properties for that protocol
         */
        $this->proto = $protocol;
        $this->nprop = $pinfo;
        $this->urlhost = strtolower($host);

        /**
         * This is one big doPrivileged call, but we're trying to optimize
         * the code as much as possible. Since we're checking quite a few
         * System properties it does help having only 1 call to doPrivileged.
         * Be mindful what you do in here though!
         */
        $proxyl[] = $this->getProxy($this->urlhost, $defProps);

        /*
         * If no specific property was set for that URI, we should be
         * returning an iterator to an empty List.
         */
        return $proxyl;
    }

    private function getProxy($urlhost, ?array $defProps = []): Proxy
    {
        $phost =  null;
        $pport = 0;
        $nphosts =  null;
        $saddr = null;

        // Then let's walk the list of protocols in our array
        for ($i = 0; $i < count(self::$props); $i += 1) {
            if (strtolower(self::$props[$i][0]) == strtolower($this->proto)) {
                for ($j = 1; $j < count(self::$props[$i]); $j += 1) {
                    /* System.getProp() will give us an empty
                     * String, "" for a defined but "empty"
                     * property.
                     */
                    $phost =  NetProperties::get(self::$props[$i][$j] . "Host", $defProps);
                    if ($phost !== null && strlen($phost) != 0) {
                        break;
                    }
                }
                if ($phost == null || strlen($phost) == 0) {
                    return Proxy::noProxy();
                }
                // If a Proxy Host is defined for that protocol
                // Let's get the NonProxyHosts property
                if ($this->nprop !== null) {
                    $nphosts = NetProperties::get($this->nprop->property, $defProps);
                    if ($nphosts == null) {
                        if ($this->nprop->defaultVal !== null) {
                            $nphosts = $this->nprop->defaultVal;
                        } else {
                            $this->nprop->hostsSource = null;
                            $this->nprop->hostsPool = null;
                        }
                    } elseif (strlen($nphosts) != 0) {
                        // add the required default patterns
                        // but only if property no set. If it
                        // is empty, leave empty.
                        $nphosts .= "|" . NonProxyInfo::defStringVal;
                    }
                    if ($nphosts !== null) {
                        if ($nphosts != $this->nprop->hostsSource) {
                            $pool = array_map(function ($val) {
                                return strtolower($val);
                            }, explode("|", $nphosts));
                            $this->nprop->hostsPool = $pool;
                            $this->nprop->hostsSource = $nphosts;
                        }
                    }
                    if ($this->nprop->hostsPool !== null) {
                        foreach ($this->nprop->hostsPool as $match) {
                            if (preg_match('/' . $match . '/im', $urlhost)) {
                                return Proxy::noProxy();
                            }
                        }
                    }
                }
                // We got a host, let's check for port

                $pport = intval(NetProperties::get(self::$props[$i][$j] . "Port", $defProps, 0));
                if ($pport == 0 && $j < (count(self::$props[$i]) - 1)) {
                    // Can't find a port with same prefix as Host
                    // AND it's not a SOCKS proxy
                    // Let's try the other prefixes for that proto
                    for ($k = 1; $k < (count(self::$props[$i]) - 1); $k += 1) {
                        if (($k != $j) && ($pport == 0)) {
                            $pport = intval(NetProperties::get(self::$props[$i][$k] . "Port", $defProps, 0));
                        }
                    }
                }

                // Still couldn't find a port, let's use default
                if ($pport == 0) {
                    if ($j == (count(self::$props[$i]) - 1)) // SOCKS
                        $pport = $this->defaultPort("socket");
                    else {
                        $pport = $this->defaultPort($this->proto);
                    }
                }
                // We did find a proxy definition.
                // Let's create the address, but don't resolve it
                // as this will be done at connection time
                $saddr = InetSocketAddress::createUnresolved($phost, $pport);
                // Socks is *always* the last on the list.
                if ($j == (count(self::$props[$i]) - 1)) {
                    $version = intval(NetProperties::get(self::SOCKS_PROXY_VERSION, $defProps, 5));
                    return SocksProxy::create($saddr, $version);
                } else {
                    return new Proxy(ProxyType::HTTP, $saddr);
                }
            }
        }
        return Proxy::noProxy();
    }

    /*public function connectFailed(URI $uri, SocketAddress $sa, ?\Exception $ioe): void
    {
        if (uri == null || sa == null || ioe == null) {
            throw new IllegalArgumentException("Arguments can't be null.");
        }
        // ignored
    }*/

    private function defaultPort(string $protocol): int
    {
        if ("http" == strtolower($protocol)) {
            return 80;
        } elseif ("https" == strtolower($protocol)) {
            return 443;
        } elseif ("ftp" == strtolower($protocol)) {
            return 80;
        } elseif ("socket" == strtolower($protocol)) {
            return 1080;
        } elseif ("gopher" == strtolower($protocol)) {
            return 80;
        } else {
            return -1;
        }
    }
}
