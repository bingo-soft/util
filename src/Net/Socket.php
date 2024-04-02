<?php

namespace Util\Net;

class Socket
{
    /**
     * Various states of this socket.
     */
    private bool $created = false;
    private bool $bound = false;
    private bool $connected = false;
    private bool $closed = false;
    private $closeLock;
    private bool $shutIn = false;
    private bool $shutOut = false;
    
    private $endpoint;

    /**
     * The implementation of this Socket.
     */
    public $impl;

    public function __construct(...$args) {
        if (empty($args)) {
            $this->setImpl();
        } elseif (count($args) == 1 && $args[0] instanceof Proxy) {
            $proxy = $args[0];
            $p = ($proxy == Proxy::noProxy()) ? Proxy::noProxy() : ApplicationProxy::create($proxy);
            $type = $p->type();
            if ($type == ProxyType::SOCKS || $type == ProxyType::HTTP) {
                $this->endpoint = $p->address();
                if ($this->endpoint->getAddress() !== null) {
                    $this->checkAddress($this->endpoint->getAddress(), "Socket");
                }
                $this->impl = ($type == ProxyType::SOCKS) ? new SocksSocketImpl($p) : new HttpConnectSocketImpl($p);
                $this->impl->setSocket($this);
                //@TODO - HttpConnectSocketImpl is yet to be implemented
            } else {
                if ($p == Proxy::noProxy()) {
                    if (self::$factory == null) {
                        $this->impl = new PlainSocketImpl();
                        $this->impl->setSocket($this);
                    } else {
                        $this->setImpl();
                    }
                } else {
                    throw new \Exception("Invalid Proxy");
                }
            }
        } elseif (count($args) == 1 && $args[0] instanceof SocketImpl) {
            $this->impl = $args[0];
            $this->impl->setSocket($this);
        } elseif (count($args) == 2) {
            $this->endpoint = new InetSocketAddress($args[0], $args[1]);
            self::__construct($this->endpoint, null, true);
        } elseif (count($args) == 3 && is_bool($args[2])) {
            $this->setImpl();
            try {
                $this->createImpl($args[2]);
                if ($args[1] != null) {
                    $this->bind($args[1]);
                }
                $this->connect($args[0]);
            } catch (\Throwable $e) {
                try {
                    $this->close();
                } catch (\Throwable $ce) {
                    //ignore
                }
                throw $e;
            }
        } elseif (count($args) == 4) {
            $this->endpoint = new InetSocketAddress($args[0], $args[1]);
            self::__construct($this->endpoint, new InetSocketAddress($args[3], $args[4]), true);
        }
    }

    /**
     * Creates the socket implementation.
     *
     * @param stream a {@code boolean} value : {@code true} for a TCP socket,
     *               {@code false} for UDP.
     * @throws IOException if creation fails
     * @since 1.4
     */
    public function createImpl(bool $stream): void
    {
        if ($this->impl == null) {
            $this->setImpl();
        }
        $this->impl->create(false, $stream, $this->endpoint->getAddress(), $this->endpoint->getPort());
        $this->created = true;
    }

    /**
     * Sets impl to the system-default type of SocketImpl.
     * @since 1.4
     */
    public function setImpl(): void
    {
        if (self::$factory != null) {
            $this->impl = self::$factory->createSocketImpl();
        } else {
            $this->impl = new SocksSocketImpl();
        }
        if ($this->impl != null) {
            $this->impl->setSocket($this);
        }
    }


    /**
     * Get the {@code SocketImpl} attached to this socket, creating
     * it if necessary.
     *
     * @return  the {@code SocketImpl} attached to that ServerSocket.
     * @throws SocketException if creation fails
     * @since 1.4
     */
    public function getImpl(): SocketImpl
    {
        if (!$this->created) {
            //client stream socket
            $this->createImpl(false, true);
        }
        return $this->impl;
    }

