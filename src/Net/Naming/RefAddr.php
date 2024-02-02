<?php

namespace Util\Net\Naming;

abstract class RefAddr
{
    /**
     * Contains the type of this address.
     * @serial
     */
    protected string $addrType;

    /**
     * Constructs a new instance of RefAddr using its address type.
     *
     * @param addrType A non-null string describing the type of the address.
     */
    public function __construct(string $addrType)
    {
        $this->addrType = $addrType;
    }

    /**
     * Retrieves the address type of this address.
     *
     * @return The non-null address type of this address.
     */
    public function getType(): string
    {
        return $this->addrType;
    }

    /**
     * Retrieves the contents of this address.
     *
     * @return The possibly null address contents.
     */
    abstract public function getContent();

    /**
     * Determines whether obj is equal to this RefAddr.
     *<p>
     * obj is equal to this RefAddr all of these conditions are true
     *<ul>
     *<li> non-null
     *<li> instance of RefAddr
     *<li> obj has the same address type as this RefAddr (using String.compareTo())
     *<li> both obj and this RefAddr's contents are null or they are equal
     *         (using the equals() test).
     *</ul>
     * @param obj possibly null obj to check.
     * @return true if obj is equal to this refaddr; false otherwise.
     * @see #getContent
     * @see #getType
     */
    public function equals($obj = null): bool
    {
        if (($obj != null) && ($obj instanceof RefAddr)) {
            if ($this->addrType == $obj->addrType) {
                $thisobj = $this->getContent();
                $thatobj = $obj->getContent();
                return $thisobj == $thatobj;
            }
        }
        return false;
    }

    /**
     * Generates the string representation of this address.
     * The string consists of the address's type and contents with labels.
     * This representation is intended for display only and not to be parsed.
     * @return The non-null string representation of this address.
     */
    public function __toString(): string
    {
        $str = "Type: " . $this->addrType . "\n";
        $str .= "Content: " . $this->getContent() . "\n";
        return $str;
    }
}
