<?php

namespace Util\Net;

class ServerSocket
{
    /**
     * Various states of this socket.
     */
    private bool $created = false;
    private bool $bound = false;
    private bool $closed = false;
    private $closeLock = null;

    /**
     * The implementation of this Socket.
     */
    private $impl;

    public function __construct(...$args)
    {
        if (empty($args)) {
            $this->setImpl();
        } elseif (count($args) == 1) {
            if ($args[0] instanceof SocketImpl) {
                $this->impl = $args[0];
                $this->impl->setServerSocket($this);
            } elseif (is_int($args[0])) {
                self::__construct($args[0], 50, null);
            }
        } elseif (count($args) == 2) {
            self::__construct($args[0], $args[1], null);
        } elseif (count($args) == 3) {
            $this->setImpl();
            if ($args[0] < 0 || $args[0] > 0xFFFF) {
                throw new \Exception("Port value out of range: " . $args[0]);
            }
            $backlog = $args[1];
            if ($backlog < 1) {
                $backlog = 50;
            }
            try {
                $this->bind(new InetSocketAddress($args[3], $args[0]), $backlog);
            } catch (\Throwable $e) {
                $this->close();
                throw $e;
            }
        }
    }

    /**
     * Get the {@code SocketImpl} attached to this socket, creating
     * it if necessary.
     *
     * @return  the {@code SocketImpl} attached to that ServerSocket.
     * @throws SocketException if creation fails.
     * @since 1.4
     */
    public function getImpl(): SocketImpl
    {
        if (!$this->created) {
            $this->createImpl();
        }
        return $this->impl;
    }

    private function setImpl(): void
    {
        $this->impl = new SocksSocketImpl();
        $this->impl->setServerSocket($this);
    }

    /**
     * Creates the socket implementation.
     *
     * @throws IOException if creation fails
     * @since 1.4
     */
    public function createImpl(): void
    {
        if ($this->impl == null) {
            $this->setImpl();
        }
        $this->impl->create(true);
        $this->created = true;
    }

    /**
     *
     * Binds the {@code ServerSocket} to a specific address
     * (IP address and port number).
     * <p>
     * If the address is {@code null}, then the system will pick up
     * an ephemeral port and a valid local address to bind the socket.
     * <P>
     * The {@code backlog} argument is the requested maximum number of
     * pending connections on the socket. Its exact semantics are implementation
     * specific. In particular, an implementation may impose a maximum length
     * or may choose to ignore the parameter altogther. The value provided
     * should be greater than {@code 0}. If it is less than or equal to
     * {@code 0}, then an implementation specific default will be used.
     * @param   endpoint        The IP address and port number to bind to.
     * @param   backlog         requested maximum length of the queue of
     *                          incoming connections.
     * @throws  IOException if the bind operation fails, or if the socket
     *                     is already bound.
     * @throws  SecurityException       if a {@code SecurityManager} is present and
     * its {@code checkListen} method doesn't allow the operation.
     * @throws  IllegalArgumentException if endpoint is a
     *          SocketAddress subclass not supported by this socket
     * @since 1.4
     */
    public function bind(?SocketAddress $endpoint = null, int $backlog = 50): void
    {
        if ($this->isClosed()) {
            throw new \Exception("Socket is closed");
        }
        if ($this->isBound()) {
            throw new \Exception("Already bound");
        }
        if ($endpoint == null) {
            $endpoint = new InetSocketAddress(0);
        }
        if (!($endpoint instanceof InetSocketAddress)) {
            throw new \Exception("Unsupported address type");
        }
        $epoint = $endpoint;
        if ($epoint->isUnresolved()) {
            throw new \Exception("Unresolved address");
        }
        if ($backlog < 1) {
            $backlog = 50;
        }
        try {
            $this->getImpl()->bind($epoint->getAddress(), $epoint->getPort());
            $this->getImpl()->listen($backlog);
            $this->bound = true;
        } catch(\Throwable $e) {
            $this->bound = false;
            throw $e;
        }
    }

