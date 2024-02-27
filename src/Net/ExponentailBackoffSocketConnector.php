<?php

namespace Util\Net;

class ExponentailBackoffSocketConnector
{
    private $connector;
    private $maxRetries;

    //in microseconds
    private $baseDelay;
    private $maxDelay;

    public function __construct(callable $connector, $maxRetries = 10, $baseDelay = 1000, $maxDelay = 1000000)
    {
        $this->connector = $connector;
        $this->maxRetries = $maxRetries;
        $this->baseDelay = $baseDelay;
        $this->maxDelay = $maxDelay;
    }

    public function connect()
    {
        $client = false;
        $attempt = 0;
        $delay = 0;
        $connector = $this->connector;
        while ($attempt < $this->maxRetries && !$client) {
            $client = $connector();
            if ($client) {
                return $client;
            } else {
                $attempt += 1;
                $delay = min(pow(2, $attempt - 1) * $this->baseDelay, $this->maxDelay);
                usleep($delay);
            }
        }

        if (!$client) {
            throw new \Exception("Network is unreachable");
        }

        return $client;
    }
}
