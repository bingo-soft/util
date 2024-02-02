<?php

namespace Util\Net\Naming;

interface ReferenceableInterface
{
    /**
     * Retrieves the Reference of this object.
     *
     * @return The non-null Reference of this object.
     * @exception NamingException If a naming exception was encountered
     *         while retrieving the reference.
     */
    public function getReference(): ?Reference;
}
