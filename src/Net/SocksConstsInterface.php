<?php

namespace Util\Net;

interface SocksConstsInterface
{
    public const PROTO_VERS4 = 4;
    public const PROTO_VERS = 5;
    public const DEFAULT_PORT = 1080;

    public const NO_AUTH = 0;
    public const GSSAPI = 1;
    public const USER_PASSW = 2;
    public const NO_METHODS = -1;

    public const CONNECT = 1;
    public const BIND = 2;
    public const UDP_ASSOC = 3;

    public const IPV4 = 1;
    public const DOMAIN_NAME = 3;
    public const IPV6 = 4;

    public const REQUEST_OK = 0;
    public const GENERAL_FAILURE = 1;
    public const NOT_ALLOWED = 2;
    public const NET_UNREACHABLE = 3;
    public const HOST_UNREACHABLE = 4;
    public const CONN_REFUSED = 5;
    public const TTL_EXPIRED = 6;
    public const CMD_NOT_SUPPORTED = 7;
    public const ADDR_TYPE_NOT_SUP = 8;

    public const NETWORK_RESOURCE_FILE_NAME = 'src/Resources/net.properties';
}
