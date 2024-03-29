<?php

namespace Util\Net\Naming;

class NamingManager
{
    /**
     * Package-private; used by DirectoryManager and NamingManager.
     */
    private static $objectFactoryBuilder = null;

    /**
     * The ObjectFactoryBuilder determines the policy used when
     * trying to load object factories.
     * See getObjectInstance() and class ObjectFactory for a description
     * of the default policy.
     * setObjectFactoryBuilder() overrides this default policy by installing
     * an ObjectFactoryBuilder. Subsequent object factories will
     * be loaded and created using the installed builder.
     *<p>
     * The builder can only be installed if the executing thread is allowed
     * (by the security manager's checkSetFactory() method) to do so.
     * Once installed, the builder cannot be replaced.
     *<p>
     * @param builder The factory builder to install. If null, no builder
     *                  is installed.
     * @exception SecurityException builder cannot be installed
     *          for security reasons.
     * @exception NamingException builder cannot be installed for
     *         a non-security-related reason.
     * @exception IllegalStateException If a factory has already been installed.
     * @see #getObjectInstance
     * @see ObjectFactory
     * @see ObjectFactoryBuilder
     * @see java.lang.SecurityManager#checkSetFactory
     */
    public static function setObjectFactoryBuilder(ObjectFactoryBuilderInterface $builder): void
    {
        if (self::$objectFactoryBuilder !== null) {
            throw new \Exception("ObjectFactoryBuilder already set");
        }

        self::$objectFactoryBuilder = $builder;
    }

    /**
     * Used for accessing object factory builder.
     */
    public static function getObjectFactoryBuilder(): ?ObjectFactoryBuilderInterface
    {
        return self::$objectFactoryBuilder;
    }


    /**
     * Retrieves the ObjectFactory for the object identified by a reference,
     * using the reference's factory class name and factory codebase
     * to load in the factory's class.
     * @param ref The non-null reference to use.
     * @param factoryName The non-null class name of the factory.
     * @return The object factory for the object identified by ref; null
     * if unable to load the factory.
     */
    public static function getObjectFactoryFromReference(?Reference $ref = null, ?string $factoryName = null): ?ObjectFactoryInterface
    {
        if ($factoryName !== null && class_exists($factoryName)) {
            return new $factoryName();
        }
        if ($ref !== null) {
            $clazz = $ref->getFactoryClassLocation();
            if (class_exists($clazz)) {
                return new $clazz();
            }

            $clazz = sprintf("%s\%s", $ref->getFactoryClassLocation(), $ref->getFactoryClassName());
            if (class_exists($clazz)) {
                return new $clazz();
            }
        }

        return null;
    }


    /**
     * Creates an object using the factories specified in the
     * <tt>Context.OBJECT_FACTORIES</tt> property of the environment
     * or of the provider resource file associated with <tt>nameCtx</tt>.
     *
     * @return factory created; null if cannot create
     */
    private static function createObjectFromFactories($obj, ?NameInterface $name, ?ContextInterface $nameCtx, array $environment)
    {
        $factories = ResourceManager::getFactories(ContextInterface::OBJECT_FACTORIES, $environment, $nameCtx);

        if (empty($factories)) {
            return null;
        }

        // Try each factory until one succeeds
        $factory = null;
        $answer = null;
        foreach ($factories as $factory) {
            $answer = $factory->getObjectInstance($obj, $name, $nameCtx, $environment);
            if ($answer !== null) {
                break;
            }
        }
        return $answer;
    }

    private static function getURLScheme(string $str): ?string
    {
        $colonPosn = strpos($str, ':');
        $slashPosn = strpos($str, '/');

        if ($colonPosn !== false && ($slashPosn === false || $colonPosn < $slashPosn)) {
            return substr($str, 0, $colonPosn);
        }
        return null;
    }

