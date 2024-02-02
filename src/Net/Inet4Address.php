<?php

namespace Util\Net;

use Util\Net\Util\IPAddressUtil;

class Inet4Address extends InetAddress
{
    public const INADDRSZ = 4;

    public function __construct(?string $hostName = null, string | array $addr = null) {
        if ($hostName == null && $addr == null) {
            parent::__construct();
            $this->holder()->hostName = null;
            $this->holder()->address = 0;
            $this->holder()->family = self::IPv4;
        } else {
            parent::__construct();
            $this->holder()->hostName = $hostName;
            $this->holder()->family = self::IPv4;
            if (is_array($addr)) {
                if (count($addr) == self::INADDRSZ) {
                    $address = $addr[3] & 0xFF;
                    $address |= (($addr[2] << 8) & 0xFF00);
                    $address |= (($addr[1] << 16) & 0xFF0000);
                    $address |= (($addr[0] << 24) & 0xFF000000);
                    $this->holder()->address = $address;
                }
            } elseif (is_int($addr)) {
                $this->holder()->address = $addr;
            } elseif (is_string($addr)) {
                $addrNew = IPAddressUtil::textToNumericFormatV4($addr);
                $address = $addrNew[3] & 0xFF;
                $address |= (($addrNew[2] << 8) & 0xFF00);
                $address |= (($addrNew[1] << 16) & 0xFF0000);
                $address |= (($addrNew[0] << 24) & 0xFF000000);
                $this->holder()->address = $address;
            }
        }  
    }

    /**
     * Replaces the object to be serialized with an InetAddress object.
     *
     * @return the alternate object to be serialized.
     *
     * @throws ObjectStreamException if a new object replacing this
     * object could not be created
     */
    private function writeReplace()
    {
        // will replace the to be serialized 'this' object
        $inet = new InetAddress();
        $inet->holder()->hostName = $this->holder()->getHostName();
        $inet->holder()->address = $this->holder()->getAddress();

        /**
         * Prior to 1.4 an InetAddress was created with a family
         * based on the platform AF_INET value (usually 2).
         * For compatibility reasons we must therefore write the
         * the InetAddress with this family.
         */
        $inet->holder()->family = 2;

        return $inet;
    }

    /**
     * Utility routine to check if the InetAddress is an
     * IP multicast address. IP multicast address is a Class D
     * address i.e first four bits of the address are 1110.
     * @return a {@code boolean} indicating if the InetAddress is
     * an IP multicast address
     * @since   JDK1.1
     */
    public function isMulticastAddress(): bool
    {
        return (($this->holder()->getAddress() & 0xf0000000) == 0xe0000000);
    }

    /**
     * Utility routine to check if the InetAddress in a wildcard address.
     * @return a {@code boolean} indicating if the Inetaddress is
     *         a wildcard address.
     * @since 1.4
     */
    public function isAnyLocalAddress(): bool
    {
        return $this->holder()->getAddress() == 0;
    }

    /**
     * Utility routine to check if the InetAddress is a loopback address.
     *
     * @return a {@code boolean} indicating if the InetAddress is
     * a loopback address; or false otherwise.
     * @since 1.4
     */
    public function isLoopbackAddress(): bool
    {
        /* 127.x.x.x */
        $byteAddr = $this->getAddress();
        return $byteAddr[0] == 127;
    }

    /**
     * Utility routine to check if the InetAddress is an link local address.
     *
     * @return a {@code boolean} indicating if the InetAddress is
     * a link local address; or false if address is not a link local unicast address.
     * @since 1.4
     */
    public function isLinkLocalAddress(): bool
    {
        // link-local unicast in IPv4 (169.254.0.0/16)
        // defined in "Documenting Special Use IPv4 Address Blocks
        // that have been Registered with IANA" by Bill Manning
        // draft-manning-dsua-06.txt
        $address = $this->holder()->getAddress();
        return (($this->uRShift($address, 24) & 0xFF) == 169) && (($this->uRShift($address, 16) & 0xFF) == 254);
    }

    /**
     * Utility routine to check if the InetAddress is a site local address.
     *
     * @return a {@code boolean} indicating if the InetAddress is
     * a site local address; or false if address is not a site local unicast address.
     * @since 1.4
     */
    public function isSiteLocalAddress(): bool
    {
        // refer to RFC 1918
        // 10/8 prefix
        // 172.16/12 prefix
        // 192.168/16 prefix
        $address = $this->holder()->getAddress();
        return (($this->uRShift($address, 24) & 0xFF) == 10)
            || ((($this->uRShift($address, 24) & 0xFF) == 172)
                && (($this->uRShift($address, 16) & 0xF0) == 16))
            || ((($this->uRShift($address, 24) & 0xFF) == 192)
                && (($this->uRShift($address, 16) & 0xFF) == 168));
    }