    /**
     * Connects this socket to the server with a specified timeout value.
     * A timeout of zero is interpreted as an infinite timeout. The connection
     * will then block until established or an error occurs.
     *
     * @param   endpoint the {@code SocketAddress}
     * @param   timeout  the timeout value to be used in milliseconds.
     * @throws  IOException if an error occurs during the connection
     * @throws  SocketTimeoutException if timeout expires before connecting
     * @throws  java.nio.channels.IllegalBlockingModeException
     *          if this socket has an associated channel,
     *          and the channel is in non-blocking mode
     * @throws  IllegalArgumentException if endpoint is null or is a
     *          SocketAddress subclass not supported by this socket
     * @since 1.4
     * @spec JSR-51
     */
    public function connect(?SocketAddress $endpoint = null, ?int $timeout = 0): void
    {
        if ($endpoint == null) {
            throw new \Exception("connect: The address can't be null");
        }

        if ($timeout < 0) {
            throw new \Exception("connect: timeout can't be negative");
        }

        if ($this->isClosed()) {
            throw new \Exception("Socket is closed");
        }

        if ($this->isConnected()) {
            throw new \Exception("already connected");
        }

        if (!($endpoint instanceof InetSocketAddress)) {
            throw new \Exception("Unsupported address type");
        }

        $epoint = $endpoint;
        $addr = $epoint->getAddress();
        $port = $epoint->getPort();
        $this->checkAddress($addr, "connect");

        if (!$this->created) {
            $this->createImpl(true, $addr, $port);
        }
        $this->impl->connect($epoint, $timeout);
        $this->connected = true;
        /*
         * If the socket was not bound before the connect, it is now because
         * the kernel will have picked an ephemeral port & a local address
         */
        $this->bound = true;
    }

    /**
     * Binds the socket to a local address.
     * <P>
     * If the address is {@code null}, then the system will pick up
     * an ephemeral port and a valid local address to bind the socket.
     *
     * @param   bindpoint the {@code SocketAddress} to bind to
     * @throws  IOException if the bind operation fails, or if the socket
     *                     is already bound.
     * @throws  IllegalArgumentException if bindpoint is a
     *          SocketAddress subclass not supported by this socket
     * @throws  SecurityException  if a security manager exists and its
     *          {@code checkListen} method doesn't allow the bind
     *          to the local port.
     *
     * @since   1.4
     * @see #isBound
     */
    public function bind(?SocketAddress $bindpoint = null): void
    {
        if ($this->isClosed()) {
            throw new \Exception("Socket is closed");
        }
        if ($this->isBound()) {
            throw new \Exception("Already bound");
        }

        if ($bindpoint != null && (!($bindpoint instanceof InetSocketAddress))) {
            throw new \Exception("Unsupported address type");
        }
        $epoint = $bindpoint;
        if ($epoint != null && $epoint->isUnresolved())
            throw new \Exception("Unresolved address");
        if ($epoint == null) {
            $epoint = new InetSocketAddress(0);
        }
        $addr = $epoint->getAddress();
        $port = $epoint->getPort();
        $this->checkAddress($addr, "bind");
        $this->getImpl()->bind($addr, $port);
        $this->bound = true;
    }

    private function checkAddress(?InetAddress $addr, string $op): void
    {
        if ($addr == null) {
            return;
        }
        if (!($addr instanceof Inet4Address || $addr instanceof Inet6Address)) {
            throw new \Exception($op . ": invalid address type");
        }
    }

    /**
     * set the flags after an accept() call.
     */
    public function postAccept(): void
    {
        $this->connected = true;
        $this->created = true;
        $this->bound = true;
    }

    public function setCreated(): void
    {
        $this->created = true;
    }

    public function setBound(): void
    {
        $this->bound = true;
    }

    public function setConnected(): void
    {
        $this->connected = true;
    }

    /**
     * Returns the address to which the socket is connected.
     * <p>
     * If the socket was connected prior to being {@link #close closed},
     * then this method will continue to return the connected address
     * after the socket is closed.
     *
     * @return  the remote IP address to which this socket is connected,
     *          or {@code null} if the socket is not connected.
     */
    public function getInetAddress(): ?InetAddress
    {
        if (!$this->isConnected()) {
            return null;
        }
        try {
            return $this->getImpl()->getInetAddress();
        } catch (\Throwable $e) {
        }
        return null;
    }

    /**
     * Gets the local address to which the socket is bound.
     * <p>
     * If there is a security manager set, its {@code checkConnect} method is
     * called with the local address and {@code -1} as its arguments to see
     * if the operation is allowed. If the operation is not allowed,
     * the {@link InetAddress#getLoopbackAddress loopback} address is returned.
     *
     * @return the local address to which the socket is bound,
     *         the loopback address if denied by the security manager, or
     *         the wildcard address if the socket is closed or not bound yet.
     * @since   JDK1.1
     *
     * @see SecurityManager#checkConnect
     */
    public function getLocalAddress(): InetAddress
    {
        if (!$this->isBound()) {
            return InetAddress::anyLocalAddress();
        }
        return InetAddress::getLoopbackAddress();;
    }

