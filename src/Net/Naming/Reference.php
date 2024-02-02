<?php

namespace Util\Net\Naming;

class Reference
{
    /**
     * Contains the fully-qualified name of the class of the object to which
     * this Reference refers.
     */
    protected $className;

    /**
     * Contains the addresses contained in this Reference.
     * Initialized by constructor.
     */
    protected $addrs = [];

    /**
     * Contains the name of the factory class for creating
     * an instance of the object to which this Reference refers.
     * Initialized to null.
     */
    protected $classFactory = null;

    /**
     * Contains the location of the factory class.
     * Initialized to null.
     */
    protected $classFactoryLocation = null;

    /**
      * Constructs a new reference for an object with class name 'className'.
      * Class factory and class factory location are set to null.
      * The newly created reference contains zero addresses.
      *
      * @param className The non-null class name of the object to which
      * this reference refers.
      */
    public function __construct(string $className, ...$args)
    {
        $this->className  = $className;
        if (!empty($args)) {
            if (count($args) == 1 && is_object($args[0]) && $args[0] instanceof RefAddr) {
                $this->addrs[] = $args[0];
            } elseif (count($args) == 2 && is_string($args[0]) && is_string($args[1])) {
                $this->classFactory = $args[0];
                $this->classFactoryLocation = $args[1];
            } elseif (count($args) == 3 && is_object($args[0]) && $args[0] instanceof RefAddr) {
                $this->addrs[] = $args[0];
                $this->classFactory = $args[1];
                $this->classFactoryLocation = $args[2];
            }
        }
    }

    /**
      * Retrieves the class name of the object to which this reference refers.
      *
      * @return The non-null fully-qualified class name of the object.
      */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
      * Retrieves the class name of the factory of the object
      * to which this reference refers.
      *
      * @return The possibly null fully-qualified class name of the factory.
      */
    public function getFactoryClassName(): ?string
    {
        return $this->classFactory;
    }

    /**
      * Retrieves the location of the factory of the object
      * to which this reference refers.
      * If it is a codebase, then it is an ordered list of URLs,
      * separated by spaces, listing locations from where the factory
      * class definition should be loaded.
      *
      * @return The possibly null string containing the
      *                 location for loading in the factory's class.
      */
    public function getFactoryClassLocation(): ?string
    {
        return $this->classFactoryLocation;
    }

    /**
      * Retrieves the first address that has the address type 'addrType'.
      * String.compareTo() is used to test the equality of the address types.
      *
      * @param addrType The non-null address type for which to find the address.
      * @return The address in this reference with address type 'addrType;
      *         null if no such address exist.
      */
    public function get(string|int $address): ?RefAddr
    {
        if (is_string($address)) {
            $len = count($this->addrs);
            $addr = null;
            for ($i = 0; $i < $len; $i++) {
                $addr = $this->addrs[$i];
                if ($addr->getType() == $address)
                    return $addr;
            }
        } elseif (is_int($address) && array_key_exists($address, $this->addrs)) {
            return $this->addrs[$address];
        }
        return null;
    }

    /**
      * Retrieves an enumeration of the addresses in this reference.
      * When addresses are added, changed or removed from this reference,
      * its effects on this enumeration are undefined.
      *
      * @return An non-null enumeration of the addresses
      *         (<tt>RefAddr</tt>) in this reference.
      *         If this reference has zero addresses, an enumeration with
      *         zero elements is returned.
      */
    public function getAll(): array
    {
        return $this->addrs;
    }

    /**
      * Retrieves the number of addresses in this reference.
      *
      * @return The nonnegative number of addresses in this reference.
      */
    public function size(): int
    {
        return count($this->addrs);
    }

    /**
      * Adds an address to the end of the list of addresses.
      *
      * @param addr The non-null address to add.
      */
    public function add(...$args): void
    {
        if (count($args) == 1 && $args[0] instanceof RefAddr) {
            $this->addrs[] = $args[0];
        } elseif (count($args) == 2 && is_int($args[0])) {
            array_splice($this->addrs, $args[0], 0, $args[1]);
        }
    }
    /**
      * Deletes the address at index posn from the list of addresses.
      * All addresses at index greater than posn are shifted down
      * the list by one (towards index 0).
      *
      * @param posn The 0-based index of in address to delete.
      * @return The address removed.
      * @exception ArrayIndexOutOfBoundsException If posn not in the specified
      *         range.
      */
    public function remove(int $posn)
    {
        if (array_key_exists($posn, $this->addrs)) {
            $r = $this->addrs[$posn];
            array_splice($this->addrs, $posn, 1);
        }
        return null;
    }

    /**
      * Deletes all addresses from this reference.
      */
    public function clear(): void
    {
        $this->addrs = [];
    }

    /**
      * Determines whether obj is a reference with the same addresses
      * (in same order) as this reference.
      * The addresses are checked using RefAddr.equals().
      * In addition to having the same addresses, the Reference also needs to
      * have the same class name as this reference.
      * The class factory and class factory location are not checked.
      * If obj is null or not an instance of Reference, null is returned.
      *
      * @param obj The possibly null object to check.
      * @return true if obj is equal to this reference; false otherwise.
      */
    public function equals($obj = null): bool
    {
        if (($obj != null) && ($obj instanceof Reference)) {
            // ignore factory information
            if (get_class($obj) == get_class($this) && $obj->size() ==  $this->size()) {
                $mycomps = $this->getAll();
                $comps = $obj->getAll();
                while (mycomps.hasMoreElements())
                    if (!(mycomps.nextElement().equals(comps.nextElement())))
                        return false;
                for ($i = 0; $i < count($mycomps); $i += 1) {
                    if ($mycomps[$i] != $comps[$i]) {
                        return false;
                    }
                }
                return true;
            }
        }
        return false;
    }

    /**
      * Generates the string representation of this reference.
      * The string consists of the class name to which this reference refers,
      * and the string representation of each of its addresses.
      * This representation is intended for display only and not to be parsed.
      *
      * @return The non-null string representation of this reference.
      */
    public function __toString(): string
    {
        $buf = "Reference Class Name: "  . $className . "\n";
        $len = count($this->addrs);
        for ($i = 0; $i < $len; $i += 1) {
            $buf .= $this->get($i);
        }
        return $buf;
    }

    /**
     * Makes a copy of this reference using its class name
     * list of addresses, class factory name and class factory location.
     * Changes to the newly created copy does not affect this Reference
     * and vice versa.
     */
    public function clone()
    {
        $r = new Reference($this->className, $this->classFactory, $this->classFactoryLocation);
        $a = $this->getAll();
        foreach ($a as $addr) {
            $r->add($addr);
        }
        return $r;
    }
}