    /**
     * Creates an instance of an object for the specified object
     * and environment.
     * <p>
     * If an object factory builder has been installed, it is used to
     * create a factory for creating the object.
     * Otherwise, the following rules are used to create the object:
     *<ol>
     * <li>If <code>refInfo</code> is a <code>Reference</code>
     *    or <code>Referenceable</code> containing a factory class name,
     *    use the named factory to create the object.
     *    Return <code>refInfo</code> if the factory cannot be created.
     *    Under JDK 1.1, if the factory class must be loaded from a location
     *    specified in the reference, a <tt>SecurityManager</tt> must have
     *    been installed or the factory creation will fail.
     *    If an exception is encountered while creating the factory,
     *    it is passed up to the caller.
     * <li>If <tt>refInfo</tt> is a <tt>Reference</tt> or
     *    <tt>Referenceable</tt> with no factory class name,
     *    and the address or addresses are <tt>StringRefAddr</tt>s with
     *    address type "URL",
     *    try the URL context factory corresponding to each URL's scheme id
     *    to create the object (see <tt>getURLContext()</tt>).
     *    If that fails, continue to the next step.
     * <li> Use the object factories specified in
     *    the <tt>Context.OBJECT_FACTORIES</tt> property of the environment,
     *    and of the provider resource file associated with
     *    <tt>nameCtx</tt>, in that order.
     *    The value of this property is a colon-separated list of factory
     *    class names that are tried in order, and the first one that succeeds
     *    in creating an object is the one used.
     *    If none of the factories can be loaded,
     *    return <code>refInfo</code>.
     *    If an exception is encountered while creating the object, the
     *    exception is passed up to the caller.
     *</ol>
     *<p>
     * Service providers that implement the <tt>DirContext</tt>
     * interface should use
     * <tt>DirectoryManager.getObjectInstance()</tt>, not this method.
     * Service providers that implement only the <tt>Context</tt>
     * interface should use this method.
     * <p>
     * Note that an object factory (an object that implements the ObjectFactory
     * interface) must be public and must have a public constructor that
     * accepts no arguments.
     * <p>
     * The <code>name</code> and <code>nameCtx</code> parameters may
     * optionally be used to specify the name of the object being created.
     * <code>name</code> is the name of the object, relative to context
     * <code>nameCtx</code>.  This information could be useful to the object
     * factory or to the object implementation.
     *  If there are several possible contexts from which the object
     *  could be named -- as will often be the case -- it is up to
     *  the caller to select one.  A good rule of thumb is to select the
     * "deepest" context available.
     * If <code>nameCtx</code> is null, <code>name</code> is relative
     * to the default initial context.  If no name is being specified, the
     * <code>name</code> parameter should be null.
     *
     * @param refInfo The possibly null object for which to create an object.
     * @param name The name of this object relative to <code>nameCtx</code>.
     *          Specifying a name is optional; if it is
     *          omitted, <code>name</code> should be null.
     * @param nameCtx The context relative to which the <code>name</code>
     *          parameter is specified.  If null, <code>name</code> is
     *          relative to the default initial context.
     * @param environment The possibly null environment to
     *          be used in the creation of the object factory and the object.
     * @return An object created using <code>refInfo</code>; or
     *          <code>refInfo</code> if an object cannot be created using
     *          the algorithm described above.
     * @exception NamingException if a naming exception was encountered
     *  while attempting to get a URL context, or if one of the
     *          factories accessed throws a NamingException.
     * @exception Exception if one of the factories accessed throws an
     *          exception, or if an error was encountered while loading
     *          and instantiating the factory and object classes.
     *          A factory should only throw an exception if it does not want
     *          other factories to be used in an attempt to create an object.
     *  See ObjectFactory.getObjectInstance().
     * @see #getURLContext
     * @see ObjectFactory
     * @see ObjectFactory#getObjectInstance
     */
    public static function getObjectInstance($refInfo, ?NameInterface $name, ?ContextInterface $nameCtx, array $environment)
    {
        $factory = null;

        // Use builder if installed
        $builder = self::getObjectFactoryBuilder();
        if ($builder != null) {
            // builder must return non-null factory
            $factory = $builder->createObjectFactory($refInfo, $environment);
            return $factory->getObjectInstance($refInfo, $name, $nameCtx, $environment);
        }

        // Use reference if possible
        $ref = null;
        if ($refInfo instanceof Reference) {
            $ref = $refInfo;
        } elseif ($refInfo instanceof ReferenceableInterface) {
            $ref = $refInfo->getReference();
        }

        $answer = null;

        if ($ref != null) {
            $f = $ref->getFactoryClassName();
            if ($f != null) {
                // if reference identifies a factory, use exclusively

                $factory = self::getObjectFactoryFromReference($ref, $f);
                if ($factory != null) {
                    return $factory->getObjectInstance($ref, $name, $nameCtx, $environment);
                }
                // No factory found, so return original refInfo.
                // Will reach this point if factory class is not in
                // class path and reference does not contain a URL for it
                return $refInfo;
            } else {
                // if reference has no factory, check for addresses
                // containing URLs

                $answer = self::processURLAddrs($ref, $name, $nameCtx, $environment);
                if ($answer != null) {
                    return $answer;
                }
            }
        }

        // try using any specified factories
        $answer =
            self::createObjectFromFactories($refInfo, $name, $nameCtx, $environment);
        return $answer ?? $refInfo;
    }

