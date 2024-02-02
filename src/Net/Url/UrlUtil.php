<?php

namespace Util\Net\Url;

class UrlUtil
{
    public static function decode(string $str): string
    {
        return urldecode($str);
    }

    public static function encode(string $str): string
    {
        return urlencode($str);
    }
}