    /**
     * Returns the local address of this server socket.
     * <p>
     * If the socket was bound prior to being {@link #close closed},
     * then this method will continue to return the local address
     * after the socket is closed.
     * <p>
     * If there is a security manager set, its {@code checkConnect} method is
     * called with the local address and {@code -1} as its arguments to see
     * if the operation is allowed. If the operation is not allowed,
     * the {@link InetAddress#getLoopbackAddress loopback} address is returned.
     *
     * @return  the address to which this socket is bound,
     *          or the loopback address if denied by the security manager,
     *          or {@code null} if the socket is unbound.
     *
     * @see SecurityManager#checkConnect
     */
    public function getInetAddress(): ?InetAddress
    {
        if (!$this->isBound())
            return null;
        try {
            $in = $this->getImpl()->getInetAddress();
            return $in;
        } catch (\Throwable $e) {
            if (!$this->isBound()) {
                return InetAddress::getLoopbackAddress();
            }            
        }
        return null;
    }

    /**
     * Returns the port number on which this socket is listening.
     * <p>
     * If the socket was bound prior to being {@link #close closed},
     * then this method will continue to return the port number
     * after the socket is closed.
     *
     * @return  the port number to which this socket is listening or
     *          -1 if the socket is not bound yet.
     */
    public function getLocalPort(): int
    {
        if (!$this->isBound()) {
            return -1;
        }
        try {
            return $this->getImpl()->getLocalPort();
        } catch (\Throwable $e) {
            // nothing
            // If we're bound, the impl has been created
            // so we shouldn't get here
        }
        return -1;
    }

    /**
     * Returns the address of the endpoint this socket is bound to.
     * <p>
     * If the socket was bound prior to being {@link #close closed},
     * then this method will continue to return the address of the endpoint
     * after the socket is closed.
     * <p>
     * If there is a security manager set, its {@code checkConnect} method is
     * called with the local address and {@code -1} as its arguments to see
     * if the operation is allowed. If the operation is not allowed,
     * a {@code SocketAddress} representing the
     * {@link InetAddress#getLoopbackAddress loopback} address and the local
     * port to which the socket is bound is returned.
     *
     * @return a {@code SocketAddress} representing the local endpoint of
     *         this socket, or a {@code SocketAddress} representing the
     *         loopback address if denied by the security manager,
     *         or {@code null} if the socket is not bound yet.
     *
     * @see #getInetAddress()
     * @see #getLocalPort()
     * @see #bind(SocketAddress)
     * @see SecurityManager#checkConnect
     * @since 1.4
     */

    public function getLocalSocketAddress(): SocketAddress
    {
        if (!$this->isBound()) {
            return null;
        }
        return new InetSocketAddress($this->getInetAddress(), $this->getLocalPort());
    }

    /**
     * Listens for a connection to be made to this socket and accepts
     * it. The method blocks until a connection is made.
     *
     * <p>A new Socket {@code s} is created and, if there
     * is a security manager,
     * the security manager's {@code checkAccept} method is called
     * with {@code s.getInetAddress().getHostAddress()} and
     * {@code s.getPort()}
     * as its arguments to ensure the operation is allowed.
     * This could result in a SecurityException.
     *
     * @exception  IOException  if an I/O error occurs when waiting for a
     *               connection.
     * @exception  SecurityException  if a security manager exists and its
     *             {@code checkAccept} method doesn't allow the operation.
     * @exception  SocketTimeoutException if a timeout was previously set with setSoTimeout and
     *             the timeout has been reached.
     * @exception  java.nio.channels.IllegalBlockingModeException
     *             if this socket has an associated channel, the channel is in
     *             non-blocking mode, and there is no connection ready to be
     *             accepted
     *
     * @return the new Socket
     * @see SecurityManager#checkAccept
     * @revised 1.4
     * @spec JSR-51
     */
    public function accept(): Socket
    {
        if ($this->isClosed()) {
            throw new \Exception("Socket is closed");
        }
        if (!$this->isBound()) {
            throw new \Exception("Socket is not bound yet");
        }
        $s = new Socket(null);
        $this->implAccept($s);
        return $s;
    }