    /*
     * Ref has no factory.  For each address of type "URL", try its URL
     * context factory.  Returns null if unsuccessful in creating and
     * invoking a factory.
     */
    public static function processURLAddrs(Reference $ref, ?NameInterface $name, ?ContextInterface $nameCtx, array $environment)
    {
        for ($i = 0; $i < $ref->size(); $i += 1) {
            $addr = $ref->get($i);
            if ($addr instanceof StringRefAddr && strtolower($addr->getType()) == "url") {
                $url = $addr->getContent();
                $answer = self::processURL($url, $name, $nameCtx, $environment);
                if ($answer != null) {
                    return $answer;
                }
            }
        }
        return null;
    }

    private static function processURL($refInfo, ?NameInterface $name, ?ContextInterface $nameCtx, array $environment)
    {
        $answer = null;

        // If refInfo is a URL string, try to use its URL context factory
        // If no context found, continue to try object factories.
        if (is_string($refInfo)) {
            $url = $refInfo;
            $scheme = self::getURLScheme($url);
            if ($scheme != null) {
                $answer = self::getURLObject($scheme, $refInfo, $name, $nameCtx, $environment);
                if ($answer != null) {
                    return $answer;
                }
            }
        }

        // If refInfo is an array of URL strings,
        // try to find a context factory for any one of its URLs.
        // If no context found, continue to try object factories.
        if (is_array($refInfo)) {
            $urls = $refInfo;
            for ($i = 0; $i < count($urls); $i += 1) {
                $scheme = self::getURLScheme($urls[$i]);
                if ($scheme != null) {
                    $answer = self::getURLObject($scheme, $refInfo, $name, $nameCtx, $environment);
                    if ($answer != null) {
                        return $answer;
                    }
                }
            }
        }
        return null;
    }


    /**
     * Retrieves a context identified by <code>obj</code>, using the specified
     * environment.
     * Used by ContinuationContext.
     *
     * @param obj       The object identifying the context.
     * @param name      The name of the context being returned, relative to
     *                  <code>nameCtx</code>, or null if no name is being
     *                  specified.
     *                  See the <code>getObjectInstance</code> method for
     *                  details.
     * @param nameCtx   The context relative to which <code>name</code> is
     *                  specified, or null for the default initial context.
     *                  See the <code>getObjectInstance</code> method for
     *                  details.
     * @param environment Environment specifying characteristics of the
     *                  resulting context.
     * @return A context identified by <code>obj</code>.
     *
     * @see #getObjectInstance
     */
    public static function getContext($obj, NameInterface $name, ContextInterface $nameCtx, array $environment): ContextInterface
    {
        $answer = null;

        if ($obj instanceof ContextInterface) {
            // %%% Ignore environment for now.  OK since method not public.
            return $obj;
        }

        $answer = self::getObjectInstance($obj, $name, $nameCtx, $environment);

        return ($answer instanceof ContextInterface)
            ? $answer
            : null;
    }

    // Used by ContinuationContext
    public static function getResolver($obj, ?NameInterface $name, ?ContextInterface $nameCtx, array $environment): ?ResolverInterface
    {
        $answer = null;

        if ($obj instanceof ResolverInterface) {
            // %%% Ignore environment for now.  OK since method not public.
            return $obj;
        }

        $answer = self::getObjectInstance($obj, $name, $nameCtx, $environment);

        return ($answer instanceof ResolverInterface)
            ? $answer
            : null;
    }


    /***************** URL Context implementations ***************/

