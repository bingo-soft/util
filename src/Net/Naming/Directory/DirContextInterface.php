<?php

namespace Util\Net\Naming\Directory;

use Util\Net\Naming\{
    ContextInterface,
    NameInterface
};

interface DirContextInterface extends ContextInterface
{
    /**
     * Retrieves all of the attributes associated with a named object.
     */
    public function getAttributes(...$args): ?AttributesInterface;
    
    /**
     * This constant specifies to add an attribute with the specified values.
     * <p>
     * If attribute does not exist,
     * create the attribute.  The resulting attribute has a union of the
     * specified value set and the prior value set.
     * Adding an attribute with no value will throw
     * <code>InvalidAttributeValueException</code> if the attribute must have
     * at least  one value.  For a single-valued attribute where that attribute
     * already exists, throws <code>AttributeInUseException</code>.
     * If attempting to add more than one value to a single-valued attribute,
     * throws <code>InvalidAttributeValueException</code>.
     * <p>
     * The value of this constant is <tt>1</tt>.
     *
     * @see ModificationItem
     * @see #modifyAttributes
     */
    public const ADD_ATTRIBUTE = 1;

    /**
     * This constant specifies to replace an attribute with specified values.
     *<p>
     * If attribute already exists,
     * replaces all existing values with new specified values.  If the
     * attribute does not exist, creates it.  If no value is specified,
     * deletes all the values of the attribute.
     * Removal of the last value will remove the attribute if the attribute
     * is required to have at least one value.  If
     * attempting to add more than one value to a single-valued attribute,
     * throws <code>InvalidAttributeValueException</code>.
     * <p>
     * The value of this constant is <tt>2</tt>.
     *
     * @see ModificationItem
     * @see #modifyAttributes
     */
    public const REPLACE_ATTRIBUTE = 2;

    /**
     * This constant specifies to delete
     * the specified attribute values from the attribute.
     *<p>
     * The resulting attribute has the set difference of its prior value set
     * and the specified value set.
     * If no values are specified, deletes the entire attribute.
     * If the attribute does not exist, or if some or all members of the
     * specified value set do not exist, this absence may be ignored
     * and the operation succeeds, or a NamingException may be thrown to
     * indicate the absence.
     * Removal of the last value will remove the attribute if the
     * attribute is required to have at least one value.
     * <p>
     * The value of this constant is <tt>3</tt>.
     *
     * @see ModificationItem
     * @see #modifyAttributes
     */
    public const REMOVE_ATTRIBUTE = 3;

    /**
     * Modifies the attributes associated with a named object
     */
    public function modifyAttributes(...$args): void;

    /**
     * Binds a name to an object, along with associated attributes.
     * If <tt>attrs</tt> is null, the resulting binding will have
     * the attributes associated with <tt>obj</tt> if <tt>obj</tt> is a
     * <tt>DirContext</tt>, and no attributes otherwise.
     * If <tt>attrs</tt> is non-null, the resulting binding will have
     * <tt>attrs</tt> as its attributes; any attributes associated with
     * <tt>obj</tt> are ignored.
     *
     * @param name
     *          the name to bind; may not be empty
     * @param obj
     *          the object to bind; possibly null
     * @param attrs
     *          the attributes to associate with the binding
     *
     * @see Context#bind(Name, Object)
     * @see #rebind(Name, Object, Attributes)
     */
    public function bind(NameInterface | string $name, $obj, /*AttributesInterface*/$attrs = null): void;

    /**
     * Binds a name to an object, along with associated attributes,
     * overwriting any existing binding.
     * If <tt>attrs</tt> is null and <tt>obj</tt> is a <tt>DirContext</tt>,
     * the attributes from <tt>obj</tt> are used.
     * If <tt>attrs</tt> is null and <tt>obj</tt> is not a <tt>DirContext</tt>,
     * any existing attributes associated with the object already bound
     * in the directory remain unchanged.
     * If <tt>attrs</tt> is non-null, any existing attributes associated with
     * the object already bound in the directory are removed and <tt>attrs</tt>
     * is associated with the named object.  If <tt>obj</tt> is a
     * <tt>DirContext</tt> and <tt>attrs</tt> is non-null, the attributes
     * of <tt>obj</tt> are ignored.
     *
     * @param name
     *          the name to bind; may not be empty
     * @param obj
     *          the object to bind; possibly null
     * @param attrs
     *          the attributes to associate with the binding
     * @see Context#bind(Name, Object)
     * @see #bind(Name, Object, Attributes)
     */
    public function rebind(NameInterface | string $name, $obj, /*AttributesInterface*/$attrs = null): void;

    /**
     * Creates and binds a new context, along with associated attributes.
     * This method creates a new subcontext with the given name, binds it in
     * the target context (that named by all but terminal atomic
     * component of the name), and associates the supplied attributes
     * with the newly created object.
     * All intermediate and target contexts must already exist.
     * If <tt>attrs</tt> is null, this method is equivalent to
     * <tt>Context.createSubcontext()</tt>.
     *
     * @param name
     *          the name of the context to create; may not be empty
     * @param attrs
     *          the attributes to associate with the newly created context
     * @return  the newly created context
     */
    public function createSubcontext(NameInterface | string $name, AttributesInterface $attrs): DirContextInterface;

    // -------------------- schema operations

    /**
     * Retrieves the schema associated with the named object.
     * The schema describes rules regarding the structure of the namespace
     * and the attributes stored within it.  The schema
     * specifies what types of objects can be added to the directory and where
     * they can be added; what mandatory and optional attributes an object
     * can have. The range of support for schemas is directory-specific.
     *
     * <p> This method returns the root of the schema information tree
     * that is applicable to the named object. Several named objects
     * (or even an entire directory) might share the same schema.
     *
     * <p> Issues such as structure and contents of the schema tree,
     * permission to modify to the contents of the schema
     * tree, and the effect of such modifications on the directory
     * are dependent on the underlying directory.
     *
     * @param name
     *          the name of the object whose schema is to be retrieved
     * @return  the schema associated with the context; never null
     */
    public function getSchema(NameInterface | string $name): DirContextInterface;

    /**
     * Retrieves a context containing the schema objects of the
     * named object's class definitions.
     *<p>
     * One category of information found in directory schemas is
     * <em>class definitions</em>.  An "object class" definition
     * specifies the object's <em>type</em> and what attributes (mandatory
     * and optional) the object must/can have. Note that the term
     * "object class" being referred to here is in the directory sense
     * rather than in the Java sense.
     * For example, if the named object is a directory object of
     * "Person" class, <tt>getSchemaClassDefinition()</tt> would return a
     * <tt>DirContext</tt> representing the (directory's) object class
     * definition of "Person".
     *<p>
     * The information that can be retrieved from an object class definition
     * is directory-dependent.
     *<p>
     * Prior to JNDI 1.2, this method
     * returned a single schema object representing the class definition of
     * the named object.
     * Since JNDI 1.2, this method returns a <tt>DirContext</tt> containing
     * all of the named object's class definitions.
     *
     * @param name
     *          the name of the object whose object class
     *          definition is to be retrieved
     * @return  the <tt>DirContext</tt> containing the named
     *          object's class definitions; never null
     */
    public function getSchemaClassDefinition(NameInterface | string $name): DirContextInterface;

    // -------------------- search operations

    /**
     * Searches in a single context for objects.
     */
    public function search(...$args): array;
}
