<?php

namespace Util\Net;

enum RequestorType
{
    /**
     * Entity requesting authentication is a HTTP proxy server.
     */
    case PROXY;
    /**
     * Entity requesting authentication is a HTTP origin server.
     */
    case SERVER;
}
