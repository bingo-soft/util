<?php

namespace Util\Net;

use Util\Net\Url\Uri;

class SocksSocketImpl extends PlainSocketImpl implements SocksConstsInterface
{
    private $server = null;
    private $serverPort = self::DEFAULT_PORT;
    private $externalAddress;
    private $useV4 = false;
    private $cmdsock = null;
    /* true if the Proxy has been set programatically */
    private bool $applicationSetProxy = false;
    private static $props = [];

    public function __construct(...$args) {
        if (!empty($args)) {
            if (is_string($args[0])) {
                $this->server = $args[0];
                if (count($args) == 2) {
                    $this->serverPort = $args[1] == -1 ? self::DEFAULT_PORT : $args[1];
                } else {
                    $this->serverPort = self::DEFAULT_PORT;
                }
            } elseif ($args[0] instanceof Proxy) {
                $a = $args[0]->address();
                if ($a instanceof InetSocketAddress) {
                    // Use getHostString() to avoid reverse lookups
                    $this->server = $ad->getHostString();
                    $this->serverPort = $ad->getPort();
                }
            }
        }
        self::init();
    }

    private function init(): void
    {
        if (empty(self::$props)) {
            $net = $this->readPropertiesFromFile(self::NETWORK_RESOURCE_FILE_NAME);
            self::$props = array_merge(getenv(), $net);
        }
    }

    public function setV4(): void
    {
        $this->useV4 = true;
    }

    private function privilegedConnect(string $host, int $port, int $timeout): void
    {
        $this->superConnectServer($host, $port, $timeout);
    }

    private function superConnectServer(string $host, int $port, int $timeout): void
    {
        parent::connect(new InetSocketAddress($host, $port), $timeout);
    }

    private static function remainingMillis(int $deadlineMillis): int
    {
        if ($deadlineMillis == 0) {
            return 0;
        }

        $remaining = $deadlineMillis - round(microtime(true)) / 1000;
        if ($remaining > 0) {
            return $remaining;
        }

        throw new \Exception("Socket timeout");
    }

    private function readSocksReply(&$data, int $len, ?int $deadlineMillis = null): int
    {        
        $received = 0;
        for ($attempts = 0; $received < $len && $attempts < 3; $attempts += 1) {
            try {
                $this->read($len - $received);
            } catch (\Exception $e) {
                throw new SocketTimeoutException("Connect timed out");
            }
            usleep(min(round(remainingMillis($deadlineMillis) / 1000), 1000000));
            $received += $count;            
        }
        return $received;
    }

    /*
    private boolean authenticate(byte method, InputStream in,
                                 BufferedOutputStream out) throws IOException {
        return authenticate(method, in, out, 0L);
    }

    private boolean authenticate(byte method, InputStream in,
                                 BufferedOutputStream out,
                                 long deadlineMillis) throws IOException {
        // No Authentication required. We're done then!
        if (method == NO_AUTH)
            return true;
        if (method == USER_PASSW) {
            String userName;
            String password = null;
            final InetAddress addr = InetAddress.getByName(server);
            PasswordAuthentication pw =
                java.security.AccessController.doPrivileged(
                    new java.security.PrivilegedAction<PasswordAuthentication>() {
                        public PasswordAuthentication run() {
                                return Authenticator.requestPasswordAuthentication(
                                       server, addr, serverPort, "SOCKS5", "SOCKS authentication", null);
                            }
                        });
            if (pw !== null) {
                userName = pw.getUserName();
                password = new String(pw.getPassword());
            } else {
                userName = java.security.AccessController.doPrivileged(
                        new sun.security.action.GetPropertyAction("user.name"));
            }
            if (userName == null)
                return false;
            $this->write(1);
            $this->write(userName.length());
            try {
                $this->write(userName.getBytes("ISO-8859-1"));
            } catch (java.io.UnsupportedEncodingException uee) {
                assert false;
            }
            if (password !== null) {
                $this->write(password.length());
                try {
                    $this->write(password.getBytes("ISO-8859-1"));
                } catch (java.io.UnsupportedEncodingException uee) {
                    assert false;
                }
            } else
                $this->write(0);
            $this->flush();
            byte[] data = new byte[2];
            int i = readSocksReply(in, data, deadlineMillis);
            if (i != 2 || data[1] != 0) {
                $this->close();
                in.close();
                return false;
            }
            return true;
        }
        return false;
    }*/

