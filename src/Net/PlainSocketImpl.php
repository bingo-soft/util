<?php

namespace Util\Net;

class PlainSocketImpl extends AbstractPlainSocketImpl
{
    public function __construct($fd = null)
    {
        $this->fd = $fd;
    }

    public function socketCreate(bool $isServer)
    {
        if ($isServer) {
            return @socket_create(AF_UNIX, SOCK_STREAM, SOL_TCP);
        }
        return @socket_create(AF_UNIX, SOCK_DGRAM, SOL_UDP);
    }

    public function socketConnect(InetAddress $address, int $port, ?int $timeout = null): void
    {
        @socket_connect($this->fd, $address->getHostAddress(), $port);
        if ($timeout !== null) {
            $this->setSocketTimeout($timeout);
        }
    }

    public function socketBind(InetAddress $address, int $port): void
    {
        @socket_bind($this->fd, $address->getHostAddress(), $port);
    }

    public function socketListen(int $count = 0): void
    {
        @socket_listen($this->fd, $count);
    }

    public function socketAccept(SocketImpl $s): void
    {
        @socket_accept($s->getFileDescriptor());
    }

    public function socketAvailable(): int
    {
        $read = [ $this->fd ];
        $write = [ $this->fd ];
        $except = null;
        $ret = @socket_select($read, $write, $except, 0);
        if ($ret === false) {
            throw new \Exception(socket_strerror(socket_last_error()));
        }
        return $ret;
    }

    public function socketClose0(bool $useDeferredClose): void
    {
        try {
            @socket_close($this->fd);
        } catch (\Throwable $t) {
            //ignore
        }
    }

    public function setSocketTimeout(int $timeout)
    {
        $this->timeout = $timeout;
        @stream_set_timeout($this->fd, $timeout);
    }

    public function socketSetOption(int $opt, $value, int $level = SOL_SOCKET): void
    {
        @socket_set_option($this->fd, $level, $opt, $value);
    }

    public function socketGetOption(int $opt, int $level = SOL_SOCKET)
    {
        return @socket_get_option($this->fd, $level, $opt);
    }

    public function read(int $length, int $type = PHP_BINARY_READ): string
    {
        return @socket_read($this->fd, $length, $type);
    }

    public function receive(string &$buffer, int $length, int $flags): int
    {
        return @socket_recv($this->fd, $buffer, $length, $flags);
    }

    public function write(string $buffer, int $length = null)
    {
        if (null === $length) {
            $length = strlen($buffer);
        }

        do {
            $return = @socket_write($this->fd, $buffer, $length);

            if (false !== $return && $return < $length) {
                $buffer = substr($buffer, $return);
                $length -= $return;
            } else {
                break;
            }
        } while (true);

        return $return;
    }

    public function send(string $buffer, int $flags = 0, int $length = null)
    {
        if (null === $length) {
            $length = strlen($buffer);
        }

        do {
            $return = @socket_send($this->fd, $buffer, $length, $flags);

            if (false !== $return && $return < $length) {
                $buffer = substr($buffer, $return);
                $length -= $return;
            } else {
                break;
            }
        } while (true);

        return $return;
    }
}