    /**
     * Returns the remote port number to which this socket is connected.
     * <p>
     * If the socket was connected prior to being {@link #close closed},
     * then this method will continue to return the connected port number
     * after the socket is closed.
     *
     * @return  the remote port number to which this socket is connected, or
     *          0 if the socket is not connected yet.
     */
    public function getPort(): int
    {
        if (!$this->isConnected()) {
            return 0;
        }
        try {
            return $this->getImpl()->getPort();
        } catch (\Throwable $e) {
            // Shouldn't happen as we're connected
        }
        return -1;
    }

    /**
     * Returns the local port number to which this socket is bound.
     * <p>
     * If the socket was bound prior to being {@link #close closed},
     * then this method will continue to return the local port number
     * after the socket is closed.
     *
     * @return  the local port number to which this socket is bound or -1
     *          if the socket is not bound yet.
     */
    public function getLocalPort(): int
    {
        if (!$this->isBound()) {
            return -1;
        }
        try {
            return $this->getImpl()->getLocalPort();
        } catch (\Throwable $e) {
            // shouldn't happen as we're bound
        }
        return -1;
    }

    /**
     * Returns the address of the endpoint this socket is connected to, or
     * {@code null} if it is unconnected.
     * <p>
     * If the socket was connected prior to being {@link #close closed},
     * then this method will continue to return the connected address
     * after the socket is closed.
     *

     * @return a {@code SocketAddress} representing the remote endpoint of this
     *         socket, or {@code null} if it is not connected yet.
     * @see #getInetAddress()
     * @see #getPort()
     * @see #connect(SocketAddress, int)
     * @see #connect(SocketAddress)
     * @since 1.4
     */
    public function getRemoteSocketAddress(): ?SocketAddress
    {
        if (!$this->isConnected()) {
            return null;
        }
        return new InetSocketAddress($this->getInetAddress(), $this->getPort());
    }

    /**
     * Returns the address of the endpoint this socket is bound to.
     * <p>
     * If a socket bound to an endpoint represented by an
     * {@code InetSocketAddress } is {@link #close closed},
     * then this method will continue to return an {@code InetSocketAddress}
     * after the socket is closed. In that case the returned
     * {@code InetSocketAddress}'s address is the
     * {@link InetAddress#isAnyLocalAddress wildcard} address
     * and its port is the local port that it was bound to.
     * <p>
     * If there is a security manager set, its {@code checkConnect} method is
     * called with the local address and {@code -1} as its arguments to see
     * if the operation is allowed. If the operation is not allowed,
     * a {@code SocketAddress} representing the
     * {@link InetAddress#getLoopbackAddress loopback} address and the local
     * port to which this socket is bound is returned.
     *
     * @return a {@code SocketAddress} representing the local endpoint of
     *         this socket, or a {@code SocketAddress} representing the
     *         loopback address if denied by the security manager, or
     *         {@code null} if the socket is not bound yet.
     *
     * @see #getLocalAddress()
     * @see #getLocalPort()
     * @see #bind(SocketAddress)
     * @see SecurityManager#checkConnect
     * @since 1.4
     */

    public function getLocalSocketAddress(): ?SocketAddress
    {
        if (!$this->isBound()) {
            return null;
        }
        return new InetSocketAddress($this->getLocalAddress(), $this->getLocalPort());
    }

    /**
     * Enable/disable {@link SocketOptions#TCP_NODELAY TCP_NODELAY}
     * (disable/enable Nagle's algorithm).
     *
     * @param on {@code true} to enable TCP_NODELAY,
     * {@code false} to disable.
     *
     * @exception SocketException if there is an error
     * in the underlying protocol, such as a TCP error.
     *
     * @since   JDK1.1
     *
     * @see #getTcpNoDelay()
     */
    public function setTcpNoDelay(bool $on): void
    {
        if ($this->isClosed()) {
            throw new \Exception("Socket is closed");
        }
        $this->getImpl()->setOption(TCP_NODELAY, $on);
    }