    /**
     * Creates a context for the given URL scheme id.
     * <p>
     * The resulting context is for resolving URLs of the
     * scheme <code>scheme</code>. The resulting context is not tied
     * to a specific URL. It is able to handle arbitrary URLs with
     * the specified scheme.
     *<p>
     * The class name of the factory that creates the resulting context
     * has the naming convention <i>scheme-id</i>URLContextFactory
     * (e.g. "ftpURLContextFactory" for the "ftp" scheme-id),
     * in the package specified as follows.
     * The <tt>Context.URL_PKG_PREFIXES</tt> environment property (which
     * may contain values taken from applet parameters, system properties,
     * or application resource files)
     * contains a colon-separated list of package prefixes.
     * Each package prefix in
     * the property is tried in the order specified to load the factory class.
     * The default package prefix is "com.sun.jndi.url" (if none of the
     * specified packages work, this default is tried).
     * The complete package name is constructed using the package prefix,
     * concatenated with the scheme id.
     *<p>
     * For example, if the scheme id is "ldap", and the
     * <tt>Context.URL_PKG_PREFIXES</tt> property
     * contains "com.widget:com.wiz.jndi",
     * the naming manager would attempt to load the following classes
     * until one is successfully instantiated:
     *<ul>
     * <li>com.widget.ldap.ldapURLContextFactory
     *  <li>com.wiz.jndi.ldap.ldapURLContextFactory
     *  <li>com.sun.jndi.url.ldap.ldapURLContextFactory
     *</ul>
     * If none of the package prefixes work, null is returned.
     *<p>
     * If a factory is instantiated, it is invoked with the following
     * parameters to produce the resulting context.
     * <p>
     * <code>factory.getObjectInstance(null, environment);</code>
     * <p>
     * For example, invoking getObjectInstance() as shown above
     * on a LDAP URL context factory would return a
     * context that can resolve LDAP urls
     * (e.g. "ldap://ldap.wiz.com/o=wiz,c=us",
     * "ldap://ldap.umich.edu/o=umich,c=us", ...).
     *<p>
     * Note that an object factory (an object that implements the ObjectFactory
     * interface) must be public and must have a public constructor that
     * accepts no arguments.
     *
     * @param scheme    The non-null scheme-id of the URLs supported by the context.
     * @param environment The possibly null environment properties to be
     *           used in the creation of the object factory and the context.
     * @return A context for resolving URLs with the
     *         scheme id <code>scheme</code>;
     *  <code>null</code> if the factory for creating the
     *         context is not found.
     * @exception NamingException If a naming exception occurs while creating
     *          the context.
     * @see #getObjectInstance
     * @see ObjectFactory#getObjectInstance
     */
    public static function getURLContext(string $scheme, array $environment): ?ContextInterface
    {
        // pass in 'null' to indicate creation of generic context for scheme
        // (i.e. not specific to a URL).
        $answer = self::getURLObject($scheme, null, null, null, $environment);
        if ($answer instanceof ContextInterface) {
            return $answer;
        } else {
            return null;
        }
    }

    private static $defaultPkgPrefix = "Util\\Net\\Ndi\\Url";

    /**
     * Creates an object for the given URL scheme id using
     * the supplied urlInfo.
     * <p>
     * If urlInfo is null, the result is a context for resolving URLs
     * with the scheme id 'scheme'.
     * If urlInfo is a URL, the result is a context named by the URL.
     * Names passed to this context is assumed to be relative to this
     * context (i.e. not a URL). For example, if urlInfo is
     * "ldap://ldap.wiz.com/o=Wiz,c=us", the resulting context will
     * be that pointed to by "o=Wiz,c=us" on the server 'ldap.wiz.com'.
     * Subsequent names that can be passed to this context will be
     * LDAP names relative to this context (e.g. cn="Barbs Jensen").
     * If urlInfo is an array of URLs, the URLs are assumed
     * to be equivalent in terms of the context to which they refer.
     * The resulting context is like that of the single URL case.
     * If urlInfo is of any other type, that is handled by the
     * context factory for the URL scheme.
     * @param scheme the URL scheme id for the context
     * @param urlInfo information used to create the context
     * @param name name of this object relative to <code>nameCtx</code>
     * @param nameCtx Context whose provider resource file will be searched
     *          for package prefix values (or null if none)
     * @param environment Environment properties for creating the context
     * @see javax.naming.InitialContext
     */
    private static function getURLObject(string $scheme, $urlInfo, ?NameInterface $name, ?ContextInterface $nameCtx, array $environment)
    {

        // e.g. "ftpURLContextFactory"
        $factory = ResourceManager::getFactory(ContextInterface::URL_PKG_PREFIXES, $environment, $nameCtx,
            "\\" . ucfirst(strtolower($scheme)) . "\\" . ucfirst(strtolower($scheme)) . "URLContextFactory", self::$defaultPkgPrefix);

        if ($factory == null) {
            return null;
        }

        // Found object factory
        return $factory->getObjectInstance($urlInfo, $name, $nameCtx, $environment);
    }


