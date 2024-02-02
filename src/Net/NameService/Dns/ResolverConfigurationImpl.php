<?php

namespace Util\Net\NameService\Dns;

use Symfony\Component\Process\Process;

class ResolverConfigurationImpl extends ResolverConfiguration
{
    private array $searchlist = [];
    private array $nameservers = [];
    private const TIMEOUT = 300;
    private static $lastRefresh = -1;

    private function resolvconf(string $keyword, int $maxperkeyword, int $maxkeywords): array
    {
        $ll = [];
 
        $fp = fopen("/etc/resolv.conf", "r"); 
        while (($line = fgets($fp, 4096)) !== false) {
            $maxvalues = $maxperkeyword;
            if (strlen($line) == 0) {
                continue;
            }
            if (strpos($line, '#') === 0 || strpos($line, ';') === 0) {
                continue;
            }
            if (strpos($line, $keyword) !== 0) {
                continue;
            }
            $tokens = array_slice(preg_split('/\s+/', $line), 1);
            foreach ($tokens as $token) {
                if (strpos($token, '#') === 0 || strpos($token, ';') === 0 || empty($token)) {
                    break;
                }
                $ll[] = $token;
                if (--$maxvalues == 0) {
                    break;
                }
            }
            if (--$maxkeywords == 0) {
                break;
            }
        }
        fclose($fp);
        return $ll;
    }

    private function loadConfig(): void
    {
        // check if cached settings have expired.
        if (self::$lastRefresh >= 0) {
            $currTime = time();
            if (($currTime - self::$lastRefresh) < self::TIMEOUT) {
                return;
            }
        }

        // get the name servers from /etc/resolv.conf
        $this->nameservers = $this->resolvconf("nameserver", 1, 5);

        // get the search list (or domain)
        $this->searchlist = $this->getSearchList();

        // update the timestamp on the configuration
        self::$lastRefresh = time();
    }

    private function getSearchList(): array
    {
        $sl = [];

        // first try the search keyword in /etc/resolv.conf

        $sl = $this->resolvconf("search", 6, 1);
        if (!empty($sl)) {
            return $sl;
        }

        // try domain keyword in /etc/resolv.conf

        $sl = $this->resolvconf("domain", 1, 1);
        if (!empty($sl)) {
            return $sl;
        }

        $process = new Process(['hostname', '--fqdn']);
        $process->start();
        $process->wait();
        $result = trim($process->getOutput());

        if (!empty($result)) {
            return [ $result ];
        }

        return [];
    }

    public function searchlist(): array
    {
        $this->loadConfig();

        return $this->searchlist;
    }

    public function nameservers(): array
    {
        $this->loadConfig();

        return $this->nameservers;
    }
}