    /**
     * Tests if {@link SocketOptions#TCP_NODELAY TCP_NODELAY} is enabled.
     *
     * @return a {@code boolean} indicating whether or not
     *         {@link SocketOptions#TCP_NODELAY TCP_NODELAY} is enabled.
     * @exception SocketException if there is an error
     * in the underlying protocol, such as a TCP error.
     * @since   JDK1.1
     * @see #setTcpNoDelay(boolean)
     */
    public function getTcpNoDelay(): bool
    {
        if ($this->isClosed()) {
            throw new \Exception("Socket is closed");
        }
        return boolval($this->getImpl()->getOption(TCP_NODELAY));
    }

    /**
     * Enable/disable {@link SocketOptions#SO_LINGER SO_LINGER} with the
     * specified linger time in seconds. The maximum timeout value is platform
     * specific.
     *
     * The setting only affects socket close.
     *
     * @param on     whether or not to linger on.
     * @param linger how long to linger for, if on is true.
     * @exception SocketException if there is an error
     * in the underlying protocol, such as a TCP error.
     * @exception IllegalArgumentException if the linger value is negative.
     * @since JDK1.1
     * @see #getSoLinger()
     */
    public function setSoLinger(bool $on, int $linger): void
    {
        if ($this->isClosed()) {
            throw new \Exception("Socket is closed");
        }
        if (!$on) {
            $this->getImpl()->setOption(SO_LINGER, $on);
        } else {
            if ($linger < 0) {
                throw new \Exception("invalid value for SO_LINGER");
            }
            if ($linger > 65535) {
                $linger = 65535;
            }
            $this->getImpl()->setOption(SO_LINGER, $linger);
        }
    }

    /**
     * Returns setting for {@link SocketOptions#SO_LINGER SO_LINGER}.
     * -1 returns implies that the
     * option is disabled.
     *
     * The setting only affects socket close.
     *
     * @return the setting for {@link SocketOptions#SO_LINGER SO_LINGER}.
     * @exception SocketException if there is an error
     * in the underlying protocol, such as a TCP error.
     * @since   JDK1.1
     * @see #setSoLinger(boolean, int)
     */
    public function getSoLinger(): int
    {
        if ($this->isClosed()) {
            throw new \Exception("Socket is closed");
        }
        $o = $this->getImpl()->getOption(SO_LINGER);
        if (is_int($o) || is_numeric($o)) {
            return intval($o);
        } else {
            return -1;
        }
    }

    /**
     * Send one byte of urgent data on the socket. The byte to be sent is the lowest eight
     * bits of the data parameter. The urgent byte is
     * sent after any preceding writes to the socket OutputStream
     * and before any future writes to the OutputStream.
     * @param data The byte of data to send
     * @exception IOException if there is an error
     *  sending the data.
     * @since 1.4
     */
    public function sendUrgentData (int $data): void
    {
        if (!$this->getImpl()->supportsUrgentData()) {
            throw new \Exception ("Urgent data not supported");
        }
        $this->getImpl()->sendUrgentData($data);
    }

    /**
     * Enable/disable {@link SocketOptions#SO_OOBINLINE SO_OOBINLINE}
     * (receipt of TCP urgent data)
     *
     * By default, this option is disabled and TCP urgent data received on a
     * socket is silently discarded. If the user wishes to receive urgent data, then
     * this option must be enabled. When enabled, urgent data is received
     * inline with normal data.
     * <p>
     * Note, only limited support is provided for handling incoming urgent
     * data. In particular, no notification of incoming urgent data is provided
     * and there is no capability to distinguish between normal data and urgent
     * data unless provided by a higher level protocol.
     *
     * @param on {@code true} to enable
     *           {@link SocketOptions#SO_OOBINLINE SO_OOBINLINE},
     *           {@code false} to disable.
     *
     * @exception SocketException if there is an error
     * in the underlying protocol, such as a TCP error.
     *
     * @since   1.4
     *
     * @see #getOOBInline()
     */
    public function setOOBInline(bool $on): void
    {
        if ($this->isClosed()) {
            throw new \Exception("Socket is closed");
        }
        $this->getImpl()->setOption(SO_OOBINLINE, $on);
    }

