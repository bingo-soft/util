<?php

namespace Util\Net;

abstract class AbstractPlainSocketImpl extends SocketImpl
{
    /* instance variable for SO_TIMEOUT */
    public int $timeout = 0;   // timeout in millisec
    // traffic class
    private int $trafficClass = 0;

    /* number of threads using the FileDescriptor */
    protected int $fdUseCount = 0;

    /* lock when increment/decrementing fdUseCount */
    protected $fdLock = null;

    /* indicates a close is pending on the file descriptor */
    protected bool $closePending = false;

    /* indicates connection reset state */
    private const CONNECTION_NOT_RESET = 0;
    private const CONNECTION_RESET_PENDING = 1;
    private const CONNECTION_RESET = 2;
    private int $resetState = 0;
    private $resetLock = null;

   /* whether this Socket is a stream (TCP) socket or not (UDP)
    */
    protected bool $stream = false;

    /**
     * Creates a socket with a boolean that specifies whether this
     * is a stream socket (true) or an unconnected UDP socket (false).
     */
    public function create(bool $isServer, bool $stream, InetAddress $host, int $port): void
    {
        $this->stream = $stream;
        if ($isServer == false) {
            ResourceManager::beforeUdpCreate();
            try {
                $this->fd = $this->socketCreate(false, $stream, $host, $port);
            } catch (\Throwable $ioe) {
                ResourceManager::afterUdpClose();
                $this->fd = null;
                throw $ioe;
            }
        } else {
            //We are creating server socket that will listen for connections
            $this->stream = $stream;
            $this->fd = $this->socketCreate($isServer, $stream, $host, $port);
        }
        if ($this->socket != null) {
            $this->socket->setCreated();
        }
        if ($this->serverSocket != null) {
            $this->serverSocket->setCreated();
        }
    }

    protected function connect(...$args): void
    {
        if (is_string($args[0])) {
            $host = $args[0];
            $port = $args[1];

            $connected = false;
            try {
                $address = InetAddress::getByName($host);
                $this->port = $port;
                $this->address = $address;
    
                $this->connectToAddress($this->address, $this->port, $this->timeout);
                $connected = true;
            } finally {
                if (!$connected) {
                    try {
                        $this->close();
                    } catch (\Throwable $ioe) {
                        /* Do nothing. If connect threw an exception then
                           it will be passed up the call stack */
                    }
                }
            }
        } elseif ($args[0] instanceof InetAddress) {
            $address = $args[0];
            $port = $args[1];
            $this->port = $port;
            $this->address = $address;
    
            try {
                $this->connectToAddress($this->address, $this->port, $this->timeout);
                return;
            } catch (\Throwable $e) {
                // everything failed
                $this->close();
                throw $e;
            }
        } elseif ($args[0] instanceof SocketAddress) {
            $address = $args[0];
            $timeout = $args[1];
            $connected = false;
            try {
                $addr = $address;
                if ($addr->isUnresolved()) {
                    throw new \Exception("Unknown host: " . $addr->getHostName());
                }
                $this->port = $addr->getPort();
                $this->address = $addr->getAddress();

                $this->connectToAddress($this->address, $this->port, $this->timeout);
                $connected = true;
            } finally {
                if (!$connected) {
                    try {
                        $this->close();
                    } catch (\Throwable $ioe) {
                        /* Do nothing. If connect threw an exception then
                        it will be passed up the call stack */
                    }
                }
            }
        }
    }

    private function connectToAddress(InetAddress $address, int $port, int $timeout): void
    {
        if ($address->isAnyLocalAddress()) {
            $this->doConnect(InetAddress::getLocalHost(), $port, $timeout);
        } else {
            $this->doConnect($address, $port, $timeout);
        }
    }

    public function setOption(int $opt, $value, int $level = SOL_SOCKET): void
    {
        if ($this->isClosedOrPending()) {
            throw new \Exception("Socket Closed");
        }
        $this->socketSetOption($level, $opt, $val);
    }

    public function getOption(int $opt, int $level = SOL_SOCKET)
    {
        if ($this->isClosedOrPending()) {
            throw new \Exception("Socket Closed");
        }
        return $this->socketGetOption($level, $opt);
    }

