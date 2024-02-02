<?php

namespace Util\Net\Naming;

class NamingException extends \Exception
{
    /**
     * Contains the part of the name that has been successfully resolved.
     * It is a composite name and can be null.
     * This field is initialized by the constructors.
     * You should access and manipulate this field
     * through its get and set methods.
     * @serial
     * @see #getResolvedName
     * @see #setResolvedName
     */
    protected $resolvedName = null;

    /**
      * Contains the object to which resolution of the part of the name was
      * successful. Can be null.
      * This field is initialized by the constructors.
      * You should access and manipulate this field
      * through its get and set methods.
      * @serial
      * @see #getResolvedObj
      * @see #setResolvedObj
      */
    protected $resolvedObj = null;

    /**
     * Contains the remaining name that has not been resolved yet.
     * It is a composite name and can be null.
     * This field is initialized by the constructors.
     * You should access and manipulate this field
     * through its get, set, "append" methods.
     * @serial
     * @see #getRemainingName
     * @see #setRemainingName
     * @see #appendRemainingName
     * @see #appendRemainingComponent
     */
    protected $remainingName = null;

    /**
     * Contains the original exception that caused this NamingException to
     * be thrown. This field is set if there is additional
     * information that could be obtained from the original
     * exception, or if the original exception could not be
     * mapped to a subclass of NamingException.
     * Can be null.
     *<p>
     * This field predates the general-purpose exception chaining facility.
     * The {@link #initCause(Throwable)} and {@link #getCause()} methods
     * are now the preferred means of accessing this information.
     *
     * @serial
     * @see #getRootCause
     * @see #setRootCause(Throwable)
     * @see #initCause(Throwable)
     * @see #getCause
     */
    protected $rootException = null;

    /**
     * Constructs a new NamingException with an explanation.
     * All unspecified fields are set to null.
     *
     * @param   explanation     A possibly null string containing
     *                          additional detail about this exception.
     * @see java.lang.Throwable#getMessage
     */
    public function __construct(?string $explanation = null)
    {
        parent::__construct($explanation);
    }

    /**
     * Retrieves the leading portion of the name that was resolved
     * successfully.
     *
     * @return The part of the name that was resolved successfully.
     *          It is a composite name. It can be null, which means
     *          the resolved name field has not been set.
     * @see #getResolvedObj
     * @see #setResolvedName
     */
    public function getResolvedName(): ?NameInterface
    {
        return $this->resolvedName;
    }

    /**
     * Retrieves the remaining unresolved portion of the name.
     * @return The part of the name that has not been resolved.
     *          It is a composite name. It can be null, which means
     *          the remaining name field has not been set.
     * @see #setRemainingName
     * @see #appendRemainingName
     * @see #appendRemainingComponent
     */
    public function getRemainingName(): ?NameInterface
    {
        return $this->remainingName;
    }

    /**
     * Retrieves the object to which resolution was successful.
     * This is the object to which the resolved name is bound.
     *
     * @return The possibly null object that was resolved so far.
     *  null means that the resolved object field has not been set.
     * @see #getResolvedName
     * @see #setResolvedObj
     */
    public function getResolvedObj()
    {
        return $this->resolvedObj;
    }

    /**
      * Retrieves the explanation associated with this exception.
      *
      * @return The possibly null detail string explaining more
      *         about this exception. If null, it means there is no
      *         detail message for this exception.
      *
      */
    public function getExplanation(): ?string
    {
        return $this->getMessage();
    }

    /**
     * Sets the resolved name field of this exception.
     *<p>
     * <tt>name</tt> is a composite name. If the intent is to set
     * this field using a compound name or string, you must
     * "stringify" the compound name, and create a composite
     * name with a single component using the string. You can then
     * invoke this method using the resulting composite name.
     *<p>
     * A copy of <code>name</code> is made and stored.
     * Subsequent changes to <code>name</code> does not
     * affect the copy in this NamingException and vice versa.
     *
     * @param name The possibly null name to set resolved name to.
     *          If null, it sets the resolved name field to null.
     * @see #getResolvedName
     */
    public function setResolvedName(?NameInterface $name = null): void
    {
        $this->resolvedName = $name;
    }

    /**
     * Sets the remaining name field of this exception.
     *<p>
     * <tt>name</tt> is a composite name. If the intent is to set
     * this field using a compound name or string, you must
     * "stringify" the compound name, and create a composite
     * name with a single component using the string. You can then
     * invoke this method using the resulting composite name.
     *<p>
     * A copy of <code>name</code> is made and stored.
     * Subsequent changes to <code>name</code> does not
     * affect the copy in this NamingException and vice versa.
     * @param name The possibly null name to set remaining name to.
     *          If null, it sets the remaining name field to null.
     * @see #getRemainingName
     * @see #appendRemainingName
     * @see #appendRemainingComponent
     */
    public function setRemainingName(NameInterface $name): void
    {
        $this->remainingName = $name;
    }

