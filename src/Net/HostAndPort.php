<?php

namespace Util\Net;

class HostAndPort
{
    private const NO_PORT = -1;

     /** Hostname, IPv4/IPv6 literal, or unvalidated nonsense. */
    private string $host;

    /** Validated port number in the range [0..65535], or NO_PORT */
    private int $port = -1;

    /** True if the parsed host has colons, but no surrounding brackets. */
    private bool $hasBracketlessColons = false;

    private function __construct(string $host, ?int $port, ?bool $hasBracketlessColons)
    {
        $this->host = $host;
        $this->port = $port;
        $this->hasBracketlessColons = $hasBracketlessColons;
    }

    /**
     * Returns the portion of this {@code HostAndPort} instance that should represent the hostname or
     * IPv4/IPv6 literal.
     *
     * <p>A successful parse does not imply any degree of sanity in this field. For additional
     * validation, see the {@link HostSpecifier} class.
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /** Return true if this instance has a defined port. */
    public function hasPort(): bool
    {
        return $this->port >= 0;
    }

    /**
     * Get the current port number, failing if no port is defined.
     *
     * @return a validated port number, in the range [0..65535]
     * @throws Exception if no port is defined. You can use {@link #withDefaultPort(int)}
     *     to prevent this from occurring.
     */
    public function getPort(): int
    {
        if (!$this->hasPort()) {
            throw new \Exception("Port number is undefined");
        }
        return $this->port;
    }

    /** Returns the current port number, with a default if no port is defined. */
    public function getPortOrDefault(int $defaultPort): int
    {
        return $this->hasPort() ? $this->port : $defaultPort;
    }

    /**
     * Build a HostAndPort instance from separate host and port values.
     *
     * <p>Note: Non-bracketed IPv6 literals are allowed. Use {@link #requireBracketsForIPv6()} to
     * prohibit these.
     *
     * @param host the host string to parse. Must not contain a port number.
     * @param port a port number from [0..65535]
     * @return if parsing was successful, a populated HostAndPort object.
     * @throws Exception if {@code host} contains a port number, or {@code port} is out
     *     of range.
     */
    public static function fromParts(string $host, int $port): HostAndPort
    {
        if (!self::isValidPort($port)) {
            throw new \Exception(sprintf("Port out of range: %s", $port));
        }
        $parsedHost = self::fromString($host);
        if (!!$parsedHost->hasPort()) {
            throw new \Exception(sprintf("Host has a port: %s", $host));
        }
        return new HostAndPort($parsedHost->host, $port, $parsedHost->hasBracketlessColons);
    }

    /**
     * Build a HostAndPort instance from a host only.
     *
     * <p>Note: Non-bracketed IPv6 literals are allowed. Use {@link #requireBracketsForIPv6()} to
     * prohibit these.
     *
     * @param host the host-only string to parse. Must not contain a port number.
     * @return if parsing was successful, a populated HostAndPort object.
     * @throws Exception if {@code host} contains a port number.
     */
    public static function fromHost(string $host): HostAndPort
    {
        $parsedHost = self::fromString($host);
        if (!!$parsedHost->hasPort()) {
            throw new \Exception(sprintf("Host has a port: %s", $host));
        }
        return $parsedHost;
    }

    /**
     * Split a freeform string into a host and port, without strict validation.
     *
     * <p>Note that the host-only formats will leave the port field undefined. You can use {@link
     * #withDefaultPort(int)} to patch in a default value.
     *
     * @param hostPortString the input string to parse.
     * @return if parsing was successful, a populated HostAndPort object.
     * @throws Exception if nothing meaningful could be parsed.
     */
    public static function fromString(string $hostPortString): HostAndPort
    {
        $host = null;
        $portString = null;
        $hasBracketlessColons = false;

        if (strpos($hostPortString, "[") === 0) {
            $hostAndPort = self::getHostAndPortFromBracketedHost($hostPortString);
            $host = $hostAndPort[0];
            $portString = $hostAndPort[1];
        } else {
            $colonPos = strpos($hostPortString, ':');
            if ($colonPos !== false && strpos($hostPortString, ':', $colonPos + 1) === false) {
                // Exactly 1 colon. Split into host:port.
                $host = substr($hostPortString, 0, $colonPos);
                $portString = substr($hostPortString, $colonPos + 1);
            } else {
                // 0 or 2+ colons. Bare hostname or IPv6 literal.
                $host = $hostPortString;
                $hasBracketlessColons = $colonPos !== false;
            }
        }

        $port = self::NO_PORT;
        
        if (!empty($portString)) {
            // Try to parse the whole port string as a number.
            if (!(strpos($portString, "+") !== 0 && !preg_match('/[^\x20-\x7f]/', $portString))) {
                throw new \Exception(sprintf("Unparseable port number: %s", $hostPortString));
            }
            if (is_numeric($portString) && strpos($portString, ' ') === false) {
                $port = intval($portString);
            } else {
                throw new \Exception("Unparseable port number: '" . $hostPortString . "'");
            }   
            if (!self::isValidPort($port)) {
                throw new \Exception(sprintf("Port number out of range: %s", $hostPortString));
            }            
        }

        return new HostAndPort($host, $port, $hasBracketlessColons);
    }