    /**
     * Tests if {@link SocketOptions#SO_OOBINLINE SO_OOBINLINE} is enabled.
     *
     * @return a {@code boolean} indicating whether or not
     *         {@link SocketOptions#SO_OOBINLINE SO_OOBINLINE}is enabled.
     *
     * @exception SocketException if there is an error
     * in the underlying protocol, such as a TCP error.
     * @since   1.4
     * @see #setOOBInline(boolean)
     */
    public function getOOBInline(): bool
    {
        if ($this->isClosed()) {
            throw new \Exception("Socket is closed");
        }
        return boolval($this->getImpl()->getOption(SO_OOBINLINE));
    }

    /**
     *  Enable/disable {@link SocketOptions#SO_TIMEOUT SO_TIMEOUT}
     *  with the specified timeout, in milliseconds. With this option set
     *  to a non-zero timeout, a read() call on the InputStream associated with
     *  this Socket will block for only this amount of time.  If the timeout
     *  expires, a <B>java.net.SocketTimeoutException</B> is raised, though the
     *  Socket is still valid. The option <B>must</B> be enabled
     *  prior to entering the blocking operation to have effect. The
     *  timeout must be {@code > 0}.
     *  A timeout of zero is interpreted as an infinite timeout.
     *
     * @param timeout the specified timeout, in milliseconds.
     * @exception SocketException if there is an error
     * in the underlying protocol, such as a TCP error.
     * @since   JDK 1.1
     * @see #getSoTimeout()
     */
    public function setSoTimeout(int $timeout): void
    {
        if ($this->isClosed()) {
            throw new \Exception("Socket is closed");
        }
        if ($timeout < 0) {
            throw new \Exception("timeout can't be negative");
        }

        $this->getImpl()->setSocketTimeout($timeout);
    }

    /**
     * Returns setting for {@link SocketOptions#SO_TIMEOUT SO_TIMEOUT}.
     * 0 returns implies that the option is disabled (i.e., timeout of infinity).
     *
     * @return the setting for {@link SocketOptions#SO_TIMEOUT SO_TIMEOUT}
     * @exception SocketException if there is an error
     * in the underlying protocol, such as a TCP error.
     *
     * @since   JDK1.1
     * @see #setSoTimeout(int)
     */
    public function getSoTimeout():int
    {
        if ($this->isClosed()) {
            throw new \Exception("Socket is closed");
        }
        $o = $this->getImpl()->getSocketTimeout();
        if (is_int($o) || is_numeric($o)) {
            return intval($o);
        } else {
            return 0;
        }
    }

    /**
     * Sets the {@link SocketOptions#SO_SNDBUF SO_SNDBUF} option to the
     * specified value for this {@code Socket}.
     * The {@link SocketOptions#SO_SNDBUF SO_SNDBUF} option is used by the
     * platform's networking code as a hint for the size to set the underlying
     * network I/O buffers.
     *
     * <p>Because {@link SocketOptions#SO_SNDBUF SO_SNDBUF} is a hint,
     * applications that want to verify what size the buffers were set to
     * should call {@link #getSendBufferSize()}.
     *
     * @exception SocketException if there is an error
     * in the underlying protocol, such as a TCP error.
     *
     * @param size the size to which to set the send buffer
     * size. This value must be greater than 0.
     *
     * @exception IllegalArgumentException if the
     * value is 0 or is negative.
     *
     * @see #getSendBufferSize()
     * @since 1.2
     */
    public function setSendBufferSize(int $size): void
    {
        if (!($size > 0)) {
            throw new \Exception("negative send size");
        }
        if ($this->isClosed()) {
            throw new \Exception("Socket is closed");
        }
        $this->getImpl()->setOption(SO_SNDBUF, $size);
    }

    /**
     * Get value of the {@link SocketOptions#SO_SNDBUF SO_SNDBUF} option
     * for this {@code Socket}, that is the buffer size used by the platform
     * for output on this {@code Socket}.
     * @return the value of the {@link SocketOptions#SO_SNDBUF SO_SNDBUF}
     *         option for this {@code Socket}.
     *
     * @exception SocketException if there is an error
     * in the underlying protocol, such as a TCP error.
     *
     * @see #setSendBufferSize(int)
     * @since 1.2
     */
    public function getSendBufferSize(): int
    {
        if ($this->isClosed()) {
            throw new \Exception("Socket is closed");
        }
        $result = 0;
        $o = $this->getImpl()->getOption(SO_SNDBUF);
        if (is_int($o) || is_numeric($o)) {
            $result = intval($o);
        }
        return $result;
    }

