<?php

namespace Util\Net;

class PlainSocketImpl extends AbstractPlainSocketImpl
{
    public function __construct($fd = null)
    {
        $this->fd = $fd;
    }

    public function socketCreate(bool $isServer, bool $stream, InetAddress $address, int $port)
    {
        if ($stream) {
            $uri = 'tcp://' . (($port !== -1) ? ($address->getHostAddress()  . ":" . $port) : $address->getHostAddress());
            if ($isServer) {
                $server = @stream_socket_server($uri);
                return $server;
            } else {
                $connector = new ExponentailBackoffSocketConnector(function () use ($uri) {
                    return @stream_socket_client($uri);
                });
                return $connector->connect();
            }
        } else {
            //Default Datagram socket
            $socket = @socket_create(AF_INET, SOCK_DGRAM, 0);
            return $socket;
        }
    }

    /*private function getStreamClient(string $uri)
    {
        return @stream_socket_client($uri);
    }*/

    public function socketConnect(InetAddress $address, int $port, ?int $timeout = null): void
    {
        if ($this->fd instanceof \Socket) {
            $connected = @socket_connect($this->fd, $address->getHostAddress(), $port);
            if ($timeout !== null) {
                $this->setSocketTimeout($timeout);
            }
        }
    }

    public function socketBind(InetAddress $address, int $port): void
    {
        //Server stream socket is already bound
        if ($this->fd instanceof \Socket) {
            @socket_bind($this->fd, $address->getHostAddress(), $port);
        }
    }

    public function socketListen(int $count = 0): void
    {
        //Server stream socket is already listening
        if ($this->fd instanceof \Socket) {
            @socket_listen($this->fd, $count);
        }
    }

    public function socketAccept(SocketImpl $s): void
    {
        if ($this->fd instanceof \Socket) {
            $client = @socket_accept($this->fd);
        } else {
            $client = @stream_socket_accept($this->fd);
        }
        $s->fd = $client;
    }

    public function socketAvailable(): int
    {
        $read = [ $this->fd ];
        $write = [ $this->fd ];
        $except = null;
        if ($this->fd instanceof \Socket) {
            $ret = @socket_select($read, $write, $except, 0);
        } else {
            $ret = @stream_select($read, $write, $except, 0);
        }
        if ($ret === false) {
            throw new \Exception(socket_strerror(socket_last_error()));
        }
        return $ret;
    }

    public function socketClose0(bool $useDeferredClose): void
    {
        try {
            if ($this->fd instanceof \Socket) {
                @socket_close($this->fd);
            } else {
                @stream_socket_shutdown($this->fd, STREAM_SHUT_WR);
            }
        } catch (\Throwable $t) {
            //ignore
        }
    }

    public function setSocketTimeout(int $timeout)
    {
        //$timeout is in milliseconds, convert to seconds
        $this->timeout = $timeout;
        if ($this->fd instanceof \Socket) {
            @socket_set_option($this->fd, SOL_SOCKET, SO_RCVTIMEO, ['sec' => round($timeout / 1000), 'usec' => round($timeout / 1000000)]);
            @socket_set_option($this->fd, SOL_SOCKET, SO_SNDTIMEO, ['sec' => round($timeout / 1000), 'usec' => round($timeout / 1000000)]);
        } else {
            @stream_set_timeout($this->fd, round($timeout / 1000));
        }
    }

    //@TODO. Implement options handling for stream sockets
    public function socketSetOption(int $opt, $value, int $level = SOL_SOCKET): void
    {
        if ($this->fd instanceof \Socket) {
            @socket_set_option($this->fd, $level, $opt, $value);
        }
    }

    //@TODO. Implement options handling for stream sockets
    public function socketGetOption(int $opt, int $level = SOL_SOCKET)
    {
        if ($this->fd instanceof \Socket) {
            return @socket_get_option($this->fd, $level, $opt);
        }
        return null;
    }

    //@TODO - make length optional to read all data from the socket
    public function read(int $length, int $type = PHP_BINARY_READ, int $nanos = 0)
    {
        if ($this->fd instanceof \Socket) {
            if ($nanos !== 0) {
                $sec = intdiv($nanos, 1000000000);
                $usec = intdiv(($nanos - ($sec * 1000000000)), 1000);
                socket_set_option($this->fd, SOL_SOCKET, SO_RCVTIMEO, [
                    "sec" => $sec, 
                    "usec" => $usec
                ]);
            }
            return @socket_read($this->fd, $length, $type);
        } elseif (is_resource($this->fd)) {
            if ($nanos !== 0) {
                $sec = intdiv($nanos, 1000000000);
                $usec = intdiv(($nanos - ($sec * 1000000000)), 1000);

                $read = [$this->fd];
                $write = null;
                $except = null;

                // Use stream_select() to wait for the stream to become available or timeout
                if (false === @stream_select($read, $write, $except, $sec, $usec)) {
                    // stream_select() failed
                    return false;
                }

                if (!empty($read)) {
                    // The stream is ready for reading
                    return @stream_socket_recvfrom($this->fd, $length);
                }

                return false;

            } else {
                return @stream_socket_recvfrom($this->fd, $length);
            }
        }
        return false;
    }

    public function receive(string &$buffer, int $length, int $flags): int
    {
        if ($this->fd instanceof \Socket) {
            return @socket_recv($this->fd, $buffer, $length, $flags);
        } else {
            return @stream_socket_recvfrom($this->fd, $length);
        }
    }

    public function write(string $buffer, int $length = null)
    {
        if (null === $length) {
            $length = strlen($buffer);
        }

        do {
            if ($this->fd instanceof \Socket) {
                $return = @socket_write($this->fd, $buffer, $length);
            } else {
                $return = @stream_socket_sendto($this->fd, $buffer);
            }
            if ($this->fd instanceof \Socket && false !== $return && $return < $length) {
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
            if ($this->fd instanceof \Socket) {
                $return = @socket_send($this->fd, $buffer, $length, $flags);
            } else {
                $return = @stream_socket_sendto($this->fd, $buffer);
            }
            if ($this->fd instanceof \Socket && false !== $return && $return < $length) {
                $buffer = substr($buffer, $return);
                $length -= $return;
            } else {
                break;
            }
        } while (true);

        return $return;
    }
}