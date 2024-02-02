<?php

namespace Util\Net\Naming\Directory;

class BasicAttribute implements AttributeInterface
{
    /**
     * Holds the attribute's id. It is initialized by the public constructor and
     * cannot be null unless methods in BasicAttribute that use attrID
     * have been overridden.
     * @serial
     */
    protected $attrID;

    /**
     * Holds the attribute's values. Initialized by public constructors.
     * Cannot be null unless methods in BasicAttribute that use
     * values have been overridden.
     */
    protected $values = [];

    /**
     * A flag for recording whether this attribute's values are ordered.
     * @serial
     */
    protected bool $ordered = false;

    public function clone()
    {
        $attr = null;
        $attr = new BasicAttribute($this->attrID, $this->ordered);
        $attr->values = array_slice($this->values, 0, count($this->values));
        return $attr;
    }

    /**
      * Determines whether obj is equal to this attribute.
      * Two attributes are equal if their attribute-ids, syntaxes
      * and values are equal.
      * If the attribute values are unordered, the order that the values were added
      * are irrelevant. If the attribute values are ordered, then the
      * order the values must match.
      * If obj is null or not an Attribute, false is returned.
      *<p>
      * By default <tt>Object.equals()</tt> is used when comparing the attribute
      * id and its values except when a value is an array. For an array,
      * each element of the array is checked using <tt>Object.equals()</tt>.
      * A subclass may override this to make
      * use of schema syntax information and matching rules,
      * which define what it means for two attributes to be equal.
      * How and whether a subclass makes
      * use of the schema information is determined by the subclass.
      * If a subclass overrides <tt>equals()</tt>, it should also override
      * <tt>hashCode()</tt>
      * such that two attributes that are equal have the same hash code.
      *
      * @param obj      The possibly null object to check.
      * @return true if obj is equal to this attribute; false otherwise.
      * @see #hashCode
      * @see #contains
      */
    public function equals($obj = null): bool
    {
        if (($obj != null) && ($obj instanceof AttributeInterface)) {
            $target = $obj;

            // Check order first
            if ($this->isOrdered() != $target->isOrdered()) {
                return false;
            }
            $len = 0;
            if ($this->attrID == $target->getID() &&
                ($len=$this->size()) == $target->size()) {
                try {
                    if ($this->isOrdered()) {
                        // Go through both list of values
                        for ($i = 0; $i < $len; $i += 1) {
                            if (!self::valueEquals($this->get($i), $target->get($i))) {
                                return false;
                            }
                        }
                    } else {
                        // order is not relevant; check for existence
                        $theirs = $target->getAll();
                        foreach ($theirs as $el) {
                            if ($this->find($el) < 0) {
                                return false;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    return false;
                }
                return true;
            }
        }
        return false;
    }

    /**
      * Generates the string representation of this attribute.
      * The string consists of the attribute's id and its values.
      * This string is meant for debugging and not meant to be
      * interpreted programmatically.
      * @return The non-null string representation of this attribute.
      */
    public function __toString(): string
    {
        $answer = $this->attrID . ": ";
        if (count($this->values) == 0) {
            $answer .= "No values";
        } else {
            $start = true;
            foreach ($this->values as $value) {
                if (!$start) {
                    $answer .= ", ";
                } 
                $answer .= $value;
                $start = false;
            }
        }
        return $answer;
    }

    /**
      * Constructs a new instance of an unordered attribute with no value.
      *
      * @param id The attribute's id. It cannot be null.
      */
    public function __construct(string $id, ...$args)
    {
        $this->attrID = $id;
        if (($cnt = count($args)) > 0) {
            if ($cnt == 1) {
                if (is_bool($args[0])) {
                    $this->ordered = $args[0];
                } else {
                    $this->ordered = false;
                    $this->values[] = $args[0];
                }
            } elseif ($cnt == 2) {                
                $this->values[] = $args[0];
                $this->ordered = $args[1];
            }
        }
    }

    /**
      * Retrieves an enumeration of this attribute's values.
      *<p>
      * By default, the values returned are those passed to the
      * constructor and/or manipulated using the add/replace/remove methods.
      * A subclass may override this to retrieve the values dynamically
      * from the directory.
      */
    public function getAll(): array
    {
      return [];
    }

    /**
      * Retrieves one of this attribute's values.
      *<p>
      * By default, the value returned is one of those passed to the
      * constructor and/or manipulated using the add/replace/remove methods.
      * A subclass may override this to retrieve the value dynamically
      * from the directory.
      */
    public function get(int $x = null)
    {
        if ($x !== null && array_key_exists($x, $this->values)) {
            return $this->values[$x];
        }
        if (count($this->values) == 0) {
            throw new \Exception("Attribute " . $this->getID() . " has no value");
        } else {
            return $this->values[0];
        }
    }

    public function size(): int
    {
      return count($this->values);
    }

    public function getID(): ?string
    {
        return $this->attrID;
    }

    /**
      * Determines whether a value is in this attribute.
      *<p>
      * By default,
      * <tt>Object.equals()</tt> is used when comparing <tt>attrVal</tt>
      * with this attribute's values except when <tt>attrVal</tt> is an array.
      * For an array, each element of the array is checked using
      * <tt>Object.equals()</tt>.
      * A subclass may use schema information to determine equality.
      */
    public function contains($attrVal): bool
    {
        return ($this->find($attrVal) >= 0);
    }

    // For finding first element that has a null in JDK1.1 Vector.
    private function find($target = null): int
    {
        $cl = null;
        if ($target == null) {
            $ct = count($this->values);
            for ($i = 0 ; $i < $ct ; $i += 1) {
                if ($this->values[$i] == null) {
                    return $i;
                }
            }
        } elseif (is_array($target)) {
            $ct = count($this->values);
            $it = null;
            for ($i = 0 ; $i < $ct ; $i += 1) {
                $it = $this->values[$i];
                if ($it != null && is_array($it) && $target == $it) {
                    return $i;
                }
            }
        } else {
            $idx = array_search($target, $this->values);
            if ($idx === false) {
                return -1;
            }
            return $idx;
        }
        return -1;  // not found
    }

    /**
     * Determines whether two attribute values are equal.
     */
    private static function valueEquals($obj1, $obj2): bool
    {
        if ($obj1 == $obj2) {
            return true; // object references are equal
        }
        if ($obj1 == null) {
            return false; // obj2 was not false
        }
        return $obj1 == $obj2;
    }

    /**
      * Adds a new value to this attribute.
      *<p>
      * By default, <tt>Object.equals()</tt> is used when comparing <tt>attrVal</tt>
      * with this attribute's values except when <tt>attrVal</tt> is an array.
      * For an array, each element of the array is checked using
      * <tt>Object.equals()</tt>.
      * A subclass may use schema information to determine equality.
      */
    public function add(...$args): bool
    {
        if (count($args) == 1) {
            if ($this->isOrdered() || ($this->find($args[0]) < 0)) {
                $this->values[] = $args[0];
                return true;
            } else {
                return false;
            }
        } else {
            if (!$this->isOrdered() && $this->contains($args[1])) {
                throw new \Exception("Cannot add duplicate to unordered attribute");
            }
            array_splice($this->values, $args[0], 0, [ $args[1] ]);
        }
    }

    public function clear(): void
    {
        $this->values = [];
    }

    //  ---- ordering methods

    public function isOrdered(): bool
    {
        return $this->ordered;
    }

    public function remove($attrval)
    {
        if (is_int($attrval)) {
            $answer = $this->values[$attrval];
            array_splice($this->values, $attrval, 1);
            return $answer;
        } else {
            // Need to do the following to handle null case

            $i = $this->find($attrval);
            if ($i >= 0) {
                array_splice($this->values, $i, 1);
                return true;
            }
            return false;
        }
    }

    public function set(int $ix, $attrVal)
    {
        if (!$this->isOrdered() && $this->contains($attrVal)) {
            throw new \Exception("Cannot add duplicate to unordered attribute");
        }

        if (array_key_exists($ix, $this->values)) {
            $answer = $this->values[$ix];
            array_splice($this->values, $ix, 0, [ $attrVal ]);
            return $answer;
        }
        return null;
    }

    // ----------------- Schema methods

    /**
      * Retrieves the syntax definition associated with this attribute.
      *<p>
      * This method by default throws OperationNotSupportedException. A subclass
      * should override this method if it supports schema.
      */
    public function getAttributeSyntaxDefinition(): ?DirContextInterface
    {
        throw new \Exception("Operation not supported");
    }

    /**
      * Retrieves this attribute's schema definition.
      *<p>
      * This method by default throws OperationNotSupportedException. A subclass
      * should override this method if it supports schema.
      */
    public function getAttributeDefinition(): ?DirContextInterface
    {
        throw new \Exception("Operation not supported");
    }
}
