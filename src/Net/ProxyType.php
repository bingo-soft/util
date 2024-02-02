<?php

namespace Util\Net;

enum ProxyType
{
    /**
     * Represents a direct connection, or the absence of a proxy.
     */
    case DIRECT;

    /**
     * Represents proxy for high level protocols such as HTTP or FTP.
     */
    case HTTP;
    
    /**
     * Represents a SOCKS (V4 or V5) proxy.
     */
    case SOCKS;
}
