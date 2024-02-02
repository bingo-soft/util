<?php

namespace Util\Net\Naming\Directory;

class BasicAttributes implements AttributesInterface
{
    /**
     * Indicates whether case of attribute ids is ignored.
     * @serial
     */
    private bool $ignoreCase = false;

    // The 'key' in attrs is stored in the 'right case'.
    // If ignoreCase is true, key is aways lowercase.
    // If ignoreCase is false, key is stored as supplied by put().
    // %%% Not declared "private" due to bug 4064984.
    public $attrs = [];

    public function __construct(...$args) {
        if (!empty($args)) {
            if (count($args) == 1 && is_bool($args[0])) {
                $this->ignoreCase = $args[0];
            } elseif (count($args) == 2) {
                $this->put(new BasicAttribute($args[0], $args[1]));
            } elseif (count($args) == 3) {
                $this->ignoreCase = $args[2];
                $this->put(new BasicAttribute($args[0], $args[1]));
            }
        }
    }

    public function clone()
    {
        $attrset = new BasicAttributes();
        foreach ($this->attrs as $attr) {
            $attrset->attrs[$attr->getID()] = $attr->clone();
        }
        return $attrset;
    }

    public function isCaseIgnored(): bool
    {
        return $this->ignoreCase;
    }

    public function size(): int
    {
        return count($this->attrs);
    }

    public function get(string $attrID): ?AttributeInterface
    {
        if ($this->ignoreCase) {
            if (array_key_exists($attrID, $this->attrs)) {
                return $this->attrs[$attrID];
            }
            if (array_key_exists(strtolower($attrID), $this->attrs)) {
                return $this->attrs[strtolower($attrID)];
            }
        } elseif (array_key_exists($attrID, $this->attrs)) {
            return $this->attrs[$attrID];
        }

        return null;
    }

    public function &getAll(): array
    {
        $arr = [];
        return $arr;
    }

    public function &getIDs(): array
    {
        $arr = [];
        return $arr;
    }

    public function put(...$args): ?AttributeInterface
    {
        $ret = null;
        if (count($args) == 1) {
            $ret = $args[0];
            $id = $args[0]->getID();
            if ($this->ignoreCase) {
                $id = strtolower($id);
            }
            $this->attrs[$id] = $args[0];
        } elseif (count($args) == 2) {
            $ret = new BasicAttribute($args[0], $args[1]);
            $this->put($ret);
        }
        return $ret;
    }

    public function remove(string $attrID): ?AttributeInterface
    {
        $key = null;
        $attr = null;
        if ($this->ignoreCase) {
            if (array_key_exists($attrID, $this->attrs)) {
                $key = $attrID;
                $attr = $this->attrs[$attrID];
            }
            if (array_key_exists(strtolower($attrID), $this->attrs)) {
                $key = strtolower($attrID);
                $attr = $this->attrs[$key];
            }
        } elseif (array_key_exists($attrID, $this->attrs)) {
            $key = $attrID;
            $attr = $this->attrs[$attrID];
        }
        if ($key !== null) {
            unset($this->attrs[$key]);
        }
        return $attr;
    }

    /**
     * Generates the string representation of this attribute set.
     * The string consists of each attribute identifier and the contents
     * of each attribute. The contents of this string is useful
     * for debugging and is not meant to be interpreted programmatically.
     *
     * @return A non-null string listing the contents of this attribute set.
     */
    public function __toString(): string
    {
        if (count($this->attrs) == 0) {
            return "No attributes";
        } else {
            return implode(", ", array_values($this->attrs));
        }
    }

    /**
     * Determines whether this <tt>BasicAttributes</tt> is equal to another
     * <tt>Attributes</tt>
     * Two <tt>Attributes</tt> are equal if they are both instances of
     * <tt>Attributes</tt>,
     * treat the case of attribute IDs the same way, and contain the
     * same attributes. Each <tt>Attribute</tt> in this <tt>BasicAttributes</tt>
     * is checked for equality using <tt>Object.equals()</tt>, which may have
     * be overridden by implementations of <tt>Attribute</tt>).
     * If a subclass overrides <tt>equals()</tt>,
     * it should override <tt>hashCode()</tt>
     * as well so that two <tt>Attributes</tt> instances that are equal
     * have the same hash code.
     * @param obj the possibly null object to compare against.
     *
     * @return true If obj is equal to this BasicAttributes.
     * @see #hashCode
     */
    public function equals($obj = null): bool
    {
        if (($obj != null) && ($obj instanceof AttributesInterface)) {
            // Check case first
            if ($this->ignoreCase != $obj->isCaseIgnored()) {
                return false;
            }

            if ($this->size() == $obj->size()) {
                try {
                    return $this->getAll() == $obj->getAll();
                } catch (\Throwable $e) {
                    return false;
                }
                return true;
            }
        }
        return false;
    }
}
