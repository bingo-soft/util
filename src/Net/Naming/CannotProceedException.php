<?php

namespace Util\Net\Naming;

class CannotProceedException extends NamingException
{
    /**
     * Contains the remaining unresolved part of the second
     * "name" argument to Context.rename().
     * This information necessary for
     * continuing the Context.rename() operation.
     * <p>
     * This field is initialized to null.
     * It should not be manipulated directly:  it should
     * be accessed and updated using getRemainingName() and setRemainingName().
     * @serial
     *
     * @see #getRemainingNewName
     * @see #setRemainingNewName
     */
    protected $remainingNewName = null;

    /**
     * Contains the environment
     * relevant for the Context or DirContext method that cannot proceed.
     * <p>
     * This field is initialized to null.
     * It should not be manipulated directly:  it should be accessed
     * and updated using getEnvironment() and setEnvironment().
     * @serial
     *
     * @see #getEnvironment
     * @see #setEnvironment
     */
    protected $environment = [];

    /**
     * Contains the name of the resolved object, relative
     * to the context <code>altNameCtx</code>.  It is a composite name.
     * If null, then no name is specified.
     * See the <code>javax.naming.spi.ObjectFactory.getObjectInstance</code>
     * method for details on how this is used.
     * <p>
     * This field is initialized to null.
     * It should not be manipulated directly:  it should
     * be accessed and updated using getAltName() and setAltName().
     * @serial
     *
     * @see #getAltName
     * @see #setAltName
     * @see #altNameCtx
     * @see javax.naming.spi.ObjectFactory#getObjectInstance
     */
    protected $altName = null;

    /**
     * Contains the context relative to which
     * <code>altName</code> is specified.  If null, then the default initial
     * context is implied.
     * See the <code>javax.naming.spi.ObjectFactory.getObjectInstance</code>
     * method for details on how this is used.
     * <p>
     * This field is initialized to null.
     * It should not be manipulated directly:  it should
     * be accessed and updated using getAltNameCtx() and setAltNameCtx().
     * @serial
     *
     * @see #getAltNameCtx
     * @see #setAltNameCtx
     * @see #altName
     * @see javax.naming.spi.ObjectFactory#getObjectInstance
     */
    protected $altNameCtx = null;

    /**
     * Constructs a new instance of CannotProceedException using an
     * explanation. All unspecified fields default to null.
     *
     * @param   explanation     A possibly null string containing additional
     *                          detail about this exception.
     *   If null, this exception has no detail message.
     * @see java.lang.Throwable#getMessage
     */
    public function __construct(?string $explanation = null)
    {
        parent::__construct($explanation);
    }

    /**
     * Retrieves the environment that was in effect when this exception
     * was created.
     * @return Possibly null environment property set.
     *          null means no environment was recorded for this exception.
     * @see #setEnvironment
     */
    public function getEnvironment(): array
    {
        return $this->environment;
    }

    /**
     * Sets the environment that will be returned when getEnvironment()
     * is called.
     * @param environment A possibly null environment property set.
     *          null means no environment is being recorded for
     *          this exception.
     * @see #getEnvironment
     */
    public function setEnvironment(array $environment): void
    {
        $this->environment = $environment; // %%% clone it??
    }

    /**
     * Retrieves the "remaining new name" field of this exception, which is
     * used when this exception is thrown during a rename() operation.
     *
     * @return The possibly null part of the new name that has not been resolved.
     *          It is a composite name. It can be null, which means
     *          the remaining new name field has not been set.
     *
     * @see #setRemainingNewName
     */
    public function getRemainingNewName(): ?NameInterface
    {
        return $this->remainingNewName;
    }

    /**
     * Sets the "remaining new name" field of this exception.
     * This is the value returned by <code>getRemainingNewName()</code>.
     *<p>
     * <tt>newName</tt> is a composite name. If the intent is to set
     * this field using a compound name or string, you must
     * "stringify" the compound name, and create a composite
     * name with a single component using the string. You can then
     * invoke this method using the resulting composite name.
     *<p>
     * A copy of <code>newName</code> is made and stored.
     * Subsequent changes to <code>name</code> does not
     * affect the copy in this NamingException and vice versa.
     *
     * @param newName The possibly null name to set the "remaining new name" to.
     *          If null, it sets the remaining name field to null.
     *
     * @see #getRemainingNewName
     */
    public function setRemainingNewName(?NameInterface $newName = null): void
    {
        $this->remainingNewName = $newName;
    }

    /**
     * Retrieves the <code>altName</code> field of this exception.
     * This is the name of the resolved object, relative to the context
     * <code>altNameCtx</code>. It will be used during a subsequent call to the
     * <code>javax.naming.spi.ObjectFactory.getObjectInstance</code> method.
     *
     * @return The name of the resolved object, relative to
     *          <code>altNameCtx</code>.
     *          It is a composite name.  If null, then no name is specified.
     *
     * @see #setAltName
     * @see #getAltNameCtx
     * @see javax.naming.spi.ObjectFactory#getObjectInstance
     */
    public function getAltName(): ?NameInterface
    {
        return $this->altName;
    }

    /**
     * Sets the <code>altName</code> field of this exception.
     *
     * @param altName   The name of the resolved object, relative to
     *                  <code>altNameCtx</code>.
     *                  It is a composite name.
     *                  If null, then no name is specified.
     *
     * @see #getAltName
     * @see #setAltNameCtx
     */
    public function setAltName(NameInterface $altName): void
    {
        $this->altName = $altName;
    }

    /**
     * Retrieves the <code>altNameCtx</code> field of this exception.
     * This is the context relative to which <code>altName</code> is named.
     * It will be used during a subsequent call to the
     * <code>javax.naming.spi.ObjectFactory.getObjectInstance</code> method.
     *
     * @return  The context relative to which <code>altName</code> is named.
     *          If null, then the default initial context is implied.
     *
     * @see #setAltNameCtx
     * @see #getAltName
     * @see javax.naming.spi.ObjectFactory#getObjectInstance
     */
    public function getAltNameCtx(): ?ContextInterface
    {
        return $this->altNameCtx;
    }

    /**
     * Sets the <code>altNameCtx</code> field of this exception.
     *
     * @param altNameCtx
     *                  The context relative to which <code>altName</code>
     *                  is named.  If null, then the default initial context
     *                  is implied.
     *
     * @see #getAltNameCtx
     * @see #setAltName
     */
    public function setAltNameCtx(ContextInterface $altNameCtx): void
    {
        $this->altNameCtx = $altNameCtx;
    }
}