    /**
     * Subclasses of ServerSocket use this method to override accept()
     * to return their own subclass of socket.  So a FooServerSocket
     * will typically hand this method an <i>empty</i> FooSocket.  On
     * return from implAccept the FooSocket will be connected to a client.
     *
     * @param s the Socket
     * @throws java.nio.channels.IllegalBlockingModeException
     *         if this socket has an associated channel,
     *         and the channel is in non-blocking mode
     * @throws IOException if an I/O error occurs when waiting
     * for a connection.
     * @since   JDK1.1
     * @revised 1.4
     * @spec JSR-51
     */
    protected function implAccept(Socket $s): void
    {
        $si = null;
        try {
            if ($s->impl == null) {
              $s->setImpl();
            } else {
                $s->impl->reset();
            }
            $si = $s->impl;
            $s->impl = null;
            $si->address = new InetAddress();
            $this->getImpl()->accept($si);
        } catch (\Throwable $e) {
            if ($si != null) {
                $si->reset();
            }
            $s->impl = $si;
            throw $e;
        }
        $s->impl = $si;
        $s->postAccept();
    }

    /**
     * Closes this socket.
     *
     * Any thread currently blocked in {@link #accept()} will throw
     * a {@link SocketException}.
     *
     * <p> If this socket has an associated channel then the channel is closed
     * as well.
     *
     * @exception  IOException  if an I/O error occurs when closing the socket.
     * @revised 1.4
     * @spec JSR-51
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
     * Returns the binding state of the ServerSocket.
     *
     * @return true if the ServerSocket successfully bound to an address
     * @since 1.4
     */
    public function isBound(): bool
    {
        return $this->bound;
    }

    /**
     * Returns the closed state of the ServerSocket.
     *
     * @return true if the socket has been closed
     * @since 1.4
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * Enable/disable {@link SocketOptions#SO_TIMEOUT SO_TIMEOUT} with the
     * specified timeout, in milliseconds.  With this option set to a non-zero
     * timeout, a call to accept() for this ServerSocket
     * will block for only this amount of time.  If the timeout expires,
     * a <B>java.net.SocketTimeoutException</B> is raised, though the
     * ServerSocket is still valid.  The option <B>must</B> be enabled
     * prior to entering the blocking operation to have effect.  The
     * timeout must be {@code > 0}.
     * A timeout of zero is interpreted as an infinite timeout.
     * @param timeout the specified timeout, in milliseconds
     * @exception SocketException if there is an error in
     * the underlying protocol, such as a TCP error.
     * @since   JDK1.1
     * @see #getSoTimeout()
     */
    public function setSoTimeout(int $timeout): void
    {
        if ($this->isClosed()) {
            throw new \Exception("Socket is closed");
        }
        $this->getImpl()->setSocketTimeout($timeout);
    }