    /**
     * Sets the resolved object field of this exception.
     * @param obj The possibly null object to set resolved object to.
     *            If null, the resolved object field is set to null.
     * @see #getResolvedObj
     */
    public function setResolvedObj($obj = null): void
    {
        $this->resolvedObj = $obj;
    }

    /**
      * Add name as the last component in remaining name.
      * @param name The component to add.
      *         If name is null, this method does not do anything.
      * @see #setRemainingName
      * @see #getRemainingName
      * @see #appendRemainingName
      */
    public function appendRemainingComponent(?string $name): void
    {
        if ($name != null) {
            if ($this->remainingName == null) {
                $this->remainingName = new CompositeName();
            }
            $this->remainingName->add($name);
        }
    }

    /**
      * Add components from 'name' as the last components in
      * remaining name.
      *<p>
      * <tt>name</tt> is a composite name. If the intent is to append
      * a compound name, you should "stringify" the compound name
      * then invoke the overloaded form that accepts a String parameter.
      *<p>
      * Subsequent changes to <code>name</code> does not
      * affect the remaining name field in this NamingException and vice versa.
      * @param name The possibly null name containing ordered components to add.
      *                 If name is null, this method does not do anything.
      * @see #setRemainingName
      * @see #getRemainingName
      * @see #appendRemainingComponent
      */
    public function appendRemainingName(?NameInterface $name = null): void
    {
        if ($name == null) {
            return;
        }
        if (!empty($this->remainingName)) {
            $this->remainingName->addAll($name);
        } else {
            $this->remainingName = $name;
        }
    }

    /**
      * Retrieves the root cause of this NamingException, if any.
      * The root cause of a naming exception is used when the service provider
      * wants to indicate to the caller a non-naming related exception
      * but at the same time wants to use the NamingException structure
      * to indicate how far the naming operation proceeded.
      *<p>
      * This method predates the general-purpose exception chaining facility.
      * The {@link #getCause()} method is now the preferred means of obtaining
      * this information.
      *
      * @return The possibly null exception that caused this naming
      *    exception. If null, it means no root cause has been
      *    set for this naming exception.
      * @see #setRootCause
      * @see #rootException
      * @see #getCause
      */
    public function getRootCause(): ?\Throwable
    {
        return $this->rootException;
    }

    /**
      * Records the root cause of this NamingException.
      * If <tt>e</tt> is <tt>this</tt>, this method does not do anything.
      *<p>
      * This method predates the general-purpose exception chaining facility.
      * The {@link #initCause(Throwable)} method is now the preferred means
      * of recording this information.
      *
      * @param e The possibly null exception that caused the naming
      *          operation to fail. If null, it means this naming
      *          exception has no root cause.
      * @see #getRootCause
      * @see #rootException
      * @see #initCause
      */
    public function setRootCause(?\Throwable $e = null): void
    {
        if ($e != $this) {
            $this->rootException = $e;
        }
    }

    /**
      * Returns the cause of this exception.  The cause is the
      * throwable that caused this naming exception to be thrown.
      * Returns <code>null</code> if the cause is nonexistent or
      * unknown.
      *
      * @return  the cause of this exception, or <code>null</code> if the
      *          cause is nonexistent or unknown.
      * @see #initCause(Throwable)
      * @since 1.4
      */
    public function getCause(): \Throwable
    {
        return $this->getRootCause();
    }

    /**
      * Initializes the cause of this exception to the specified value.
      * The cause is the throwable that caused this naming exception to be
      * thrown.
      *<p>
      * This method may be called at most once.
      *
      * @param  cause   the cause, which is saved for later retrieval by
      *         the {@link #getCause()} method.  A <tt>null</tt> value
      *         indicates that the cause is nonexistent or unknown.
      * @return a reference to this <code>NamingException</code> instance.
      * @throws IllegalArgumentException if <code>cause</code> is this
      *         exception.  (A throwable cannot be its own cause.)
      * @throws IllegalStateException if this method has already
      *         been called on this exception.
      * @see #getCause
      * @since 1.4
      */
    public function initCause(?\Throwable $cause = null): \Throwable
    {
        $this->setRootCause($cause);
        return $this;
    }

    /**
     * Generates the string representation of this exception.
     * The string representation consists of this exception's class name,
     * its detailed message, and if it has a root cause, the string
     * representation of the root cause exception, followed by
     * the remaining name (if it is not null).
     * This string is used for debugging and not meant to be interpreted
     * programmatically.
     *
     * @return The non-null string containing the string representation
     * of this exception.
     */
    public function __toString(): string
    {
        $answer = method_exists(\Exception::__CLASS__, '__toString') ? parent::__toString() : "";

        if ($this->rootException != null) {
            $answer .= " [Root exception is " . $this->rootException . "]";
        }
        if ($this->remainingName != null) {
            $answer .= "; remaining name '" . $this->remainingName . "'";
        }
        return $answer;
    }
}