    /**
     * Sets the {@link SocketOptions#SO_RCVBUF SO_RCVBUF} option to the
     * specified value for this {@code Socket}. The
     * {@link SocketOptions#SO_RCVBUF SO_RCVBUF} option is
     * used by the platform's networking code as a hint for the size to set
     * the underlying network I/O buffers.
     *
     * <p>Increasing the receive buffer size can increase the performance of
     * network I/O for high-volume connection, while decreasing it can
     * help reduce the backlog of incoming data.
     *
     * <p>Because {@link SocketOptions#SO_RCVBUF SO_RCVBUF} is a hint,
     * applications that want to verify what size the buffers were set to
     * should call {@link #getReceiveBufferSize()}.
     *
     * <p>The value of {@link SocketOptions#SO_RCVBUF SO_RCVBUF} is also used
     * to set the TCP receive window that is advertized to the remote peer.
     * Generally, the window size can be modified at any time when a socket is
     * connected. However, if a receive window larger than 64K is required then
     * this must be requested <B>before</B> the socket is connected to the
     * remote peer. There are two cases to be aware of:
     * <ol>
     * <li>For sockets accepted from a ServerSocket, this must be done by calling
     * {@link ServerSocket#setReceiveBufferSize(int)} before the ServerSocket
     * is bound to a local address.<p></li>
     * <li>For client sockets, setReceiveBufferSize() must be called before
     * connecting the socket to its remote peer.</li></ol>
     * @param size the size to which to set the receive buffer
     * size. This value must be greater than 0.
     *
     * @exception IllegalArgumentException if the value is 0 or is
     * negative.
     *
     * @exception SocketException if there is an error
     * in the underlying protocol, such as a TCP error.
     *
     * @see #getReceiveBufferSize()
     * @see ServerSocket#setReceiveBufferSize(int)
     * @since 1.2
     */
    public function setReceiveBufferSize(int $size): void
    {
        if ($size <= 0) {
            throw new \Exception("invalid receive size");
        }
        if ($this->isClosed()) {
            throw new \Exception("Socket is closed");
        }
        $this->getImpl()->setOption(SO_RCVBUF, $size);
    }

    /**
     * Gets the value of the {@link SocketOptions#SO_RCVBUF SO_RCVBUF} option
     * for this {@code Socket}, that is the buffer size used by the platform
     * for input on this {@code Socket}.
     *
     * @return the value of the {@link SocketOptions#SO_RCVBUF SO_RCVBUF}
     *         option for this {@code Socket}.
     * @exception SocketException if there is an error
     * in the underlying protocol, such as a TCP error.
     * @see #setReceiveBufferSize(int)
     * @since 1.2
     */
    public function getReceiveBufferSize(): int
    {
        if ($this->isClosed()) {
            throw new \Exception("Socket is closed");
        }
        $result = 0;
        $o = $this->getImpl()->getOption(SO_RCVBUF);
        if (is_int($o) || is_numeric($o)) {
            $result = intval($o);
        }
        return $result;
    }

    /**
     * Enable/disable {@link SocketOptions#SO_KEEPALIVE SO_KEEPALIVE}.
     *
     * @param on  whether or not to have socket keep alive turned on.
     * @exception SocketException if there is an error
     * in the underlying protocol, such as a TCP error.
     * @since 1.3
     * @see #getKeepAlive()
     */
    public function setKeepAlive(bool $on): void
    {
        if ($this->isClosed()) {
            throw new \Exception("Socket is closed");
        }
        $this->getImpl()->setOption(SO_KEEPALIVE, $on);
    }

    /**
     * Tests if {@link SocketOptions#SO_KEEPALIVE SO_KEEPALIVE} is enabled.
     *
     * @return a {@code boolean} indicating whether or not
     *         {@link SocketOptions#SO_KEEPALIVE SO_KEEPALIVE} is enabled.
     * @exception SocketException if there is an error
     * in the underlying protocol, such as a TCP error.
     * @since   1.3
     * @see #setKeepAlive(boolean)
     */
    public function getKeepAlive(): bool
    {
        if ($this->isClosed()) {
            throw new \Exception("Socket is closed");
        }
        return boolval($this->getImpl()->getOption(SO_KEEPALIVE));
    }

