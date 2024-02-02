<?php

namespace Util\Net\Naming\Directory;

interface AttributesInterface
{
    /**
    * Determines whether the attribute set ignores the case of
    * attribute identifiers when retrieving or adding attributes.
    * @return true if case is ignored; false otherwise.
    */
    public function isCaseIgnored(): bool;

    /**
    * Retrieves the number of attributes in the attribute set.
    *
    * @return The nonnegative number of attributes in this attribute set.
    */
    public function size(): int;

    /**
    * Retrieves the attribute with the given attribute id from the
    * attribute set.
    *
    * @param attrID The non-null id of the attribute to retrieve.
    *           If this attribute set ignores the character
    *           case of its attribute ids, the case of attrID
    *           is ignored.
    * @return The attribute identified by attrID; null if not found.
    * @see #put
    * @see #remove
    */
    public function get(string $attrID): ?AttributeInterface;

    /**
    * Retrieves an enumeration of the attributes in the attribute set.
    * The effects of updates to this attribute set on this enumeration
    * are undefined.
    *
    * @return A non-null enumeration of the attributes in this attribute set.
    *         Each element of the enumeration is of class <tt>Attribute</tt>.
    *         If attribute set has zero attributes, an empty enumeration
    *         is returned.
    */
    public function getAll(): array;

    /**
    * Retrieves an enumeration of the ids of the attributes in the
    * attribute set.
    * The effects of updates to this attribute set on this enumeration
    * are undefined.
    *
    * @return A non-null enumeration of the attributes' ids in
    *         this attribute set. Each element of the enumeration is
    *         of class String.
    *         If attribute set has zero attributes, an empty enumeration
    *         is returned.
    */
    public function getIDs(): array;

    /**
    * Adds a new attribute to the attribute set.
    *
    * @param attrID   non-null The id of the attribute to add.
    *           If the attribute set ignores the character
    *           case of its attribute ids, the case of attrID
    *           is ignored.
    * @param val      The possibly null value of the attribute to add.
    *                 If null, the attribute does not have any values.
    * @return The Attribute with attrID that was previous in this attribute set;
    *         null if no such attribute existed.
    * @see #remove
    */
    public function put(...$args): ?AttributeInterface;

    /**
    * Removes the attribute with the attribute id 'attrID' from
    * the attribute set. If the attribute does not exist, ignore.
    *
    * @param attrID   The non-null id of the attribute to remove.
    *                 If the attribute set ignores the character
    *                 case of its attribute ids, the case of
    *                 attrID is ignored.
    * @return The Attribute with the same ID as attrID that was previous
    *         in the attribute set;
    *         null if no such attribute existed.
    */
    public function remove(string $attrID): ?AttributeInterface;

    /**
    * Makes a copy of the attribute set.
    * The new set contains the same attributes as the original set:
    * the attributes are not themselves cloned.
    * Changes to the copy will not affect the original and vice versa.
    *
    * @return A non-null copy of this attribute set.
    */
    public function clone();
}