    private function connectV4(InetSocketAddress $endpoint, int $deadlineMillis): void
    {
        if (!($endpoint->getAddress() instanceof Inet4Address)) {
            throw new \Exception("SOCKS V4 requires IPv4 only addresses");
        }
        $this->write(self::PROTO_VERS4);
        $this->write(self::CONNECT);
        $this->write(($endpoint->getPort() >> 8) & 0xff);
        $this->write(($endpoint->getPort() >> 0) & 0xff);
        $this->write($endpoint->getAddress()->getAddress());
        $userName = $this->getUserName();
        $this->write($userName);
        $this->write(0);
        //$this->flush();
        $data = "";
        $n = $this->readSocksReply($data, 8, $deadlineMillis);
        if ($n != 8) {
            throw new \Exception("Reply from SOCKS server has bad length: " . $n);
        }
        if ($data[0] != 0 && $data[0] != 4) {
            throw new \Exception("Reply from SOCKS server has bad version");
        }
        $ex = null;
        /*switch (data[1]) {
        case 90:
            // Success!
            $this->externalAddress = endpoint;
            break;
        case 91:
            ex = new SocketException("SOCKS request rejected");
            break;
        case 92:
            ex = new SocketException("SOCKS server couldn't reach destination");
            break;
        case 93:
            ex = new SocketException("SOCKS authentication failed");
            break;
        default:
            ex = new SocketException("Reply from SOCKS server contains bad status");
            break;
        }
        if (ex !== null) {
            in.close();
            $this->close();
            throw ex;
        }*/
    }