    /**
     * Utility routine to check if the multicast address has global scope.
     *
     * @return a {@code boolean} indicating if the address has
     *         is a multicast address of global scope, false if it is not
     *         of global scope or it is not a multicast address
     * @since 1.4
     */
    public function isMCGlobal(): bool
    {
        // 224.0.1.0 to 238.255.255.255
        $byteAddr = $this->getAddress();
        return (($byteAddr[0] & 0xff) >= 224 && ($byteAddr[0] & 0xff) <= 238 ) &&
            !(($byteAddr[0] & 0xff) == 224 && $byteAddr[1] == 0 && $byteAddr[2] == 0);
    }

    /**
     * Utility routine to check if the multicast address has node scope.
     *
     * @return a {@code boolean} indicating if the address has
     *         is a multicast address of node-local scope, false if it is not
     *         of node-local scope or it is not a multicast address
     * @since 1.4
     */
    public function isMCNodeLocal(): bool
    {
        // unless ttl == 0
        return false;
    }

    /**
     * Utility routine to check if the multicast address has link scope.
     *
     * @return a {@code boolean} indicating if the address has
     *         is a multicast address of link-local scope, false if it is not
     *         of link-local scope or it is not a multicast address
     * @since 1.4
     */
    public function isMCLinkLocal(): bool
    {
        // 224.0.0/24 prefix and ttl == 1
        $address = $this->holder()->getAddress();
        return (($this->uRShift($address, 24) & 0xFF) == 224)
            && (($this->uRShift($address, 16) & 0xFF) == 0)
            && (($this->uRShift($address, 8) & 0xFF) == 0);
    }

    /**
     * Utility routine to check if the multicast address has site scope.
     *
     * @return a {@code boolean} indicating if the address has
     *         is a multicast address of site-local scope, false if it is not
     *         of site-local scope or it is not a multicast address
     * @since 1.4
     */
    public function isMCSiteLocal(): bool
    {
        // 239.255/16 prefix or ttl < 32
        $address = $this->holder()->getAddress();
        return (($this->uRShift($address, 24) & 0xFF) == 239)
            && (($this->uRShift($address, 16) & 0xFF) == 255);
    }

    /**
     * Utility routine to check if the multicast address has organization scope.
     *
     * @return a {@code boolean} indicating if the address has
     *         is a multicast address of organization-local scope,
     *         false if it is not of organization-local scope
     *         or it is not a multicast address
     * @since 1.4
     */
    public function isMCOrgLocal(): bool
    {
        // 239.192 - 239.195
        $address = $this->holder()->getAddress();
        return (($this->uRShift($address, 24) & 0xFF) == 239)
            && (($this->uRShift($address, 16) & 0xFF) >= 192)
            && (($this->uRShift($address, 16) & 0xFF) <= 195);
    }

    /**
     * Returns the raw IP address of this {@code InetAddress}
     * object. The result is in network byte order: the highest order
     * byte of the address is in {@code getAddress()[0]}.
     *
     * @return  the raw IP address of this object.
     */
    public function getAddress(): array
    {
        $address = $this->holder()->getAddress();
        $addr = [];
        $addr[0] = ($this->uRShift($address, 24)) & 0xFF;
        $addr[1] = ($this->uRShift($address, 16)) & 0xFF;
        $addr[2] = ($this->uRShift($address, 8)) & 0xFF;
        $addr[3] = $address & 0xFF;
        return $addr;
    }

    private function uRShift(int $a, int $b): int
    {
        if ($b == 0) {
            return $a;
        }
        return ($a >> $b) & ~(1<<(8*PHP_INT_SIZE-1)>>($b-1));
    }

    /**
     * Returns the IP address string in textual presentation form.
     *
     * @return  the raw IP address in a string format.
     * @since   JDK1.0.2
     */
    public function getHostAddress(): string
    {
        return self::numericToTextFormat($this->getAddress());
    }

    /**
     * Compares this object against the specified object.
     * The result is {@code true} if and only if the argument is
     * not {@code null} and it represents the same IP address as
     * this object.
     * <p>
     * Two instances of {@code InetAddress} represent the same IP
     * address if the length of the byte arrays returned by
     * {@code getAddress} is the same for both, and each of the
     * array components is the same for the byte arrays.
     *
     * @param   obj   the object to compare against.
     * @return  {@code true} if the objects are the same;
     *          {@code false} otherwise.
     * @see     java.net.InetAddress#getAddress()
     */
    public function equals($obj = null): bool
    {
        return ($obj != null) && ($obj instanceof Inet4Address) &&
            ($obj->holder()->getAddress() == $this->holder()->getAddress());
    }

    // Utilities
    /*
     * Converts IPv4 binary address into a string suitable for presentation.
     *
     * @param src a byte array representing an IPv4 numeric address
     * @return a String representing the IPv4 address in
     *         textual representation format
     * @since 1.4
     */

    public static function numericToTextFormat(array $src): string
    {
        return ($src[0] & 0xff) . "." . ($src[1] & 0xff) . "." . ($src[2] & 0xff) . "." . ($src[3] & 0xff);
    }
}