    // ------------ Initial Context Factory Stuff
    private static $initctxFactoryBuilder = null;

    /**
     * Use this method for accessing self::$initctxFactoryBuilder while
     * inside an unsynchronized method.
     */
    private static function getInitialContextFactoryBuilder(): InitialContextFactoryBuilderInterface
    {
        return self::$initctxFactoryBuilder;
    }

    /**
     * Creates an initial context using the specified environment
     * properties.
     *<p>
     * If an InitialContextFactoryBuilder has been installed,
     * it is used to create the factory for creating the initial context.
     * Otherwise, the class specified in the
     * <tt>Context.INITIAL_CONTEXT_FACTORY</tt> environment property is used.
     * Note that an initial context factory (an object that implements the
     * InitialContextFactory interface) must be public and must have a
     * public constructor that accepts no arguments.
     *
     * @param env The possibly null environment properties used when
     *                  creating the context.
     * @return A non-null initial context.
     * @exception NoInitialContextException If the
     *          <tt>Context.INITIAL_CONTEXT_FACTORY</tt> property
     *         is not found or names a nonexistent
     *         class or a class that cannot be instantiated,
     *          or if the initial context could not be created for some other
     *          reason.
     * @exception NamingException If some other naming exception was encountered.
     * @see javax.naming.InitialContext
     * @see javax.naming.directory.InitialDirContext
     */
    public static function getInitialContext(?array $env = null): ContextInterface
    {
        $factory = null;

        $builder = self::getInitialContextFactoryBuilder();
        if ($builder == null) {
            // No factory installed, use property
            // Get initial context factory class name

            $className = !empty($env) && array_key_exists(ContextInterface::INITIAL_CONTEXT_FACTORY) ? $env[ContextInterface::INITIAL_CONTEXT_FACTORY] : null;
            if ($className == null) {
                $ne = new \Exception(
                    "Need to specify class name in environment or system " .
                    "property, or as an applet parameter, or in an " .
                    "application resource file:  " .
                    ContextInterface::INITIAL_CONTEXT_FACTORY
                );
                throw $ne;
            }

            try {
                $factory = new $className();
            } catch(\Throwablen $e) {
                $ne = new \Exception("Cannot instantiate class: " . $className, 0, $e);
                throw $ne;
            }
        } else {
            $factory = $builder->createInitialContextFactory($env);
        }

        return $factory->getInitialContext($env);
    }


    /**
     * Sets the InitialContextFactory builder to be builder.
     *
     *<p>
     * The builder can only be installed if the executing thread is allowed by
     * the security manager to do so. Once installed, the builder cannot
     * be replaced.
     * @param builder The initial context factory builder to install. If null,
     *                no builder is set.
     * @exception SecurityException builder cannot be installed for security
     *                  reasons.
     * @exception NamingException builder cannot be installed for
     *         a non-security-related reason.
     * @exception IllegalStateException If a builder was previous installed.
     * @see #hasInitialContextFactoryBuilder
     * @see java.lang.SecurityManager#checkSetFactory
     */
    public static function setInitialContextFactoryBuilder(InitialContextFactoryBuilderInterface $builder): void
    {
        if (self::$initctxFactoryBuilder != null) {
            throw new \Exception("InitialContextFactoryBuilder already set");
        }

        self::$initctxFactoryBuilder = $builder;
    }

    /**
     * Determines whether an initial context factory builder has
     * been set.
     * @return true if an initial context factory builder has
     *           been set; false otherwise.
     * @see #setInitialContextFactoryBuilder
     */
    public static function hasInitialContextFactoryBuilder(): bool
    {
        return (self::getInitialContextFactoryBuilder() != null);
    }

    // -----  Continuation Context Stuff

    /**
     * Constant that holds the name of the environment property into
     * which <tt>getContinuationContext()</tt> stores the value of its
     * <tt>CannotProceedException</tt> parameter.
     * This property is inherited by the continuation context, and may
     * be used by that context's service provider to inspect the
     * fields of the exception.
     *<p>
     * The value of this constant is "Util\Net\Naming\CannotProceedException".
     *
     * @see #getContinuationContext
     */
    public const CPE = "Util\Net\Naming\CannotProceedException";

