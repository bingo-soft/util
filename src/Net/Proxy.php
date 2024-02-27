<?php

namespace Util\Net;

class Proxy
{
    private $type;
    private $sa;

    /**
     * A proxy setting that represents a {@code DIRECT} connection,
     * basically telling the protocol handler not to use any proxying.
     * Used, for instance, to create sockets bypassing any other global
     * proxy settings (like SOCKS):
     * <P>
     * {@code Socket s = new Socket(Proxy.NO_PROXY);}
     *
     */
    private static $NO_PROXY;

    public static function noProxy(): Proxy
    {
        if (self::$NO_PROXY == null) {
            self::$NO_PROXY = new Proxy();
        }
        return self::$NO_PROXY;
    }

    // Creates the proxy that represents a {@code DIRECT} connection.
    public function __construct(...$args)
    {
        if (empty($args)) {
            $this->type = ProxyType::DIRECT;
            $this->sa = null;
        } else {
            if (($args[0] == ProxyType::DIRECT) || !($args[1] instanceof InetSocketAddress)) {
                throw new \Exception("type " . $args[0] . " is not compatible with address " . $args[1]);
            }
            $this->type = $args[0];
            $this->sa = $args[1];
        }
    }

    /**
     * Returns the proxy type.
     *
     * @return a Type representing the proxy type
     */
    public function type(): ProxyType
    {
        return $this->type;
    }

    /**
     * Returns the socket address of the proxy, or
     * {@code null} if its a direct connection.
     *
     * @return a {@code SocketAddress} representing the socket end
     *         point of the proxy
     */
    public function address(): ?SocketAddress
    {
        return $this->sa;
    }

    /**
     * Constructs a string representation of this Proxy.
     * This String is constructed by calling toString() on its type
     * and concatenating " @ " and the toString() result from its address
     * if its type is not {@code DIRECT}.
     *
     * @return  a string representation of this object.
     */
    public function __toString(): string
    {
        if ($this->type() == ProxyType::DIRECT) {
            return "DIRECT";
        }
        return $this->type() . " @ " . $this->address();
    }

    /**
     * Compares this object against the specified object.
     * The result is {@code true} if and only if the argument is
     * not {@code null} and it represents the same proxy as
     * this object.
     * <p>
     * Two instances of {@code Proxy} represent the same
     * address if both the SocketAddresses and type are equal.
     *
     * @param   obj   the object to compare against.
     * @return  {@code true} if the objects are the same;
     *          {@code false} otherwise.
     * @see java.net.InetSocketAddress#equals(java.lang.Object)
     */
    public function equals($obj = null): bool
    {
        if ($obj == null || !($obj instanceof Proxy)) {
            return false;
        }
        if ($obj->type() == $this->type()) {
            if ($this->address() == null) {
                return ($obj->address() == null);
            } else {
                if (method_exists($this->address(), 'equals')) {
                    return $this->address()->equals($obj->address());
                }
                return $this->address() == $obj->address();
            }
        }
        return false;
    }
}