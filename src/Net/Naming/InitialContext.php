<?php

namespace Util\Net\Naming;

class InitialContext implements ContextInterface
{
    /**
     * The environment associated with this InitialContext.
     * It is initialized to null and is updated by the constructor
     * that accepts an environment or by the <tt>init()</tt> method.
     * @see #addToEnvironment
     * @see #removeFromEnvironment
     * @see #getEnvironment
     */
    protected $myProps = [];

    /**
     * Field holding the result of calling NamingManager.getInitialContext()->
     * It is set by getDefaultInitCtx() the first time getDefaultInitCtx()
     * is called. Subsequent invocations of getDefaultInitCtx() return
     * the value of defaultInitCtx.
     * @see #getDefaultInitCtx
     */
    protected $defaultInitCtx = null;

    /**
     * Field indicating whether the initial context has been obtained
     * by calling NamingManager.getInitialContext()->
     * If true, its result is in <code>defaultInitCtx</code>.
     */
    protected bool $gotDefault = false;

    /**
     * Constructs an initial context.
     */
    public function __construct(bool | array $arg)
    {
        if (is_bool($arg) && $arg === false) {
            $this->init([]);
        } elseif (is_array($arg)) {
            $this->init($arg);
        }
    }

    /**
     * Initializes the initial context using the supplied environment.
     * Environment properties are discussed in the class description.
     *
     * <p> This method will modify <tt>environment</tt> and save
     * a reference to it.  The caller may no longer modify it.
     *
     * @param environment
     *          environment used to create the initial context.
     *          Null indicates an empty environment.
     *
     * @throws  NamingException if a naming exception is encountered
     *
     * @see #InitialContext(boolean)
     * @since 1.3
     */
    protected function init(array $environment = []): void
    {
        $this->myProps = ResourceManager::getInitialEnvironment($environment);

        if (array_key_exists(ContextInterface::INITIAL_CONTEXT_FACTORY, $this->myProps)) {
            // user has specified initial context factory; try to get it
            $this->getDefaultInitCtx();
        }
    }

    /**
     * A static method to retrieve the named object.
     * This is a shortcut method equivalent to invoking:
     * <p>
     * <code>
     *        InitialContext ic = new InitialContext();
     *        Object obj = ic.lookup();
     * </code>
     * <p> If <tt>name</tt> is empty, returns a new instance of this context
     * (which represents the same naming context as this context, but its
     * environment may be modified independently and it may be accessed
     * concurrently)->
     *
     * @param <T> the type of the returned object
     * @param name
     *          the name of the object to look up
     * @return  the object bound to <tt>name</tt>
     * @throws  NamingException if a naming exception is encountered
     *
     * @see #doLookup(String)
     * @see #lookup(Name)
     * @since 1.6
     */
    public static function doLookup(NameInterface | string $name)
    {
        return (new InitialContext())->lookup($name);
    }

    private static function getURLScheme(string $str): ?string
    {
        $colonPosn = strpos($str, ':');
        $slashPosn = strpos($str, '/');

        if ($colonPosn > 0 && ($slashPosn === false || $colonPosn < $slashPosn)) {
            return substr($str, 0, $colonPosn);
        }
        return null;
    }

    /**
     * Retrieves the initial context by calling
     * <code>NamingManager.getInitialContext()</code>
     * and cache it in defaultInitCtx.
     * Set <code>gotDefault</code> so that we know we've tried this before.
     * @return The non-null cached initial context.
     * @exception NoInitialContextException If cannot find an initial context.
     * @exception NamingException If a naming exception was encountered.
     */
    protected function getDefaultInitCtx(): ContextInterface
    {
        if (!$this->gotDefault) {
            $this->defaultInitCtx = NamingManager::getInitialContext($this->myProps);
            $this->gotDefault = true;
        }
        if ($this->defaultInitCtx == null) {
            throw new \Exception("No initial context");
        }

        return $this->defaultInitCtx;
    }