    /**
     * Connects the Socks Socket to the specified endpoint. It will first
     * connect to the SOCKS proxy and negotiate the access. If the proxy
     * grants the connections, then the connect is successful and all
     * further traffic will go to the "real" endpoint.
     *
     * @param   endpoint        the {@code SocketAddress} to connect to.
     * @param   timeout         the timeout value in milliseconds
     * @throws  IOException     if the connection can't be established.
     * @throws  SecurityException if there is a security manager and it
     *                          doesn't allow the connection
     * @throws  IllegalArgumentException if endpoint is null or a
     *          SocketAddress subclass not supported by this socket
     */
    public function connect(/*SocketAddress $endpoint, int $timeout*/...$args): void
    {
        $endpoint = $args[0];
        $timeout = $args[1];
        $deadlineMillis = 0;

        if ($timeout == 0) {
            $deadlineMillis = 0;
        } else {
            $finish = round(microtime(true) / 1000) + $timeout;
            $deadlineMillis = $finish < 0 ?  PHP_INT_MAX : $finish;
        }

        /*SecurityManager security = System.getSecurityManager();
        if (endpoint == null || !(endpoint instanceof InetSocketAddress))
            throw new IllegalArgumentException("Unsupported address type");
        if (security !== null) {
            if (epoint.isUnresolved())
                security.checkConnect(epoint.getHostName(),
                                      epoint.getPort());
            else
                security.checkConnect(epoint.getAddress().getHostAddress(),
                                      epoint.getPort());
        }*/
        if ($this->server == null) {
            // This is the general case
            // server is not null only when the socket was created with a
            // specified proxy in which case it does bypass the ProxySelector
            //@TODO. Implement Default Proxy Selector
            $sel = ProxySelector::getDefault();
            if ($sel == null) {
                parent::connect($endpoint, $this->remainingMillis($deadlineMillis));
                return;
            }
            // Use getHostString() to avoid reverse lookups
            $host = $endpoint->getHostString();
            // IPv6 litteral?
            if ($endpoint->getAddress() instanceof Inet6Address && strpos($host, "[") === false && strpos($host, ":") !== false) {
                $host = "[" . $host . "]";
            }
            $uri = new URI("socket://" . urlencode($host) . ":" . $endpoint->getPort());
            $p = null;
            $savedExc = null;
            $iProxy = $sel->select($uri);
            if (empty($iProxy)) {
                parent::connect($endpoint, $this->remainingMillis($deadlineMillis));
                return;
            }
            foreach ($iProxy as $p) {
                if ($p->type() != ProxyType::SOCKS) {
                    parent::connect($endpoint, $this->remainingMillis($deadlineMillis));
                    return;
                }

                if (!($p->address() instanceof InetSocketAddress)) {
                    throw new \Exception("Unknown address type for proxy: " . $p);
                }
                // Use getHostString() to avoid reverse lookups
                $this->server = $p->address()->getHostString();
                $this->serverPort = $p->address()->getPort();
                if ($p instanceof SocksProxy) {
                    if ($p->protocolVersion() == 4) {
                        $this->useV4 = true;
                    }
                }

                // Connects to the SOCKS server
                try {
                    $this->privilegedConnect($this->server, $this->serverPort, $this->remainingMillis($deadlineMillis));
                    // Worked, let's get outta here
                    break;
                } catch (\Exception $e) {
                    // Ooops, let's notify the ProxySelector
                    //sel.connectFailed(uri,p.address(),e);
                    $this->server = null;
                    $this->serverPort = -1;
                    $this->savedExc = $e;
                    // Will continue the while loop and try the next proxy
                }
            }

            /*
             * If server is still null at this point, none of the proxy
             * worked
             */
            if ($this->server == null) {
                throw new \Exception("Can't connect to SOCKS proxy: "  . $savedExc->getMessage());
            }
        } else {
            // Connects to the SOCKS server
            $this->privilegedConnect($this->server, $this->serverPort, $this->remainingMillis($deadlineMillis));
        }

        if ($this->useV4) {
            // SOCKS Protocol version 4 doesn't know how to deal with
            // DOMAIN type of addresses (unresolved addresses here)
            if ($endpoint->isUnresolved()) {
                throw new \Exception(strval($endpoint));
            }
            $this->connectV4($endpoint, $deadlineMillis);
            return;
        }

        // This is SOCKS V5
        $this->write(self::PROTO_VERS);
        $this->write(2);
        $this->write(self::NO_AUTH);
        $this->write(self::USER_PASSW);
        //$this->flush();
        $data = "";
        $i = $this->readSocksReply($data, 2, $deadlineMillis);
        if ($i != 2 || ($data[0]) != self::PROTO_VERS) {
            // Maybe it's not a V5 sever after all
            // Let's try V4 before we give up
            // SOCKS Protocol version 4 doesn't know how to deal with
            // DOMAIN type of addresses (unresolved addresses here)
            if ($endpoint->isUnresolved()) {
                throw new \Exception(strval($endpoint));
            }
            $this->connectV4($endpoint, $deadlineMillis);
            return;
        }
        if (($data[1]) == self::NO_METHODS) {
            throw new \Exception("SOCKS : No acceptable methods");
        }
        /*if (!authenticate(data[1], in, out, deadlineMillis)) {
            throw new SocketException("SOCKS : authentication failed");
        }*/
        $this->write(self::PROTO_VERS);
        $this->write(self::CONNECT);
        $this->write(0);
        /* Test for IPV4/IPV6/Unresolved */
        if ($endpoint->isUnresolved()) {
            $this->write(self::DOMAIN_NAME);
            $this->write(strlen($endpoint->getHostName()));
            $this->write($endpoint->getHostName());
            $this->write(($endpoint->getPort() >> 8) & 0xff);
            $this->write(($endpoint->getPort() >> 0) & 0xff);
        } elseif ($endpoint->getAddress() instanceof Inet6Address) {
            $this->write(self::IPV6);
            $this->write($endpoint->getAddress()->getAddress());
            $this->write(($endpoint->getPort() >> 8) & 0xff);
            $this->write(($endpoint->getPort() >> 0) & 0xff);
        } else {
            $this->write(self::IPV4);
            $this->write($endpoint->getAddress()->getAddress());
            $this->write(($endpoint->getPort() >> 8) & 0xff);
            $this->write(($endpoint->getPort() >> 0) & 0xff);
        }
        //$this->flush();
        $data = "";
        $i = $this->readSocksReply($data, 4, $deadlineMillis);
        if ($i != 4) {
            throw new \Exception("Reply from SOCKS server has bad length");
        }
        /*SocketException ex = null;
        int len;
        byte[] addr;
        switch (data[1]) {
        case REQUEST_OK:
            // success!
            switch(data[3]) {
            case IPV4:
                addr = new byte[4];
                i = readSocksReply(in, addr, deadlineMillis);
                if (i != 4)
                    throw new SocketException("Reply from SOCKS server badly formatted");
                data = new byte[2];
                i = readSocksReply(in, data, deadlineMillis);
                if (i != 2)
                    throw new SocketException("Reply from SOCKS server badly formatted");
                break;
            case DOMAIN_NAME:
                len = data[1];
                byte[] host = new byte[len];
                i = readSocksReply(in, host, deadlineMillis);
                if (i != len)
                    throw new SocketException("Reply from SOCKS server badly formatted");
                data = new byte[2];
                i = readSocksReply(in, data, deadlineMillis);
                if (i != 2)
                    throw new SocketException("Reply from SOCKS server badly formatted");
                break;
            case IPV6:
                len = data[1];
                addr = new byte[len];
                i = readSocksReply(in, addr, deadlineMillis);
                if (i != len)
                    throw new SocketException("Reply from SOCKS server badly formatted");
                data = new byte[2];
                i = readSocksReply(in, data, deadlineMillis);
                if (i != 2)
                    throw new SocketException("Reply from SOCKS server badly formatted");
                break;
            default:
                ex = new SocketException("Reply from SOCKS server contains wrong code");
                break;
            }
            break;
        case GENERAL_FAILURE:
            ex = new SocketException("SOCKS server general failure");
            break;
        case NOT_ALLOWED:
            ex = new SocketException("SOCKS: Connection not allowed by ruleset");
            break;
        case NET_UNREACHABLE:
            ex = new SocketException("SOCKS: Network unreachable");
            break;
        case HOST_UNREACHABLE:
            ex = new SocketException("SOCKS: Host unreachable");
            break;
        case CONN_REFUSED:
            ex = new SocketException("SOCKS: Connection refused");
            break;
        case TTL_EXPIRED:
            ex =  new SocketException("SOCKS: TTL expired");
            break;
        case CMD_NOT_SUPPORTED:
            ex = new SocketException("SOCKS: Command not supported");
            break;
        case ADDR_TYPE_NOT_SUP:
            ex = new SocketException("SOCKS: address type not supported");
            break;
        }
        if (ex !== null) {
            in.close();
            $this->close();
            throw ex;
        }*/
        $this->externalAddress = $endpoint;
    }