    /**
     * The workhorse of the connection operation.  Tries several times to
     * establish a connection to the given <host, port>.  If unsuccessful,
     * throws an IOException indicating what went wrong.
     */
    public function doConnect(InetAddress $address, int $port, int $timeout): void
    {
        /*if (!closePending && (socket == null || !socket.isBound())) {
            NetHooks.beforeTcpConnect(fd, address, port);
        }*/
        try {
            $this->acquireFD();
            try {
                $this->socketConnect($address, $port, $timeout);
                /* socket may have been closed during poll/select */
                if ($this->closePending) {
                    throw new \Exception("Socket closed");
                }
                // If we have a ref. to the Socket, then sets the flags
                // created, bound & connected to true.
                // This is normally done in Socket.connect() but some
                // subclasses of Socket may call impl.connect() directly!
                if ($this->socket != null) {
                    $this->socket->setBound();
                    $this->socket->setConnected();
                }
            } finally {
                $this->releaseFD();
            }
        } catch (\Throwable $e) {
            $this->close();
            throw $e;
        }
    }

    /**
     * Binds the socket to the specified address of the specified local port.
     * @param address the address
     * @param lport the port
     */
    public function bind(InetAddress $address, int $lport): void
    {
        /*if (!closePending && (socket == null || !socket.isBound())) {
            NetHooks.beforeTcpBind(fd, address, lport);
        }*/
        $this->socketBind($address, $lport);
        if ($this->socket != null) {
            $this->socket->setBound();
        }
        if ($this->serverSocket != null) {
            $this->serverSocket->setBound();
        }
    }

    /**
     * Listens, for a specified amount of time, for connections.
     * @param count the amount of time to listen for connections
     */
    public function listen(int $count): void
    {
        $this->socketListen($count);
    }

    /**
     * Accepts connections.
     * @param s the connection
     */
    public function accept(SocketImpl $s): void
    {
        $this->acquireFD();
        try {
            $this->socketAccept($s);
        } finally {
            $this->releaseFD();
        }
    }

    public function getInputStream()
    {
        return $this->fd;
    }

    public function getOutputStream()
    {
        return $this->fd;
    }

    public function setFileDescriptor($fd): void
    {
        $this->fd = $fd;
    }

    public function setAddress(InetAddress $address): void
    {
        $this->address = $address;
    }

    public function setPort(int $port): void
    {
        $this->port = $port;
    }

    public function setLocalPort(int $localport): void
    {
        $this->localport = $localport;
    }

    /**
     * Returns the number of bytes that can be read without blocking.
     */
    protected function available(): int
    {
        if ($this->isClosedOrPending()) {
            throw new \Exception("Stream closed.");
        }

        /*
         * If connection has been reset or shut down for input, then return 0
         * to indicate there are no buffered bytes.
         */
        if ($this->isConnectionReset() || self::$shutRd) {
            return 0;
        }

        /*
         * If no bytes available and we were previously notified
         * of a connection reset then we move to the reset state.
         *
         * If are notified of a connection reset then check
         * again if there are bytes buffered on the socket.
         */
        $n = 0;
        try {
            $n = $this->socketAvailable();
            if ($n == 0 && $this->isConnectionResetPending()) {
                $this->setConnectionReset();
            }
        } catch (\Throwable $exc1) {
            $this->setConnectionResetPending();
            try {
                $n = $this->socketAvailable();
                if ($n == 0) {
                    $this->setConnectionReset();
                }
            } catch (\Throwable $exc2) {
            }
        }
        return $n;
    }

    /**
     * Closes the socket.
     */
    protected function close(): void
    {
        if ($this->fd != null) {
            if (!$this->stream) {
                ResourceManager::afterUdpClose();
            }
            if ($this->fdUseCount == 0) {
                if ($this->closePending) {
                    return;
                }
                $this->closePending = true;
                /*
                 * We close the FileDescriptor in two-steps - first the
                 * "pre-close" which closes the socket but doesn't
                 * release the underlying file descriptor. This operation
                 * may be lengthy due to untransmitted data and a long
                 * linger interval. Once the pre-close is done we do the
                 * actual socket to release the fd.
                 */
                try {
                    $this->socketPreClose();
                } finally {
                    $this->socketClose();
                }
                $this->fd = null;
                return;
            } else {
                /*
                 * If a thread has acquired the fd and a close
                 * isn't pending then use a deferred close.
                 * Also decrement fdUseCount to signal the last
                 * thread that releases the fd to close it.
                 */
                if (!$this->closePending) {
                    $this->closePending = true;
                    $this->fdUseCount -= 1;
                    $this->socketPreClose();
                }
            }
        }
    }