    /**
     * Retrieves a context for resolving the string name <code>name</code>.
     * If <code>name</code> name is a URL string, then attempt
     * to find a URL context for it. If none is found, or if
     * <code>name</code> is not a URL string, then return
     * <code>getDefaultInitCtx()</code>.
     *<p>
     * See getURLOrDefaultInitCtx(Name) for description
     * of how a subclass should use this method.
     * @param name The non-null name for which to get the context.
     * @return A URL context for <code>name</code> or the cached
     *         initial context. The result cannot be null.
     * @exception NoInitialContextException If cannot find an initial context.
     * @exception NamingException In a naming exception is encountered.
     * @see javax.naming.spi.NamingManager#getURLContext
     */
    protected function getURLOrDefaultInitCtx(NameInterface | string $name): ContextInterface
    {
        if (NamingManager::hasInitialContextFactoryBuilder()) {
            return $this->getDefaultInitCtx();
        }
        if (is_string($name)) {
            $scheme = self::getURLScheme($name);
            if ($scheme != null) {
                $ctx = NamingManager::getURLContext($scheme, $this->myProps);
                if ($ctx != null) {
                    return $ctx;
                }
            }
        } else {
            if ($name->size() > 0) {
                $first = $name->get(0);
                $scheme = self::getURLScheme($first);
                if ($scheme != null) {
                    $ctx = NamingManager::getURLContext($scheme, $this->myProps);
                    if ($ctx != null) {
                        return $ctx;
                    }
                }
            }
        }
        return $this->getDefaultInitCtx();
    }

    
    // Context methods
    public function lookup(NameInterface | string $name)
    {
        return $this->getURLOrDefaultInitCtx($name)->lookup($name);
    }

    public function bind(NameInterface | string $name, $obj, $attrs = null): void
    {
        $this->getURLOrDefaultInitCtx($name)->bind($name, $obj);
    }

    public function rebind(NameInterface | string $name, $obj, $attrs = null): void
    {
        $this->getURLOrDefaultInitCtx($name)->rebind($name, $obj);
    }

    public function unbind(NameInterface | string $name): void
    {
        $this->getURLOrDefaultInitCtx($name)->unbind($name);
    }

    public function rename(NameInterface | string $oldName, NameInterface | string $newName): void
    {
        $this->getURLOrDefaultInitCtx($oldName)->rename($oldName, $newName);
    }

    public function list(NameInterface | string $name): array
    {
        return $this->getURLOrDefaultInitCtx($name)->list($name);
    }

    public function listBindings(NameInterface | string $name): array
    {
        return $this->getURLOrDefaultInitCtx($name)->listBindings($name);
    }

    public function destroySubcontext(NameInterface | string $name): void
    {
        $this->getURLOrDefaultInitCtx($name)->destroySubcontext($name);
    }

    public function createSubcontext(NameInterface | string $name): ContextInterface
    {
        return $this->getURLOrDefaultInitCtx($name)->createSubcontext($name);
    }

    public function lookupLink(NameInterface | string $name)
    {
        return $this->getURLOrDefaultInitCtx($name)->lookupLink($name);
    }

    public function getNameParser(NameInterface | string $name): NameParserInterface
    {
        return $this->getURLOrDefaultInitCtx($name)->getNameParser($name);
    }

    /**
     * Composes the name of this context with a name relative to
     * this context.
     * Since an initial context may never be named relative
     * to any context other than itself, the value of the
     * <tt>prefix</tt> parameter must be an empty name.
     */
    public function composeName(NameInterface | string $name, NameInterface | string $prefix)
    {
        if ($name instanceof NameInterface && method_exists($name, 'clone')) {
            return $name->clone();
        }
        return $name;
    }

    public function addToEnvironment(string $propName, $propVal)
    {
        $this->myProps[$propName] = $propVal;
        return $this->getDefaultInitCtx()->addToEnvironment($propName, $propVal);
    }

    public function removeFromEnvironment(string $propName)
    {
        if (array_key_exists($propName, $this->myProps)) {
            unset($this->myProps[$propName]);
        }
        return $this->getDefaultInitCtx()->removeFromEnvironment($propName);
    }

    public function getEnvironment(): array
    {
        return $this->getDefaultInitCtx()->getEnvironment();
    }

    public function close(): void
    {
        $this->myProps = [];
        if ($this->defaultInitCtx != null) {
            $this->defaultInitCtx->close();
            $this->defaultInitCtx = null;
        }
        $this->gotDefault = false;
    }

    public function getNameInNamespace(): string
    {
        return $this->getDefaultInitCtx()->getNameInNamespace();
    }
}