    private function bindV4(InetAddress $baddr,int $lport): void
    {
        if (!($baddr instanceof Inet4Address)) {
            throw new \Exception("SOCKS V4 requires IPv4 only addresses");
        }
        parent::bind($baddr, $lport);
        $addr1 = $baddr->getAddress();
        /* Test for AnyLocal */
        $naddr = $baddr;
        if ($naddr->isAnyLocalAddress()) {
            $naddr = $this->cmdsock->etLocalAddress();;
            $addr1 = $naddr->getAddress();
        }
        $this->write(self::PROTO_VERS4);
        $this->write(self::BIND);
        $this->write((parent::getLocalPort() >> 8) & 0xff);
        $this->write((parent::getLocalPort() >> 0) & 0xff);
        $this->write($addr1);
        $userName = $this->getUserName();
        $this->write($userName);
        $this->write(0);
        /*$this->flush();
        byte[] data = new byte[8];
        int n = readSocksReply(in, data);
        if (n != 8)
            throw new SocketException("Reply from SOCKS server has bad length: " + n);
        if (data[0] != 0 && data[0] != 4)
            throw new SocketException("Reply from SOCKS server has bad version");
        SocketException ex = null;
        switch (data[1]) {
        case 90:
            // Success!
            $this->externalAddress = new InetSocketAddress(baddr, lport);
            break;
        case 91:
            ex = new SocketException("SOCKS request rejected");
            break;
        case 92:
            ex = new SocketException("SOCKS server couldn't reach destination");
            break;
        case 93:
            ex = new SocketException("SOCKS authentication failed");
            break;
        default:
            ex = new SocketException("Reply from SOCKS server contains bad status");
            break;
        }
        if (ex !== null) {
            in.close();
            $this->close();
            throw ex;
        }*/
    }