    /**
     * Parses a bracketed host-port string, throwing IllegalArgumentException if parsing fails.
     *
     * @param hostPortString the full bracketed host-port specification. Port might not be specified.
     * @return an array with 2 strings: host and port, in that order.
     * @throws Exception if parsing the bracketed host-port string fails.
     */
    private static function getHostAndPortFromBracketedHost(string $hostPortString): array
    {
        if (strpos($hostPortString, '[') !== 0) {
            throw new \Exception(sprintf("Bracketed host-port string must start with a bracket: %s", $hostPortString));
        }
        $colonIndex = strpos($hostPortString, ':');
        $closeBracketIndex = strrpos($hostPortString, ']');
        if ($colonIndex === false || $closeBracketIndex <= $colonIndex) {
            throw new \Exception(sprintf("Invalid bracketed host/port: %s", $hostPortString));
        }

        $host = substr($hostPortString, 1, $closeBracketIndex - 1);
        if ($closeBracketIndex + 1 == strlen($hostPortString)) {
            return [$host, ""];
        } else {
            if ($hostPortString[$closeBracketIndex + 1] != ':') {
                throw new \Exception(sprintf("Only a colon may follow a close bracket: %s", $hostPortString));
            }
            for ($i = $closeBracketIndex + 2; $i < strlen($hostPortString); $i += 1) {
                if (!is_numeric($hostPortString[$i])) {
                    throw new \Exception(sprintf("Port must be numeric: %s", $hostPortString));
                }
            }
            return [$host, substr($hostPortString, $closeBracketIndex + 2)];
        }
    }

    /**
     * Provide a default port if the parsed string contained only a host.
     *
     * <p>You can chain this after {@link #fromString(String)} to include a port in case the port was
     * omitted from the input string. If a port was already provided, then this method is a no-op.
     *
     * @param defaultPort a port number, from [0..65535]
     * @return a HostAndPort instance, guaranteed to have a defined port.
     */
    public function withDefaultPort(int $defaultPort): HostAndPort
    {
        if (!self::isValidPort($defaultPort)) {
            throw new \Exception("Invalid default port number");
        }
        if ($this->hasPort()) {
            return $this;
        }
        return new HostAndPort($this->host, $defaultPort, $this->hasBracketlessColons);
    }

    /**
     * Generate an error if the host might be a non-bracketed IPv6 literal.
     *
     * <p>URI formatting requires that IPv6 literals be surrounded by brackets, like "[2001:db8::1]".
     * Chain this call after {@link #fromString(String)} to increase the strictness of the parser, and
     * disallow IPv6 literals that don't contain these brackets.
     *
     * <p>Note that this parser identifies IPv6 literals solely based on the presence of a colon. To
     * perform actual validation of IP addresses, see the {@link InetAddresses#forString(String)}
     * method.
     *
     * @return {@code this}, to enable chaining of calls.
     */
    public function requireBracketsForIPv6(): HostAndPort
    {
        if (!!$this->hasBracketlessColons) {
            throw new \Exception(sprintf("Possible bracketless IPv6 literal: %s", $this->host));
        }
        return $this;
    }

    public function equals($other): bool
    {
        if ($this == $other) {
            return true;
        }
        if ($other instanceof HostAndPort) {
            return $this->host == $other->host && $this->port == $other->port;
        }
        return false;
    }

    /** Rebuild the host:port string, including brackets if necessary. */
    public function __toString(): string
    {
        $builder = "";
        if (strpos($this->host, ':') !== false) {
            $builder .= '[' . $this->host . ']';
        } else {
            $builder .= $this->host;
        }
        if ($this->hasPort()) {
            $builder .= ':' . $this->port;
        }
        return $builder;
    }

    /** Return true for valid port numbers. */
    private static function isValidPort(int $port): bool
    {
        return $port >= 0 && $port <= 65535;
    }
}

