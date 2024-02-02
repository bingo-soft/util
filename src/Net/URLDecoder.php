<?php

namespace Util\Net;

class URLDecoder
{
    public static function decode(string $s): ?string
    {
        return urldecode($s);
    }
}
