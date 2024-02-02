<?php

namespace Util\Net\Naming;

class ResolveResult
{
    /**
     * Field containing the Object that was resolved to successfully.
     * It can be null only when constructed using a subclass.
     * Constructors should always initialize this.
     * @serial
     */
    protected $resolvedObj = null;

    /**
     * Field containing the remaining name yet to be resolved.
     * It can be null only when constructed using a subclass.
     * Constructors should always initialize this.
     * @serial
     */
    protected $remainingName = null;

    /**
      * Constructs a new instance of ResolveResult consisting of
      * the resolved object and the remaining unresolved component.
      *
      * @param robj The non-null object resolved to.
      * @param rcomp The single remaining name component that has yet to be
      *                 resolved. Cannot be null (but can be empty).
      */
    public function __construct($robj = null, $rcomp = null) {
        $this->resolvedObj = $robj;
        try {
            if (is_string($rcomp)) {
                $this->remainingName = new CompositeName($rcomp);
            } elseif (is_object($rcomp) && $rcomp instanceof NameInterface) {
                $this->setRemainingName($rcomp);
            }
        } catch (\Throwable $e) {
            // ignore; shouldn't happen
        }
    }

    /**
     * Retrieves the remaining unresolved portion of the name.
     *
     * @return The remaining unresolved portion of the name.
     *          Cannot be null but empty OK.
     * @see #appendRemainingName
     * @see #appendRemainingComponent
     * @see #setRemainingName
     */
    public function getRemainingName(): ?NameInterface
    {
        return $this->remainingName;
    }

    /**
     * Retrieves the Object to which resolution was successful.
     *
     * @return The Object to which resolution was successful. Cannot be null.
      * @see #setResolvedObj
     */
    public function getResolvedObj()
    {
        return $this->resolvedObj;
    }

    /**
      * Sets the remaining name field of this result to name.
      * A copy of name is made so that modifying the copy within
      * this ResolveResult does not affect <code>name</code> and
      * vice versa.
      *
      * @param name The name to set remaining name to. Cannot be null.
      * @see #getRemainingName
      * @see #appendRemainingName
      * @see #appendRemainingComponent
      */
    public function setRemainingName(?NameInterface $name = null): void{
        $this->remainingName = $name;
    }

    /**
      * Adds components to the end of remaining name.
      *
      * @param name The components to add. Can be null.
      * @see #getRemainingName
      * @see #setRemainingName
      * @see #appendRemainingComponent
      */
    public function appendRemainingName(?NameInterface $name = null): void
    {
        if ($name != null) {
            if ($this->remainingName != null) {
                try {
                    $this->remainingName->addAll($name);
                } catch (\Throwable $e) {
                    // ignore; shouldn't happen for composite name
                }
            } else {
                $this->remainingName = $name;
            }
        }
    }

    /**
      * Adds a single component to the end of remaining name.
      *
      * @param name The component to add. Can be null.
      * @see #getRemainingName
      * @see #appendRemainingName
      */
    public function appendRemainingComponent(?string $name): void
    {
        if ($name != null) {
            $rname = new CompositeName();
            try {
                $rname->add($name);
            } catch (\Throwable $e) {
                // ignore; shouldn't happen for empty composite name
            }
            $this->appendRemainingName($rname);
        }
    }

    /**
      * Sets the resolved Object field of this result to obj.
      *
      * @param obj The object to use for setting the resolved obj field.
      *            Cannot be null.
      * @see #getResolvedObj
      */
    public function setResolvedObj($obj): void
    {
        $this->resolvedObj = $obj;
    }
}