    /**
     * Sends the Bind request to the SOCKS proxy. In the SOCKS protocol, bind
     * means "accept incoming connection from", so the SocketAddress is the
     * the one of the host we do accept connection from.
     *
     * @param      saddr   the Socket address of the remote host.
     * @exception  IOException  if an I/O error occurs when binding this socket.
     */
    protected function socksBind(InetSocketAddress $saddr): void
    {
        if ($this->socket !== null) {
            // this is a client socket, not a server socket, don't
            // call the SOCKS proxy for a bind!
            return;
        }

        // Connects to the SOCKS server

        if ($this->server == null) {
            // This is the general case
            // server is not null only when the socket was created with a
            // specified proxy in which case it does bypass the ProxySelector
            $sel = ProxySelector::getDefault();
            $uri = null;
            // Use getHostString() to avoid reverse lookups
            $host = $saddr->getHostString();
            // IPv6 litteral?
            if ($saddr->getAddress() instanceof Inet6Address && strpos($host, "[") === false && strpos($host, ":") !== false) {
                $host = "[" . $host . "]";
            }
            try {
                $uri = new URI("serversocket://" . urlencode($host) . ":" .  $saddr->getPort());
            } catch (\Exception $e) {
                // This shouldn't happen
                $uri = null;
            }
            $p = null;
            $savedExc = null;
            $iProxy = $sel->select($uri);
            if (empty($iProxy)) {
                return;
            }
            foreach ($iProxy as $p) {
                if ($p->type() != ProxyType::SOCKS) {
                    return;
                }

                if (!($p->address() instanceof InetSocketAddress)) {
                    throw new \Exception("Unknown address type for proxy: " . $p);
                }
                // Use getHostString() to avoid reverse lookups
                $this->server = $p->address()->getHostString();
                $this->serverPort = $p->address()->getPort();
                if ($p instanceof SocksProxy) {
                    if ($p->protocolVersion() == 4) {
                        $this->useV4 = true;
                    }
                }

                // Connects to the SOCKS server
                try {
                    $this->cmdsock = new Socket(new PlainSocketImpl());
                    $this->cmdsock->connect(new InetSocketAddress($this->server, $this->serverPort));
                } catch (\Exception $e) {
                    // Ooops, let's notify the ProxySelector
                    //sel.connectFailed(uri,p.address(),new SocketException(e.getMessage()));
                    $this->server = null;
                    $this->serverPort = -1;
                    $this->cmdsock = null;
                    $this->savedExc = $e;
                    // Will continue the while loop and try the next proxy
                }
            }

            /*
             * If server is still null at this point, none of the proxy
             * worked
             */
            if ($this->server == null || $this->cmdsock == null) {
                throw new \Exception("Can't connect to SOCKS proxy:" . $savedExc->getMessage());
            }
        } else {
            $this->cmdsock = new Socket(new PlainSocketImpl());
            $this->cmdsock->connect(new InetSocketAddress($this->server, $this->serverPort));
        }
        if ($this->useV4) {
            $this->bindV4($saddr->getAddress(), $saddr->getPort());
            return;
        }
        $this->write(self::PROTO_VERS);
        $this->write(2);
        $this->write(self::NO_AUTH);
        $this->write(self::USER_PASSW);
        //$this->flush();
        $data = "";
        $i = $this->readSocksReply($data, 2);
        if ($i != 2 || ($data[0]) != self::PROTO_VERS) {
            // Maybe it's not a V5 sever after all
            // Let's try V4 before we give up
            $this->bindV4($saddr->getAddress(), $saddr->getPort());
            return;
        }
        if ($data[1] == self::NO_METHODS) {
            throw new \Exception("SOCKS : No acceptable methods");
        }
        /*if (!authenticate(data[1], in, out)) {
            throw new SocketException("SOCKS : authentication failed");
        }*/
        // We're OK. Let's issue the BIND command.
        $this->write(self::PROTO_VERS);
        $this->write(self::BIND);
        $this->write(0);
        $lport = $saddr->getPort();
        if ($saddr->isUnresolved()) {
            $this->write(self::DOMAIN_NAME);
            $this->write(strlen($saddr->getHostName()));
            $this->write($saddr->getHostName());
            $this->write(($lport >> 8) & 0xff);
            $this->write(($lport >> 0) & 0xff);
        } elseif ($saddr->getAddress() instanceof Inet4Address) {
            $addr1 = $saddr->getAddress()->getAddress();
            $this->write(self::IPV4);
            $this->write($addr1);
            $this->write(($lport >> 8) & 0xff);
            $this->write(($lport >> 0) & 0xff);
            //$this->flush();
        } elseif ($saddr->getAddress() instanceof Inet6Address) {
            $addr1 = $saddr->getAddress()->getAddress();
            $this->write(self::IPV6);
            $this->write(self::addr1);
            $this->write(($lport >> 8) & 0xff);
            $this->write(($lport >> 0) & 0xff);
            //$this->flush();
        } else {
            $this->cmdsock->close();
            throw new \Exception("unsupported address type : " . $saddr);
        }
        $data = "";
        $i = $this->readSocksReply($data, 4);
        $ex = null;
        $len = 0;
        $nport = 0;
        $addr = null;
        /*switch (data[1]) {
        case REQUEST_OK:
            // success!
            switch(data[3]) {
            case IPV4:
                addr = new byte[4];
                i = readSocksReply(in, addr);
                if (i != 4)
                    throw new SocketException("Reply from SOCKS server badly formatted");
                data = new byte[2];
                i = readSocksReply(in, data);
                if (i != 2)
                    throw new SocketException("Reply from SOCKS server badly formatted");
                nport = ((int)data[0] & 0xff) << 8;
                nport += ((int)data[1] & 0xff);
                $this->externalAddress =
                    new InetSocketAddress(new Inet4Address("", addr) , nport);
                break;
            case DOMAIN_NAME:
                len = data[1];
                byte[] host = new byte[len];
                i = readSocksReply(in, host);
                if (i != len)
                    throw new SocketException("Reply from SOCKS server badly formatted");
                data = new byte[2];
                i = readSocksReply(in, data);
                if (i != 2)
                    throw new SocketException("Reply from SOCKS server badly formatted");
                nport = ((int)data[0] & 0xff) << 8;
                nport += ((int)data[1] & 0xff);
                $this->externalAddress = new InetSocketAddress(new String(host), nport);
                break;
            case IPV6:
                len = data[1];
                addr = new byte[len];
                i = readSocksReply(in, addr);
                if (i != len)
                    throw new SocketException("Reply from SOCKS server badly formatted");
                data = new byte[2];
                i = readSocksReply(in, data);
                if (i != 2)
                    throw new SocketException("Reply from SOCKS server badly formatted");
                nport = ((int)data[0] & 0xff) << 8;
                nport += ((int)data[1] & 0xff);
                $this->externalAddress =
                    new InetSocketAddress(new Inet6Address("", addr), nport);
                break;
            }
            break;
        case GENERAL_FAILURE:
            ex = new SocketException("SOCKS server general failure");
            break;
        case NOT_ALLOWED:
            ex = new SocketException("SOCKS: Bind not allowed by ruleset");
            break;
        case NET_UNREACHABLE:
            ex = new SocketException("SOCKS: Network unreachable");
            break;
        case HOST_UNREACHABLE:
            ex = new SocketException("SOCKS: Host unreachable");
            break;
        case CONN_REFUSED:
            ex = new SocketException("SOCKS: Connection refused");
            break;
        case TTL_EXPIRED:
            ex =  new SocketException("SOCKS: TTL expired");
            break;
        case CMD_NOT_SUPPORTED:
            ex = new SocketException("SOCKS: Command not supported");
            break;
        case ADDR_TYPE_NOT_SUP:
            ex = new SocketException("SOCKS: address type not supported");
            break;
        }
        if (ex !== null) {
            in.close();
            $this->close();
            $this->cmdsock.close();
            $this->cmdsock = null;
            throw ex;
        }
        cmdIn = in;
        cmdOut = out;*/
    }

