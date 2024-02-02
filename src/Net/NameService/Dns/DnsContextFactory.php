<?php

namespace Util\Net\NameService\Dns;

use Util\Net\Naming\{
    ContextInterface,
    InitialContextFactoryInterface
};
use Util\Net\Url\UrlUtil;

class DnsContextFactory implements InitialContextFactoryInterface
{
    private const DEFAULT_URL = "dns:";
    private const DEFAULT_PORT = 53;

    public function getInitialContext(array $env = []): ?ContextInterface
    {
        return self::urlToContext(self::getInitCtxUrl($env), $env);
    }

    public static function getContext(string $domain, array $args, array $env): ?DnsContext
    {
        if (!empty($args)) {
            if (is_string($args[0])) {
                return new DnsContext($domain, $args, $env);
            }
        } else {
            $servers = self::serversForUrls($args);
            $ctx = self::getContext($domain, $servers, $env);
            if (self::platformServersUsed($args)) {
                $ctx->setProviderUrl(self::constructProviderUrl($domain, $servers));
            }
            return $ctx;
        }
        return null;
    }

    /*
     * Public for use by product test suite.
     */
    public static function platformServersAvailable(): bool
    {
        return !empty(self::filterNameServers(
            ResolverConfiguration::open()->nameservers(), true
        ));
    }

    private static function urlToContext(string $url, array $env): ContextInterface
    {
        $urls = DnsUrl::fromList($url);
        if (count($urls) == 0) {
            throw new \Exception("Invalid DNS pseudo-URL(s): " . $url);
        }
        $domain = $urls[0]->getDomain();

        // If multiple urls, all must have the same domain.
        for ($i = 1; $i < count($urls); $i += 1) {
            if (strtolower($domain) != $urls[$i]->getDomain()) {
                throw new \Exception("Conflicting domains: " . $url);
            }
        }
        return self::getContext($domain, $urls, $env);
    }

    /*
     * Returns all the servers specified in a set of URLs.
     * If a URL has no host (or port), the servers configured on the
     * underlying platform are used if possible.  If no configured
     * servers can be found, then fall back to the old behavior of
     * using "localhost".
     * There must be at least one URL.
     */
    private static function serversForUrls(array $urls): array
    {
        if (empty($urls)) {
            throw new \Exception("DNS pseudo-URL required");
        }

        $servers = [];

        for ($i = 0; $i < count($urls); $i += 1) {
            $server = $urls[$i]->getHost();
            $port = $urls[$i]->getPort();

            if ($server == null && $port < 0) {
                // No server or port given, so look to underlying platform.
                // ResolverConfiguration does some limited caching, so the
                // following is reasonably efficient even if called rapid-fire.
                $platformServers = self::filterNameServers(ResolverConfiguration::open()->nameservers(), false);
                if (!empty($platformServers)) {
                    $servers = array_merge($servers, $platformServers);
                    continue;  // on to next URL (if any, which is unlikely)
                }
            }

            if ($server == null) {
                $server = "localhost";
            }
            $servers[] = ($port < 0) ? $server : ( $server . ":" . $port );
        }
        return $servers;
    }

    /*
     * Returns true if serversForUrls(urls) would make use of servers
     * from the underlying platform.
     */
    private static function platformServersUsed(array $urls): bool
    {
        if (!self::platformServersAvailable()) {
            return false;
        }
        for ($i = 0; $i < count($urls); $i += 1) {
            if ($urls[$i]->getHost() == null && $urls[$i]->getPort() < 0) {
                return true;
            }
        }
        return false;
    }

    /*
     * Returns a value for the PROVIDER_URL property (space-separated URL
     * Strings) that reflects the given domain and servers.
     * Each server is of the form "server[:port]".
     * There must be at least one server.
     * IPv6 literal host names include delimiting brackets.
     */
    private static function constructProviderUrl(string $domain, array $servers): string
    {
        $path = "";
        if ($domain != ".") {
            try {
                $path = "/" . UrlUtil::encode($domain);
            } catch (\Throwable $e) {
                // assert false : "ISO-Latin-1 charset unavailable";
            }
        }

        $buf = "";
        for ($i = 0; $i < count($servers); $i += 1) {
            if ($i > 0) {
                $buf .= ' ';
            }
            $buf .= "dns://" . $servers[$i] . $path;
        }
        return $buf;
    }

    /*
     * Reads environment to find URL(s) of initial context.
     * Default URL is "dns:".
     */
    private static function getInitCtxUrl(array $env): string
    {
        if (array_key_exists(ContextInterface::PROVIDER_URL, $env)) {
            return $env[ContextInterface::PROVIDER_URL];
        }
        return self::DEFAULT_URL;
    }

    /**
     * Removes any DNS server that's not permitted to access
     * @param input the input server[:port] list, must not be null
     * @param oneIsEnough return output once there exists one ok
     * @return the filtered list, all non-permitted input removed
     */
    private static function filterNameServers(array $input, bool $oneIsEnough): array
    {
        $output = [];
        foreach ($input as $platformServer) {
            $colon = strpos($platformServer, ':', strpos($platformServer, ']') + 1);

            $p = ($colon < 0) ? self::DEFAULT_PORT : intval(substr($platformServer, $colon + 1));
            $s = ($colon < 0) ? $platformServer : substr($platformServer, 0, $colon);
            try {
                $output[] = $platformServer;
                if ($oneIsEnough) {
                    return $output;
                }
            } catch (\Throwable $se) {
                continue;
            }
        }
        return $output;
    }
}
