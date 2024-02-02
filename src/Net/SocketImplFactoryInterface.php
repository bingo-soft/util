<?php

namespace Util\Net;

interface SocketImplFactoryInterface
{
    /**
     * Creates a new {@code SocketImpl} instance.
     *
     * @return  a new instance of {@code SocketImpl}.
     * @see     java.net.SocketImpl
     */
    public function createSocketImpl(): SocketImpl;
}