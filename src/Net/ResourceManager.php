<?php

namespace Util\Net;

class ResourceManager
{
    /* default maximum number of udp sockets per VM
     * when a security manager is enabled.
     * The default is 25 which is high enough to be useful
     * but low enough to be well below the maximum number
     * of port numbers actually available on all OSes
     * when multiplied by the maximum feasible number of VM processes
     * that could practically be spawned.
     */
    private const DEFAULT_MAX_SOCKETS = 25;
    private const MAX_DATAGRAM_SOCKETS = 'net.maxDatagramSockets';
    private static $maxSockets;
    private static $numSockets;
    private static $initialized = false;

    public function init(?string $resourcePath = 'src/Resources/php.security'): void
    {
        if (!self::$initialized) {
            $props = [];
            if (!empty(getenv(self::MAX_DATAGRAM_SOCKETS))) {
                self::$maxSockets = getenv(self::MAX_DATAGRAM_SOCKETS);
            } elseif (file_exists($resourcePath)) {                
                $fp = fopen($resourcePath, "r");       
                while (($line = fgets($fp, 4096)) !== false) {
                    $tokens = explode("=", $line);
                    if (count($tokens) == 2) {
                        $props[$tokens[0]] = trim($tokens[1]);
                    }
                }
                fclose($fp);

                if (array_key_exists(self::MAX_DATAGRAM_SOCKETS, $props)) {
                    self::$maxSockets = $props[self::MAX_DATAGRAM_SOCKETS];
                }
            }
            self::$numSockets = 0;
            self::$initialized = true;
        }
    }

    public static function beforeUdpCreate(): void
    {
        self::$numSockets += 1;
        if (self::$maxSockets !== null && self::$numSockets > self::$maxSockets) {
            self::$numSockets -= 1;
            throw new \Exception("maximum number of DatagramSockets reached");
        }
    }

    public static function afterUdpClose(): void
    {
        self::$numSockets -= 1;
    }
}
