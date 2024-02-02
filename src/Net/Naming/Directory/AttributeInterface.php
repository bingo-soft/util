<?php

namespace Util\Net\Naming\Directory;

interface AttributeInterface
{
    /**
    * Retrieves an enumeration of the attribute's values.
    * The behaviour of this enumeration is unspecified
    * if the attribute's values are added, changed,
    * or removed while the enumeration is in progress.
    * If the attribute values are ordered, the enumeration's items
    * will be ordered.
    *
    * @return A non-null enumeration of the attribute's values.
    * Each element of the enumeration is a possibly null Object. The object's
    * class is the class of the attribute value. The element is null
    * if the attribute's value is null.
    * If the attribute has zero values, an empty enumeration
    * is returned.
    * @exception NamingException
    *         If a naming exception was encountered while retrieving
    *         the values.
    * @see #isOrdered
    */
    public function getAll(): array;

    /**
    * Retrieves one of this attribute's values.
    * If the attribute has more than one value and is unordered, any one of
    * the values is returned.
    * If the attribute has more than one value and is ordered, the
    * first value is returned.
    *
    * @return A possibly null object representing one of
    *        the attribute's value. It is null if the attribute's value
    *        is null.
    * @exception NamingException
    *         If a naming exception was encountered while retrieving
    *         the value.
    * @exception java.util.NoSuchElementException
    *         If this attribute has no values.
    */
    public function get(int $x = null);

    /**
    * Retrieves the number of values in this attribute.
    *
    * @return The nonnegative number of values in this attribute.
    */
    public function size(): int;

    /**
    * Retrieves the id of this attribute.
    *
    * @return The id of this attribute. It cannot be null.
    */
    public function getID(): ?string;

    /**
    * Determines whether a value is in the attribute.
    * Equality is determined by the implementation, which may use
    * <tt>Object.equals()</tt> or schema information to determine equality.
    *
    * @param attrVal The possibly null value to check. If null, check
    *  whether the attribute has an attribute value whose value is null.
    * @return true if attrVal is one of this attribute's values; false otherwise.
    * @see java.lang.Object#equals
    * @see BasicAttribute#equals
    */
    public function contains($attrVal): bool;

    /**
    * Adds a new value to the attribute.
    * If the attribute values are unordered and
    * <tt>attrVal</tt> is already in the attribute, this method does nothing.
    * If the attribute values are ordered, <tt>attrVal</tt> is added to the end of
    * the list of attribute values.
    *<p>
    * Equality is determined by the implementation, which may use
    * <tt>Object.equals()</tt> or schema information to determine equality.
    *
    * @param attrVal The new possibly null value to add. If null, null
    *  is added as an attribute value.
    * @return true if a value was added; false otherwise.
    */
    public function add(...$args): bool;

    /**
    * Removes a specified value from the attribute.
    * If <tt>attrval</tt> is not in the attribute, this method does nothing.
    * If the attribute values are ordered, the first occurrence of
    * <tt>attrVal</tt> is removed and attribute values at indices greater
    * than the removed
    * value are shifted up towards the head of the list (and their indices
    * decremented by one).
    *<p>
    * Equality is determined by the implementation, which may use
    * <tt>Object.equals()</tt> or schema information to determine equality.
    *
    * @param attrval The possibly null value to remove from this attribute.
    * If null, remove the attribute value that is null.
    * @return true if the value was removed; false otherwise.
    */
    public function remove($attrval);

    /**
    * Removes all values from this attribute.
    */
    public function clear(): void;

    /**
    * Retrieves the syntax definition associated with the attribute.
    * An attribute's syntax definition specifies the format
    * of the attribute's value(s). Note that this is different from
    * the attribute value's representation as a Java object. Syntax
    * definition refers to the directory's notion of <em>syntax</em>.
    *<p>
    * For example, even though a value might be
    * a Java String object, its directory syntax might be "Printable String"
    * or "Telephone Number". Or a value might be a byte array, and its
    * directory syntax is "JPEG" or "Certificate".
    * For example, if this attribute's syntax is "JPEG",
    * this method would return the syntax definition for "JPEG".
    * <p>
    * The information that you can retrieve from a syntax definition
    * is directory-dependent.
    *<p>
    * If an implementation does not support schemas, it should throw
    * OperationNotSupportedException. If an implementation does support
    * schemas, it should define this method to return the appropriate
    * information.
    * @return The attribute's syntax definition. Null if the implementation
    *    supports schemas but this particular attribute does not have
    *    any schema information.
    * @exception OperationNotSupportedException If getting the schema
    *         is not supported.
    * @exception NamingException If a naming exception occurs while getting
    *         the schema.
    */
    public function getAttributeSyntaxDefinition(): ?DirContextInterface;

    /**
    * Retrieves the attribute's schema definition.
    * An attribute's schema definition contains information
    * such as whether the attribute is multivalued or single-valued,
    * the matching rules to use when comparing the attribute's values.
    *
    * The information that you can retrieve from an attribute definition
    * is directory-dependent.
    *
    *<p>
    * If an implementation does not support schemas, it should throw
    * OperationNotSupportedException. If an implementation does support
    * schemas, it should define this method to return the appropriate
    * information.
    * @return This attribute's schema definition. Null if the implementation
    *     supports schemas but this particular attribute does not have
    *     any schema information.
    * @exception OperationNotSupportedException If getting the schema
    *         is not supported.
    * @exception NamingException If a naming exception occurs while getting
    *         the schema.
    */
    public function getAttributeDefinition(): ?DirContextInterface;

    /**
    * Makes a copy of the attribute.
    * The copy contains the same attribute values as the original attribute:
    * the attribute values are not themselves cloned.
    * Changes to the copy will not affect the original and vice versa.
    *
    * @return A non-null copy of the attribute.
    */
    public function clone();

    //----------- Methods to support ordered multivalued attributes

    /**
    * Determines whether this attribute's values are ordered.
    * If an attribute's values are ordered, duplicate values are allowed.
    * If an attribute's values are unordered, they are presented
    * in any order and there are no duplicate values.
    * @return true if this attribute's values are ordered; false otherwise.
    * @see #get(int)
    * @see #remove(int)
    * @see #add(int, java.lang.Object)
    * @see #set(int, java.lang.Object)
    */
    public function isOrdered(): bool;

    /**
    * Sets an attribute value in the ordered list of attribute values.
    * This method sets the value at the <tt>ix</tt> index of the list of
    * attribute values to be <tt>attrVal</tt>. The old value is removed.
    * If the attribute values are unordered,
    * this method sets the value that happens to be at that index
    * to <tt>attrVal</tt>, unless <tt>attrVal</tt> is already one of the values.
    * In that case, <tt>IllegalStateException</tt> is thrown.
    *
    * @param ix The index of the value in the ordered list of attribute values.
    * {@code 0 <= ix < size()}.
    * @param attrVal The possibly null attribute value to use.
    * If null, 'null' replaces the old value.
    * @return The possibly null attribute value at index ix that was replaced.
    *   Null if the attribute value was null.
    * @exception IndexOutOfBoundsException If <tt>ix</tt> is outside the specified range.
    * @exception IllegalStateException If <tt>attrVal</tt> already exists and the
    *    attribute values are unordered.
    */
    public function set(int $ix, $attrVal);
}