    public function reset(): void
    {
        if ($this->fd != null) {
            $this->socketClose();
        }
        $this->fd = null;
        parent::reset();
    }


    /**
     * Shutdown read-half of the socket connection;
     */
    /*protected function shutdownInput(): void
    {
        if ($this->fd != null) {
            $this->socketShutdown(self::$shutRd);
            self::$shutRd = true;
        }
    }*/

    /**
     * Shutdown write-half of the socket connection;
     */
    /*protected function shutdownOutput(): void
    {
        if ($this->fd != null) {
            $this->socketShutdown(self::$shutWr);
            self::$shutWr = true;
        }
    }*/

    protected function supportsUrgentData(): bool
    {
        return true;
    }

    protected function sendUrgentData(int $data): void
    {
        if ($this->fd == null) {
            throw new IOException("Socket Closed");
        }
        $this->socketSendUrgentData($data);
    }

    /**
     * Cleans up if the user forgets to close it.
     */
    protected function finalize(): void
    {
        $this->close();
    }

    /*
     * "Acquires" and returns the FileDescriptor for this impl
     *
     * A corresponding releaseFD is required to "release" the
     * FileDescriptor.
     */
    public function acquireFD()
    {
        $this->fdUseCount += 1;
        return $this->fd;
    }

    /*
     * "Release" the FileDescriptor for this impl.
     *
     * If the use count goes to -1 then the socket is closed.
     */
    public function releaseFD(): void
    {
        $this->fdUseCount--;
        if ($this->fdUseCount == -1) {
            if ($this->fd != null) {
                try {
                    $this->socketClose();
                } catch (\Throwable $e) {
                } finally {
                    $this->fd = null;
                }
            }
        }
    }

    public function isConnectionReset(): bool
    {
        return $this->resetState == self::CONNECTION_RESET;
    }

    public function isConnectionResetPending(): bool
    {
        return $this->resetState == self::CONNECTION_RESET_PENDING;
    }

    public function setConnectionReset(): void
    {
        $this->resetState = self::CONNECTION_RESET;
    }

    public function setConnectionResetPending(): void
    {
        if ($this->resetState == self::CONNECTION_NOT_RESET) {
            $this->resetState = self::CONNECTION_RESET_PENDING;
        }
    }

    /*
     * Return true if already closed or close is pending
     */
    public function isClosedOrPending(): bool
    {
        return $this->closePending || ($this->fd == null);
    }

    /*
     * Return the current value of SO_TIMEOUT
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /*
     * "Pre-close" a socket by dup'ing the file descriptor - this enables
     * the socket to be closed without releasing the file descriptor.
     */
    private function socketPreClose(): void
    {
        $this->socketClose0(true);
    }

    /*
     * Close the socket (and release the file descriptor).
     */
    protected function socketClose(): void
    {
        $this->socketClose0(false);
    }

    public function getSocketTimeout(): int
    {
        return $this->timeout;
    }

    public function isStream(): bool
    {
        return $this->stream;
    }

    abstract public function socketCreate(bool $isServer, bool $stream, InetAddress $address, int $port);
    abstract public function socketConnect(InetAddress $address, int $port, ?int $timeout = null): void;
    abstract public function socketBind(InetAddress $address, int $port): void;
    abstract public function socketListen(int $count = 0): void;
    abstract public function socketAccept(SocketImpl $s): void;
    abstract public function socketAvailable(): int;
    abstract public function socketClose0(bool $useDeferredClose): void;
    //abstract public function socketShutdown(int $howto): void;
    abstract public function socketSetOption(int $opt, $value, int $level = SOL_SOCKET): void;
    abstract public function socketGetOption(int $opt, int $level = SOL_SOCKET);
    abstract public function read(int $length, int $type = PHP_BINARY_READ, int $nanosTimeout = 0);
    abstract public function receive(string &$buffer, int $length, int $flags): int;
    abstract public function write(string $buffer, int $length = null);
    abstract public function send(string $buffer, int $flags = 0, int $length = null);

    //public static $shutRd = 0;
    //public static $shutWr = 1;
}