    /**
     * Retrieve setting for {@link SocketOptions#SO_TIMEOUT SO_TIMEOUT}.
     * 0 returns implies that the option is disabled (i.e., timeout of infinity).
     * @return the {@link SocketOptions#SO_TIMEOUT SO_TIMEOUT} value
     * @exception IOException if an I/O error occurs
     * @since   JDK1.1
     * @see #setSoTimeout(int)
     */
    public function getSoTimeout(): int
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
     * Enabling {@link SocketOptions#SO_REUSEADDR SO_REUSEADDR} prior to
     * binding the socket using {@link #bind(SocketAddress)} allows the socket
     * to be bound even though a previous connection is in a timeout state.
     * <p>
     * When a {@code ServerSocket} is created the initial setting
     * of {@link SocketOptions#SO_REUSEADDR SO_REUSEADDR} is not defined.
     * Applications can use {@link #getReuseAddress()} to determine the initial
     * setting of {@link SocketOptions#SO_REUSEADDR SO_REUSEADDR}.
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
     * @see #isBound()
     * @see #isClosed()
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
     * Returns the implementation address and implementation port of
     * this socket as a {@code String}.
     * <p>
     * If there is a security manager set, its {@code checkConnect} method is
     * called with the local address and {@code -1} as its arguments to see
     * if the operation is allowed. If the operation is not allowed,
     * an {@code InetAddress} representing the
     * {@link InetAddress#getLoopbackAddress loopback} address is returned as
     * the implementation address.
     *
     * @return  a string representation of this socket.
     */
    public function __toString(): string
    {
        if (!$this->isBound()) {
            return "ServerSocket[unbound]";
        }
        $in = $this->impl->getInetAddress();
        return "ServerSocket[addr=" . $in . ",localport=" . $this->impl->getLocalPort()  . "]";
    }

    public function setBound(): void
    {
        $this->bound = true;
    }

    public function setCreated(): void
    {
        $this->created = true;
    }

    /**
     * The factory for all server sockets.
     */
    private static $factory = null;

    /**
     * Sets the server socket implementation factory for the
     * application. The factory can be specified only once.
     * <p>
     * When an application creates a new server socket, the socket
     * implementation factory's {@code createSocketImpl} method is
     * called to create the actual socket implementation.
     * <p>
     * Passing {@code null} to the method is a no-op unless the factory
     * was already set.
     * <p>
     * If there is a security manager, this method first calls
     * the security manager's {@code checkSetFactory} method
     * to ensure the operation is allowed.
     * This could result in a SecurityException.
     *
     * @param      fac   the desired factory.
     * @exception  IOException  if an I/O error occurs when setting the
     *               socket factory.
     * @exception  SocketException  if the factory has already been defined.
     * @exception  SecurityException  if a security manager exists and its
     *             {@code checkSetFactory} method doesn't allow the operation.
     * @see        java.net.SocketImplFactory#createSocketImpl()
     * @see        SecurityManager#checkSetFactory
     */
    public static function setSocketFactory(SocketImplFactoryInterface $fac): void
    {
        if (self::$factory != null) {
            throw new \Exception("factory already defined");
        }
        self::$factory = $fac;
    }

    /**
     * Sets a default proposed value for the
     * {@link SocketOptions#SO_RCVBUF SO_RCVBUF} option for sockets
     * accepted from this {@code ServerSocket}. The value actually set
     * in the accepted socket must be determined by calling
     * {@link Socket#getReceiveBufferSize()} after the socket
     * is returned by {@link #accept()}.
     * <p>
     * The value of {@link SocketOptions#SO_RCVBUF SO_RCVBUF} is used both to
     * set the size of the internal socket receive buffer, and to set the size
     * of the TCP receive window that is advertized to the remote peer.
     * <p>
     * It is possible to change the value subsequently, by calling
     * {@link Socket#setReceiveBufferSize(int)}. However, if the application
     * wishes to allow a receive window larger than 64K bytes, as defined by RFC1323
     * then the proposed value must be set in the ServerSocket <B>before</B>
     * it is bound to a local address. This implies, that the ServerSocket must be
     * created with the no-argument constructor, then setReceiveBufferSize() must
     * be called and lastly the ServerSocket is bound to an address by calling bind().
     * <p>
     * Failure to do this will not cause an error, and the buffer size may be set to the
     * requested value but the TCP receive window in sockets accepted from
     * this ServerSocket will be no larger than 64K bytes.
     *
     * @exception SocketException if there is an error
     * in the underlying protocol, such as a TCP error.
     *
     * @param size the size to which to set the receive buffer
     * size. This value must be greater than 0.
     *
     * @exception IllegalArgumentException if the
     * value is 0 or is negative.
     *
     * @since 1.4
     * @see #getReceiveBufferSize
     */
     public function setReceiveBufferSize (int $size): void
     {
        if (!($size > 0)) {
            throw new \Exception("negative receive size");
        }
        if ($this->isClosed()) {
            throw new \Exception("Socket is closed");
        }
        $this->getImpl()->setOption(SO_RCVBUF, $size);
    }

    /**
     * Gets the value of the {@link SocketOptions#SO_RCVBUF SO_RCVBUF} option
     * for this {@code ServerSocket}, that is the proposed buffer size that
     * will be used for Sockets accepted from this {@code ServerSocket}.
     *
     * <p>Note, the value actually set in the accepted socket is determined by
     * calling {@link Socket#getReceiveBufferSize()}.
     * @return the value of the {@link SocketOptions#SO_RCVBUF SO_RCVBUF}
     *         option for this {@code Socket}.
     * @exception SocketException if there is an error
     *            in the underlying protocol, such as a TCP error.
     * @see #setReceiveBufferSize(int)
     * @since 1.4
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
}