    /**
     * Enable/disable the {@link SocketOptions#SO_REUSEADDR SO_REUSEADDR}
     * socket option.
     * <p>
     * When a TCP connection is closed the connection may remain
     * in a timeout state for a period of time after the connection
     * is closed (typically known as the {@code TIME_WAIT} state
     * or {@code 2MSL} wait state).
     * For applications using a well known socket address or port
     * it may not be possible to bind a socket to the required
     * {@code SocketAddress} if there is a connection in the
     * timeout state involving the socket address or port.
     * <p>
     * Enabling {@link SocketOptions#SO_REUSEADDR SO_REUSEADDR}
     * prior to binding the socket using {@link #bind(SocketAddress)} allows
     * the socket to be bound even though a previous connection is in a timeout
     * state.
     * <p>
     * When a {@code Socket} is created the initial setting
     * of {@link SocketOptions#SO_REUSEADDR SO_REUSEADDR} is disabled.
     * <p>
     * The behaviour when {@link SocketOptions#SO_REUSEADDR SO_REUSEADDR} is
     * enabled or disabled after a socket is bound (See {@link #isBound()})
     * is not defined.
     *
     * @param on  whether to enable or disable the socket option
     * @exception SocketException if an error occurs enabling or
     *            disabling the {@link SocketOptions#SO_REUSEADDR SO_REUSEADDR}
     *            socket option, or the socket is closed.
     * @since 1.4
     * @see #getReuseAddress()
     * @see #bind(SocketAddress)
     * @see #isClosed()
     * @see #isBound()
     */
    public function setReuseAddress(bool $on): void
    {
        if ($this->isClosed()) {
            throw new \Exception("Socket is closed");
        }
        $this->getImpl()->setOption(SO_REUSEADDR, $on);
    }

    /**
     * Tests if {@link SocketOptions#SO_REUSEADDR SO_REUSEADDR} is enabled.
     *
     * @return a {@code boolean} indicating whether or not
     *         {@link SocketOptions#SO_REUSEADDR SO_REUSEADDR} is enabled.
     * @exception SocketException if there is an error
     * in the underlying protocol, such as a TCP error.
     * @since   1.4
     * @see #setReuseAddress(boolean)
     */
    public function getReuseAddress(): bool
    {
        if ($this->isClosed()) {
            throw new \Exception("Socket is closed");
        }
        return boolval($this->getImpl()->getOption(SO_REUSEADDR));
    }

    /**
     * Closes this socket.
     * <p>
     * Any thread currently blocked in an I/O operation upon this socket
     * will throw a {@link SocketException}.
     * <p>
     * Once a socket has been closed, it is not available for further networking
     * use (i.e. can't be reconnected or rebound). A new socket needs to be
     * created.
     *
     * <p> Closing this socket will also close the socket's
     * {@link java.io.InputStream InputStream} and
     * {@link java.io.OutputStream OutputStream}.
     *
     * <p> If this socket has an associated channel then the channel is closed
     * as well.
     *
     * @exception  IOException  if an I/O error occurs when closing this socket.
     * @revised 1.4
     * @spec JSR-51
     * @see #isClosed
     */
    public function close(): void
    {
        if ($this->isClosed()) {
            return;
        }
        if ($this->created) {
            $this->impl->close();
        }
        $this->closed = true;
    }

    /**
     * Places the input stream for this socket at "end of stream".
     * Any data sent to the input stream side of the socket is acknowledged
     * and then silently discarded.
     * <p>
     * If you read from a socket input stream after invoking this method on the
     * socket, the stream's {@code available} method will return 0, and its
     * {@code read} methods will return {@code -1} (end of stream).
     *
     * @exception IOException if an I/O error occurs when shutting down this
     * socket.
     *
     * @since 1.3
     * @see java.net.Socket#shutdownOutput()
     * @see java.net.Socket#close()
     * @see java.net.Socket#setSoLinger(boolean, int)
     * @see #isInputShutdown
     */
    public function shutdownInput(): void
    {
        if ($this->isClosed()) {
            throw new \Exception("Socket is closed");
        }
        if (!$this->isConnected()) {
            throw new \Exception("Socket is not connected");
        }
        if ($this->isInputShutdown()) {
            throw new \Exception("Socket input is already shutdown");
        }
        $this->getImpl()->shutdownInput();
        $this->shutIn = true;
    }