    /**
     * Accepts a connection from a specific host.
     *
     * @param      s   the accepted connection.
     * @param      saddr the socket address of the host we do accept
     *               connection from
     * @exception  IOException  if an I/O error occurs when accepting the
     *               connection.
     */
    protected function acceptFrom(SocketImpl $s, InetSocketAddress $saddr): void
    {
        if ($this->cmdsock == null) {
            // Not a Socks ServerSocket.
            return;
        }
        // Sends the "SOCKS BIND" request.
        $this->socksBind($saddr);
        /*in.read();
        int i = in.read();
        in.read();
        SocketException ex = null;
        int nport;
        byte[] addr;
        InetSocketAddress real_end = null;
        switch (i) {
        case REQUEST_OK:
            // success!
            i = in.read();
            switch(i) {
            case IPV4:
                addr = new byte[4];
                readSocksReply(in, addr);
                nport = in.read() << 8;
                nport += in.read();
                real_end =
                    new InetSocketAddress(new Inet4Address("", addr) , nport);
                break;
            case DOMAIN_NAME:
                int len = in.read();
                addr = new byte[len];
                readSocksReply(in, addr);
                nport = in.read() << 8;
                nport += in.read();
                real_end = new InetSocketAddress(new String(addr), nport);
                break;
            case IPV6:
                addr = new byte[16];
                readSocksReply(in, addr);
                nport = in.read() << 8;
                nport += in.read();
                real_end =
                    new InetSocketAddress(new Inet6Address("", addr), nport);
                break;
            }
            break;
        case GENERAL_FAILURE:
            ex = new SocketException("SOCKS server general failure");
            break;
        case NOT_ALLOWED:
            ex = new SocketException("SOCKS: Accept not allowed by ruleset");
            break;
        case NET_UNREACHABLE:
            ex = new SocketException("SOCKS: Network unreachable");
            break;
        case HOST_UNREACHABLE:
            ex = new SocketException("SOCKS: Host unreachable");
            break;
        case CONN_REFUSED:
            ex = new SocketException("SOCKS: Connection refused");
            break;
        case TTL_EXPIRED:
            ex =  new SocketException("SOCKS: TTL expired");
            break;
        case CMD_NOT_SUPPORTED:
            ex = new SocketException("SOCKS: Command not supported");
            break;
        case ADDR_TYPE_NOT_SUP:
            ex = new SocketException("SOCKS: address type not supported");
            break;
        }
        if (ex !== null) {
            cmdIn.close();
            cmd$this->close();
            $this->cmdsock.close();
            $this->cmdsock = null;
            throw ex;
        }

        if (s instanceof SocksSocketImpl) {
            ((SocksSocketImpl)s).$this->externalAddress = real_end;
        }
        if (s instanceof PlainSocketImpl) {
            PlainSocketImpl psi = (PlainSocketImpl) s;
            psi.setInputStream(($this->socketInputStream) in);
            psi.setFileDescriptor($this->cmdsock.getImpl().getFileDescriptor());
            psi.setAddress($this->cmdsock.getImpl().getInetAddress());
            psi.setPort($this->cmdsock.getImpl().getPort());
            psi.setLocalPort($this->cmdsock.getImpl().getLocalPort());
        } else {
            s.fd = $this->cmdsock.getImpl().fd;
            s.address = $this->cmdsock.getImpl().address;
            s.port = $this->cmdsock.getImpl().port;
            s.localport = $this->cmdsock.getImpl().localport;
        }*/

        // Need to do that so that the socket won't be closed
        // when the ServerSocket is closed by the user.
        // It kinds of detaches the Socket because it is now
        // used elsewhere.
        $this->cmdsock = null;
    }


