<?php

namespace Util\Net;

abstract class SocketImpl implements SocketOptionsInterface
{
    /**
     * The actual Socket object.
     */
    public $socket = null;
    public $serverSocket = null;

    /**
     * The file descriptor object for this socket.
     */
    protected $fd;

    /**
     * The IP address of the remote end of this socket.
     */
    protected $address;

    /**
     * The port number on the remote host to which this socket is connected.
     */
    protected int $port = -1;

    /**
     * The local port number to which this socket is connected.
     */
    protected int $localport = -1;

    /**
     * Creates either a stream or a datagram socket.
     */
    abstract protected function create(...$args): void;

    /**
     * Binds this socket to the specified local IP address and port number.
     *
     * @param      host   an IP address that belongs to a local interface.
     * @param      port   the port number.
     * @exception  IOException  if an I/O error occurs when binding this socket.
     */
    abstract protected function bind(InetAddress $host, int $port): void;

    /**
     * Sets the maximum queue length for incoming connection indications
     * (a request to connect) to the {@code count} argument. If a
     * connection indication arrives when the queue is full, the
     * connection is refused.
     *
     * @param      backlog   the maximum length of the queue.
     * @exception  IOException  if an I/O error occurs when creating the queue.
     */
    abstract protected function listen(int $backlog): void;

    /**
     * Accepts a connection.
     *
     * @param      s   the accepted connection.
     * @exception  IOException  if an I/O error occurs when accepting the
     *               connection.
     */
    abstract protected function accept(SocketImpl $s): void;

    /**
     * Returns the number of bytes that can be read from this socket
     * without blocking.
     *
     * @return     the number of bytes that can be read from this socket
     *             without blocking.
     * @exception  IOException  if an I/O error occurs when determining the
     *               number of bytes available.
     */
    abstract protected function available(): int;

    /**
     * Closes this socket.
     *
     * @exception  IOException  if an I/O error occurs when closing this socket.
     */
    abstract protected function close(): void;

    /**
     * Places the input stream for this socket at "end of stream".
     * Any data sent to this socket is acknowledged and then
     * silently discarded.
     *
     * If you read from a socket input stream after invoking this method on the
     * socket, the stream's {@code available} method will return 0, and its
     * {@code read} methods will return {@code -1} (end of stream).
     *
     * @exception IOException if an I/O error occurs when shutting down this
     * socket.
     * @see java.net.Socket#shutdownOutput()
     * @see java.net.Socket#close()
     * @see java.net.Socket#setSoLinger(boolean, int)
     * @since 1.3
     */
    protected function shutdownInput(): void
    {
        throw new \Exception("Method not implemented!");
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
     * @see java.net.Socket#shutdownInput()
     * @see java.net.Socket#close()
     * @see java.net.Socket#setSoLinger(boolean, int)
     * @since 1.3
     */
    protected function shutdownOutput(): void
    {
        throw new \Exception("Method not implemented!");
    }

    /**
     * Returns the value of this socket's {@code fd} field.
     *
     * @return  the value of this socket's {@code fd} field.
     * @see     java.net.SocketImpl#fd
     */
    protected function getFileDescriptor()
    {
        return $this->fd;
    }

    /**
     * Returns the value of this socket's {@code address} field.
     *
     * @return  the value of this socket's {@code address} field.
     * @see     java.net.SocketImpl#address
     */
    protected function getInetAddress(): InetAddress
    {
        return $this->address;
    }

    /**
     * Returns the value of this socket's {@code port} field.
     *
     * @return  the value of this socket's {@code port} field.
     * @see     java.net.SocketImpl#port
     */
    protected function getPort(): int
    {
        return $this->port;
    }

    /**
     * Returns whether or not this SocketImpl supports sending
     * urgent data. By default, false is returned
     * unless the method is overridden in a sub-class
     *
     * @return  true if urgent data supported
     * @see     java.net.SocketImpl#address
     * @since 1.4
     */
    protected function supportsUrgentData (): bool
    {
        return false; // must be overridden in sub-class
    }

    /**
     * Send one byte of urgent data on the socket.
     * The byte to be sent is the low eight bits of the parameter
     * @param data The byte of data to send
     * @exception IOException if there is an error
     *  sending the data.
     * @since 1.4
     */
    abstract protected function sendUrgentData(int $data): void;

    /**
     * Returns the value of this socket's {@code localport} field.
     *
     * @return  the value of this socket's {@code localport} field.
     * @see     java.net.SocketImpl#localport
     */
    protected function getLocalPort(): int
    {
        return $this->localport;
    }

    public function setSocket(Socket $soc): void
    {
        $this->socket = $soc;
    }

    public function getSocket(): Socket
    {
        return $this->socket;
    }

    public function setServerSocket(ServerSocket $soc): void
    {
        $this->serverSocket = $soc;
    }

    public function getServerSocket(): ServerSocket
    {
        return $this->serverSocket;
    }

    /**
     * Returns the address and port of this socket as a {@code String}.
     *
     * @return  a string representation of this socket.
     */
    public function __toString(): string
    {
        return "Socket[addr=" . $this->getInetAddress() .
            ",port=" . $this->getPort() . ",localport=" . $this->getLocalPort() . "]";
    }

    public function reset(): void
    {
        $this->address = null;
        $this->port = -1;
        $this->localport = -1;
    }
}