    /**
     * Disables the output stream for this socket.
     * For a TCP socket, any previously written data will be sent
     * followed by TCP's normal connection termination sequence.
     *
     * If you write to a socket output stream after invoking
     * shutdownOutput() on the socket, the stream will throw
     * an IOException.
     *
     * @exception IOException if an I/O error occurs when shutting down this
     * socket.
     *
     * @since 1.3
     * @see java.net.Socket#shutdownInput()
     * @see java.net.Socket#close()
     * @see java.net.Socket#setSoLinger(boolean, int)
     * @see #isOutputShutdown
     */
    public function shutdownOutput(): void
    {
        if ($this->isClosed()) {
            throw new \Exception("Socket is closed");
        }
        if (!$this->isConnected()) {
            throw new \Exception("Socket is not connected");
        }
        if ($this->isOutputShutdown()) {
            throw new \Exception("Socket output is already shutdown");
        }
        $this->getImpl()->shutdownOutput();
        $this->shutOut = true;
    }

    /**
     * Converts this socket to a {@code String}.
     *
     * @return  a string representation of this socket.
     */
    public function __toString(): string
    {
        try {
            if ($this->isConnected()) {
                return "Socket[addr=" . $this->getImpl()->getInetAddress() .
                    ",port=" . $this->getImpl()->getPort() .
                    ",localport=" . $this->getImpl()->getLocalPort() . "]";
            }
        } catch (\Throwable $e) {
        }
        return "Socket[unconnected]";
    }

    /**
     * Returns the connection state of the socket.
     * <p>
     * Note: Closing a socket doesn't clear its connection state, which means
     * this method will return {@code true} for a closed socket
     * (see {@link #isClosed()}) if it was successfuly connected prior
     * to being closed.
     *
     * @return true if the socket was successfuly connected to a server
     * @since 1.4
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Returns the binding state of the socket.
     * <p>
     * Note: Closing a socket doesn't clear its binding state, which means
     * this method will return {@code true} for a closed socket
     * (see {@link #isClosed()}) if it was successfuly bound prior
     * to being closed.
     *
     * @return true if the socket was successfuly bound to an address
     * @since 1.4
     * @see #bind
     */
    public function isBound(): bool
    {
        return $this->bound;
    }

    /**
     * Returns the closed state of the socket.
     *
     * @return true if the socket has been closed
     * @since 1.4
     * @see #close
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * Returns whether the read-half of the socket connection is closed.
     *
     * @return true if the input of the socket has been shutdown
     * @since 1.4
     * @see #shutdownInput
     */
    public function isInputShutdown(): bool
    {
        return $this->shutIn;
    }

    /**
     * Returns whether the write-half of the socket connection is closed.
     *
     * @return true if the output of the socket has been shutdown
     * @since 1.4
     * @see #shutdownOutput
     */
    public function isOutputShutdown(): bool
    {
        return $this->shutOut;
    }

    /**
     * The factory for all client sockets.
     */
    private static $factory = null;

    /**
     * Sets the client socket implementation factory for the
     * application. The factory can be specified only once.
     * <p>
     * When an application creates a new client socket, the socket
     * implementation factory's {@code createSocketImpl} method is
     * called to create the actual socket implementation.
     * <p>
     * Passing {@code null} to the method is a no-op unless the factory
     * was already set.
     * <p>If there is a security manager, this method first calls
     * the security manager's {@code checkSetFactory} method
     * to ensure the operation is allowed.
     * This could result in a SecurityException.
     *
     * @param      fac   the desired factory.
     * @exception  IOException  if an I/O error occurs when setting the
     *               socket factory.
     * @exception  SocketException  if the factory is already defined.
     * @exception  SecurityException  if a security manager exists and its
     *             {@code checkSetFactory} method doesn't allow the operation.
     * @see        java.net.SocketImplFactory#createSocketImpl()
     * @see        SecurityManager#checkSetFactory
     */
    public static function setSocketImplFactory(SocketImplFactoryInterface $fac): void
    {
        if (self::$factory != null) {
            throw new \Exception("factory already defined");
        }
        self::$factory = $fac;
    }

    public function write(string $buffer, int $length = null)
    {
        $this->impl->write($buffer, $length);
    }

    public function read(int $length, int $type = PHP_BINARY_READ, int $nanosTimeout = 0)
    {
        return $this->impl->read($length, $type, $nanosTimeout);
    }
}