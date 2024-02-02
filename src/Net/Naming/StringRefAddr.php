<?php

namespace Util\Net\Naming;

class StringRefAddr extends RefAddr
{
    /**
     * Contains the contents of this address.
     * Can be null.
     * @serial
     */
    private $contents;

    /**
      * Constructs a new instance of StringRefAddr using its address type
      * and contents.
      *
      * @param addrType A non-null string describing the type of the address.
      * @param addr The possibly null contents of the address in the form of a string.
      */
    public function __construct(string $addrType, ?string $addr = null) {
        parent::__construct($addrType);
        $this->contents = $addr;
    }

    /**
      * Retrieves the contents of this address. The result is a string.
      *
      * @return The possibly null address contents.
      */
    public function getContent()
    {
        return $this->contents;
    }
}
