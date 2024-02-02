<?php

namespace Util\Net\Util;

class IPAddressUtil
{
    private const INADDR4SZ = 4;
    private const INADDR16SZ = 16;
    private const INT16SZ = 2;

    public static function textToNumericFormatV4(string $src): ?array
    {
        $res = [];

        $tmpValue = 0;
        $currByte = 0;

        $len = strlen($src);
        if ($len == 0 || $len > 15) {
            return null;
        }
        for ($i = 0; $i < $len; $i += 1) {
            $c = $src[$i];
            if ($c == '.') {
                if ($tmpValue < 0 || $tmpValue > 0xff || $currByte == 3) {
                    return null;
                }
                $d = $tmpValue & 0xff;
                $res[$currByte++] = $d;
                $tmpValue = 0;
            } else {
                $digit = intval($c);
                if ($digit < 0) {
                    return null;
                }
                $tmpValue *= 10;
                $tmpValue += $digit;
            }
        }
        if ($tmpValue < 0 || $tmpValue >= (1 << ((4 - $currByte) * 8))) {
            return null;
        }
        switch ($currByte) {
            case 0:
                $res[0] = (($tmpValue >> 24) & 0xff);
            case 1:
                $res[1] = (($tmpValue >> 16) & 0xff);
            case 2:
                $res[2] = (($tmpValue >> 8) & 0xff);
            case 3:
                $res[3] = (($tmpValue >> 0) & 0xff);
        }
        ksort($res);
        return $res;
    }

    public static function textToNumericFormatV6(string $src): ?array
    {
        // Shortest valid string is "::", hence at least 2 chars
        if (strlen($src) < 2) {
            return null;
        }

        $colonp = -1;
        $ch = null;
        $sawXdigit = false;
        $val = 0;
        $srcb = $src;
        $dst = [];

        $srcbLength = strlen($srcb);
        $pc = strpos($src, "%");
        if ($pc == $srcbLength - 1) {
            return null;
        }

        if ($pc !== false) {
            $srcbLength = $pc;
        }

        $i = 0;
        $j = 0;
        /* Leading :: requires some special handling. */
        if ($srcb[$i] == ':') {
            if ($srcb[++$i] != ':') {
                return null;
            }
        }
        $curtok = $i;
        $sawXdigit = false;
        $val = 0;
        while ($i < $srcbLength) {
            $ch = $srcb[$i++];
            $chval = intval(@base_convert($ch, 16, 10));
            if (!($chval == '0' && $ch != '0')) {
                $val <<= 4;
                $val |= $chval;
                if ($val > 0xffff) {
                    return null;
                }
                $sawXdigit = true;
                continue;
            }
            if ($ch == ':') {
                $curtok = $i;
                if (!$sawXdigit) {
                    if ($colonp != -1) {
                        return null;
                    }
                    $colonp = $j;
                    continue;
                } elseif ($i == $srcbLength) {
                    return null;
                }
                if ($j + self::INT16SZ > self::INADDR16SZ) {
                    return null;
                }
                $dst[$j++] = (($val >> 8) & 0xff);
                $dst[$j++] = $val & 0xff;
                $sawXdigit = false;
                $val = 0;
                continue;
            }
            if ($ch == '.' && (($j + self::INADDR4SZ) <= self::INADDR16SZ)) {
                $ia4 = substr($src, $curtok, $srcbLength - $curtok);
                /* check this IPv4 address has 3 dots, ie. A.B.C.D */
                $dotCount = 0;
                $index = 0;
                while (($index = strpos($ia4, '.', $index)) !== false) {
                    $dotCount++;
                    $index++;
                }
                if ($dotCount != 3) {
                    return null;
                }
                $v4addr = self::textToNumericFormatV4($ia4);
                if ($v4addr == null) {
                    return null;
                }
                for ($k = 0; $k < self::INADDR4SZ; $k++) {
                    $dst[$j++] = $v4addr[$k];
                }
                $sawXdigit = false;
                break;  /* '\0' was seen by inet_pton4(). */
            }
            return null;
        }
        if ($sawXdigit) {
            if ($j + self::INT16SZ > self::INADDR16SZ) {
                return null;
            }            
            $dst[$j++] = ($val >> 8) & 0xff;
            $dst[$j++] = $val & 0xff;
        }

        if ($colonp != -1) {
            $n = $j - $colonp;

            if ($j == self::INADDR16SZ) {
                return null;
            }
            for ($i = 1; $i <= $n; $i++) {
                $dst[self::INADDR16SZ - $i] = $dst[$colonp + $n - $i];
                $dst[$colonp + $n - $i] = '00';
            }
            $j = self::INADDR16SZ;
        }
        if ($j != self::INADDR16SZ) {
            return null;
        }
        $newdst = self::convertFromIPv4MappedAddress($dst);
        if ($newdst != null) {
            return self::completeV6($newdst);
        } else {
            return self::completeV6($dst);
        }
    }

    private static function completeV6(array $arr): array
    {
        for ($i = 0; $i < self::INADDR16SZ; $i += 1) {
            if (!array_key_exists($i, $arr)) {
                $arr[$i] = 0;
            }
        }
        ksort($arr);
        return $arr;
    }

    /**
     * @param src a String representing an IPv4 address in textual format
     * @return a boolean indicating whether src is an IPv4 literal address
     */
    public static function isIPv4LiteralAddress(string $src): bool
    {
        return self::textToNumericFormatV4($src) != null;
    }

    /**
     * @param src a String representing an IPv6 address in textual format
     * @return a boolean indicating whether src is an IPv6 literal address
     */
    public static function isIPv6LiteralAddress(string $src): bool
    {
        return self::textToNumericFormatV6($src) != null;
    }

    /*
     * Convert IPv4-Mapped address to IPv4 address. Both input and
     * returned value are in network order binary form.
     *
     * @param src a String representing an IPv4-Mapped address in textual format
     * @return a byte array representing the IPv4 numeric address
     */
    public static function convertFromIPv4MappedAddress(array $addr): ?array
    {
        if (self::isIPv4MappedAddress($addr)) {
            $newAddr = array_slice($addr, 12, self::INADDR4SZ);
            return $newAddr;
        }
        return null;
    }

    /**
     * Utility routine to check if the InetAddress is an
     * IPv4 mapped IPv6 address.
     *
     * @return a <code>boolean</code> indicating if the InetAddress is
     * an IPv4 mapped IPv6 address; or false if address is IPv4 address.
     */
    private static function isIPv4MappedAddress(array $addr): bool
    {
        if (count($addr) < self::INADDR16SZ) {
            return false;
        }
        if (
            ($addr[0] == 0x00) && ($addr[1] == 0x00) &&
            ($addr[2] == 0x00) && ($addr[3] == 0x00) &&
            ($addr[4] == 0x00) && ($addr[5] == 0x00) &&
            ($addr[6] == 0x00) && ($addr[7] == 0x00) &&
            ($addr[8] == 0x00) && ($addr[9] == 0x00) &&
            ($addr[10] == 0xff) &&
            ($addr[11] == 0xff)
        )  {
            return true;
        }
        return false;
    }
}