    /**
     * Returns the value of this socket's {@code address} field.
     *
     * @return  the value of this socket's {@code address} field.
     * @see     java.net.SocketImpl#address
     */
    public function getInetAddress(): ?InetAddress
    {
        if ($this->externalAddress !== null) {
            return $this->externalAddress->getAddress();
        } else {
            return parent::getInetAddress();
        }
    }

    /**
     * Returns the value of this socket's {@code port} field.
     *
     * @return  the value of this socket's {@code port} field.
     * @see     java.net.SocketImpl#port
     */
    public function getPort(): int
    {
        if ($this->externalAddress !== null) {
            return $this->externalAddress->getPort();
        } else {
            return parent::getPort();
        }
    }

    public function getLocalPort(): int
    {
        if ($this->socket !== null) {
            return parent::getLocalPort();
        }
        if ($this->externalAddress !== null) {
            return $this->externalAddress->getPort();
        } else {
            return parent::getLocalPort();
        }
    }

    public function close(): void
    {
        if ($this->cmdsock !== null) {
            $this->cmdsock->close();
        }
        $this->cmdsock = null;
        parent::close();
    }

    private function getUserName(): string
    {
        if (array_key_exists("user.name", self::$props)) {
            return self::$props["user.name"];
        }
        return "";
    }

    private function readPropertiesFromFile(string $path): array
    {
        $props = [];
        if (file_exists($path)) {                
            $fp = fopen($path, "r");       
            while (($line = fgets($fp, 4096)) !== false) {
                $tokens = explode("=", $line);
                if (count($tokens) == 2) {
                    $props[$tokens[0]] = trim($tokens[1]);
                }
            }
            fclose($fp);
        }
        return $props;
    }
}
