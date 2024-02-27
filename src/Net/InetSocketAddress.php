<?php

namespace Util\Net;

class InetSocketAddress extends SocketAddress
{
    private $holder;

    private static function checkPort(int $port): int
    {
        if ($port < 0 || $port > 0xFFFF) {
            throw new \Exception("port out of range:" . $port);
        }
        return $port;
    }

    private static function checkHost(?string $hostname = null): string
    {
        if ($hostname == null) {
            throw new \Exception("hostname can't be null");
        }
        return $hostname;
    }

    public function __construct(...$args)
    {
        if (count($args) == 1 && is_int($args[0])) {
            self::__construct(InetAddress::anyLocalAddress(), $args[0]);
        } elseif (count($args) == 2) {
            if ($args[0] instanceof InetAddress || $args[0] === null) {
                $this->holder = new InetSocketAddressHolder(
                    null,
                    $args[0] ?? InetAddress::anyLocalAddress(),
                    self::checkPort($args[1])
                );
            } elseif (is_string($args[0])) {
                self::checkHost($args[0]);
                $addr = null;
                $host = null;
                try {
                    $addr = InetAddress::getByName($args[0]);
                } catch(\Throwable $e) {
                    $host = $args[0];
                }
                $this->holder = new InetSocketAddressHolder($host, $addr, self::checkPort($args[1]));
            } elseif (is_int($args[0])) {
                $this->holder = new InetSocketAddressHolder($args[1], null, $args[0]);
            }
        }
    }

    /**
     *
     * Creates an unresolved socket address from a hostname and a port number.
     * <p>
     * No attempt will be made to resolve the hostname into an InetAddress.
     * The address will be flagged as <I>unresolved</I>.
     * <p>
     * A valid port value is between 0 and 65535.
     * A port number of {@code zero} will let the system pick up an
     * ephemeral port in a {@code bind} operation.
     * <P>
     * @param   host    the Host name
     * @param   port    The port number
     * @throws IllegalArgumentException if the port parameter is outside
     *                  the range of valid port values, or if the hostname
     *                  parameter is <TT>null</TT>.
     * @see     #isUnresolved()
     * @return  a {@code InetSocketAddress} representing the unresolved
     *          socket address
     * @since 1.5
     */
    public static function createUnresolved(string $host, int $port): InetSocketAddress
    {
        return new InetSocketAddress(self::checkPort($port), self::checkHost($host));
    }

    /**
     * Gets the port number.
     *
     * @return the port number.
     */
    public function getPort(): int
    {
        return $this->holder->getPort();
    }

    /**
     *
     * Gets the {@code InetAddress}.
     *
     * @return the InetAdress or {@code null} if it is unresolved.
     */
    public function getAddress(): InetAddress
    {
        return $this->holder->getAddress();
    }

    /**
     * Gets the {@code hostname}.
     * Note: This method may trigger a name service reverse lookup if the
     * address was created with a literal IP address.
     *
     * @return  the hostname part of the address.
     */
    public function getHostName(): ?string
    {
        return $this->holder->getHostName();
    }

    /**
     * Returns the hostname, or the String form of the address if it
     * doesn't have a hostname (it was created using a literal).
     * This has the benefit of <b>not</b> attempting a reverse lookup.
     *
     * @return the hostname, or String representation of the address.
     * @since 1.7
     */
    public function getHostString(): string
    {
        return $this->holder->getHostString();
    }

    /**
     * Checks whether the address has been resolved or not.
     *
     * @return {@code true} if the hostname couldn't be resolved into
     *          an {@code InetAddress}.
     */
    public function isUnresolved(): bool
    {
        return $this->holder->isUnresolved();
    }

    /**
     * Constructs a string representation of this InetSocketAddress.
     * This String is constructed by calling toString() on the InetAddress
     * and concatenating the port number (with a colon). If the address
     * is unresolved then the part before the colon will only contain the hostname.
     *
     * @return  a string representation of this object.
     */
    public function __toString(): string
    {
        return strval($this->holder);
    }

    /**
     * Compares this object against the specified object.
     * The result is {@code true} if and only if the argument is
     * not {@code null} and it represents the same address as
     * this object.
     * <p>
     * Two instances of {@code InetSocketAddress} represent the same
     * address if both the InetAddresses (or hostnames if it is unresolved) and port
     * numbers are equal.
     * If both addresses are unresolved, then the hostname and the port number
     * are compared.
     *
     * Note: Hostnames are case insensitive. e.g. "FooBar" and "foobar" are
     * considered equal.
     *
     * @param   obj   the object to compare against.
     * @return  {@code true} if the objects are the same;
     *          {@code false} otherwise.
     * @see java.net.InetAddress#equals(java.lang.Object)
     */
    public function equals($obj = null): bool
    {
        if ($obj == null || !($obj instanceof InetSocketAddress)) {
            return false;
        }
        return $this->holder->equals($obj->holder);
    }
}