    /**
     * Creates a context in which to continue a context operation.
     *<p>
     * In performing an operation on a name that spans multiple
     * namespaces, a context from one naming system may need to pass
     * the operation on to the next naming system.  The context
     * implementation does this by first constructing a
     * <code>CannotProceedException</code> containing information
     * pinpointing how far it has proceeded.  It then obtains a
     * continuation context from JNDI by calling
     * <code>getContinuationContext</code>.  The context
     * implementation should then resume the context operation by
     * invoking the same operation on the continuation context, using
     * the remainder of the name that has not yet been resolved.
     *<p>
     * Before making use of the <tt>cpe</tt> parameter, this method
     * updates the environment associated with that object by setting
     * the value of the property <a href="#CPE"><tt>CPE</tt></a>
     * to <tt>cpe</tt>.  This property will be inherited by the
     * continuation context, and may be used by that context's
     * service provider to inspect the fields of this exception.
     *
     * @param cpe
     *          The non-null exception that triggered this continuation.
     * @return A non-null Context object for continuing the operation.
     * @exception NamingException If a naming exception occurred.
     */
    public static function getContinuationContext(\Throwable $cpe): ?ContextInterface
    {
        $env = [];
        if (method_exists($cpe, 'getEnvironment')) {
            $env = $cpe->getEnvironment();
        }

        if ($env == null) {
            $env = [];
        }
        $env->put(self::CPE, $cpe);

        $cctx = new ContinuationContext($cpe, $env);
        return $cctx->getTargetContext();
    }

// ------------ State Factory Stuff

    /**
     * Retrieves the state of an object for binding.
     * <p>
     * Service providers that implement the <tt>DirContext</tt> interface
     * should use <tt>DirectoryManager.getStateToBind()</tt>, not this method.
     * Service providers that implement only the <tt>Context</tt> interface
     * should use this method.
     *<p>
     * This method uses the specified state factories in
     * the <tt>Context.STATE_FACTORIES</tt> property from the environment
     * properties, and from the provider resource file associated with
     * <tt>nameCtx</tt>, in that order.
     *    The value of this property is a colon-separated list of factory
     *    class names that are tried in order, and the first one that succeeds
     *    in returning the object's state is the one used.
     * If no object's state can be retrieved in this way, return the
     * object itself.
     *    If an exception is encountered while retrieving the state, the
     *    exception is passed up to the caller.
     * <p>
     * Note that a state factory
     * (an object that implements the StateFactory
     * interface) must be public and must have a public constructor that
     * accepts no arguments.
     * <p>
     * The <code>name</code> and <code>nameCtx</code> parameters may
     * optionally be used to specify the name of the object being created.
     * See the description of "Name and Context Parameters" in
     * {@link ObjectFactory#getObjectInstance
     *          ObjectFactory.getObjectInstance()}
     * for details.
     * <p>
     * This method may return a <tt>Referenceable</tt> object.  The
     * service provider obtaining this object may choose to store it
     * directly, or to extract its reference (using
     * <tt>Referenceable.getReference()</tt>) and store that instead.
     *
     * @param obj The non-null object for which to get state to bind.
     * @param name The name of this object relative to <code>nameCtx</code>,
     *          or null if no name is specified.
     * @param nameCtx The context relative to which the <code>name</code>
     *          parameter is specified, or null if <code>name</code> is
     *          relative to the default initial context.
     *  @param environment The possibly null environment to
     *          be used in the creation of the state factory and
     *  the object's state.
     * @return The non-null object representing <tt>obj</tt>'s state for
     *  binding.  It could be the object (<tt>obj</tt>) itself.
     * @exception NamingException If one of the factories accessed throws an
     *          exception, or if an error was encountered while loading
     *          and instantiating the factory and object classes.
     *          A factory should only throw an exception if it does not want
     *          other factories to be used in an attempt to create an object.
     *  See <tt>StateFactory.getStateToBind()</tt>.
     * @see StateFactory
     * @see StateFactory#getStateToBind
     * @see DirectoryManager#getStateToBind
     * @since 1.3
     */
    public static function getStateToBind($obj, ?NameInterface $name, ContextInterface $nameCtx, array $environment)
    {
        $factories = ResourceManager::getFactories(ContextInterface::STATE_FACTORIES, $environment, $nameCtx);

        if (empty($factories)) {
            return $obj;
        }

        // Try each factory until one succeeds
        $factory = null;
        $answer = null;
        foreach ($factories as $factory) {
            $answer = $factory->getStateToBind($obj, $name, $nameCtx, $environment);
            if (!empty($answer)) {
                break;
            }
        }

        return !empty($answer) ? $answer : $obj;
    }
}