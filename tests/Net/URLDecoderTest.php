<?php

namespace Tests\Net;

use Util\Net\URLDecoder;
use PHPUnit\Framework\TestCase;

class URLDecoderTest extends TestCase
{
    public function testDecode(): void
    {
        $str = URLDecoder::decode("I%20love%20coding%2C%20it%27s%20fun%21");
        $this->assertEquals('I love coding, it\'s fun!', $str);
    }
}
