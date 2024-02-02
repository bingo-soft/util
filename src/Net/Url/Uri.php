<?php

namespace Util\Net\Url;

class Uri
{
    protected $uri;
    protected $scheme;
    protected $host = null;
    protected int $port = -1;
    protected bool $hasAuthority = false;
    protected $path;
    protected $query = null;

    /**
     * Creates a Uri object given a URI string.
     */
    public function __construct(?string $uri = null)
    {
        if ($uri !== null) {
            $this->init($uri);
        }
    }

    /**
     * Initializes a Uri object given a URI string.
     * This method must be called exactly once, and before any other Uri
     * methods.
     */
    protected function init(string $uri): void
    {
        $this->uri = $uri;
        $this->parse($uri);
    }

    /**
     * Returns the URI's scheme.
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * Returns the host from the URI's authority part, or null
     * if no host is provided.  If the host is an IPv6 literal, the
     * delimiting brackets are part of the returned value (see
     * {@link java.net.URI#getHost}).
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Returns the port from the URI's authority part, or -1 if
     * no port is provided.
     */
    public function getPort(): ?int
    {
        return $this->port;
    }

    /**
     * Returns the URI's path.  The path is never null.  Note that a
     * slash following the authority part (or the scheme if there is
     * no authority part) is part of the path.  For example, the path
     * of "http://host/a/b" is "/a/b".
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Returns the URI's query part, or null if no query is provided.
     * Note that a query always begins with a leading "?".
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * Returns the URI as a string.
     */
    public function __toString(): string
    {
        return $this->uri;
    }

    /*
     * Parses a URI string and sets this object's fields accordingly.
     */
    private function parse(string $uri): void
    {
        $i = strpos($uri, ':'); // parse scheme
        if ($i < 0) {
            throw new \Exception("Invalid URI: " . $uri);
        }
        $this->scheme = substr($uri, 0, $i);
        $i++; // skip past ":"

        $this->hasAuthority = strpos($uri, "//", $i);
        if ($this->hasAuthority) { // parse "//host:port"
            $i += 2; // skip past "//"
            $slash = strpos($uri, '/', $i);
            if ($slash === false) {
                $slash = strlen($uri);
            }
            if (strpos($uri, "[", $i) !== false) { // at IPv6 literal
                $brac = strpos($uri, ']', $i + 1);
                if ($brac  === false || $brac > $slash) {
                    throw new \Exception("Invalid URI: " . $uri);
                }
                $this->host = substr($uri, $i, $brac + 1 - $i); // include brackets
                $i = $brac + 1; // skip past "[...]"
            } else { // at host name or IPv4
                $colon = strpos($uri, ':', $i);
                $hostEnd = ($colon === false || $colon > $slash)
                    ? $slash
                    : $colon;
                if ($i < $hostEnd) {
                    $this->host = substr($uri, $i, $hostEnd - $i);
                }
                $i = $hostEnd; // skip past host
            }

            if (($i + 1 < $slash) && strpos($uri, ":", $i) !== false) { // parse port
                $i++; // skip past ":"
                $this->port = intval(substr($uri, $i, $slash - $i));
            }
            $i = $slash; // skip to path
        }
        $qmark = strpos($uri, '?', $i); // look for query
        if ($qmark === false) {
            $this->path = substr($uri, $i);
        } else {
            $this->path = substr($uri, $i, $qmark - $i);
            $this->query = substr($uri, $qmark);
        }
    }
}
