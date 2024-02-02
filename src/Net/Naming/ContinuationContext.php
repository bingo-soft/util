<?php

namespace Util\Net\Naming;

class ContinuationContext implements ContextInterface, ResolverInterface
{
    protected $cpe;
    protected $env;
    protected $contCtx = null;

    public function __construct(CannotProceedException $cpe, array $env)
    {
        $this->cpe = $cpe;
        $this->env = $env;
    }

    protected function getTargetContext(): ?ContextInterface
    {
        if ($this->contCtx == null) {
            if ($this->cpe->getResolvedObj() == null) {
                throw new NamingException($cpe->getTraceAsString());
            }

            $this->contCtx = NamingManager::getContext(
                $this->cpe->getResolvedObj(),
                $this->cpe->getAltName(),
                $this->cpe->getAltNameCtx(),
                $this->env
            );
            if ($this->contCtx == null) {
                throw new NamingException($cpe->getTraceAsString());
            }
        }
        return $this->contCtx;
    }

    public function lookup(NameInterface | string $name)
    {
        $ctx = $this->getTargetContext();
        return $ctx->lookup($name);
    }

    public function bind(NameInterface | string $name, $newObj): void
    {
        $ctx = $this->getTargetContext();
        $ctx->bind($name, $newObj);
    }

    public function rebind(NameInterface | string $name, $newObj): void
    {
        $ctx = $this->getTargetContext();
        $ctx->rebind($name, $newObj);
    }

    public function unbind(NameInterface | string $name, $newObj): void
    {
        $ctx = $this->getTargetContext();
        $ctx->unbind($name, $newObj);
    }

    public function rename(NameInterface | string $name, $newObj): void
    {
        $ctx = $this->getTargetContext();
        $ctx->rename($name, $newObj);
    }

    public function list(NameInterface | string $name): array
    {
        $ctx = $this->getTargetContext();
        return $ctx->list($name);
    }

    public function listBindings(NameInterface | string $name): array
    {
        $ctx = $this->getTargetContext();
        return $ctx->listBindings($name);
    }

    public function destroySubcontext(NameInterface | string $name): void
    {
        $ctx = $this->getTargetContext();
        $ctx->destroySubcontext($name);
    }

    public function createSubcontext(NameInterface | string $name): ContextInterface
    {
        $ctx = $this->getTargetContext();
        return $ctx->createSubcontext($name);
    }

    public function lookupLink(NameInterface | string $name)
    {
        $ctx = $this->getTargetContext();
        return $ctx->lookupLink($name);
    }

    public function getNameParser(NameInterface | string $name): NameParserInterface
    {
        $ctx = $this->getTargetContext();
        return $ctx->getNameParser($name);
    }

    public function composeName(NameInterface | string $name, string $prefix): string
    {
        $ctx = $this->getTargetContext();
        return $ctx->composeName($name, $prefix);
    }

    public function addToEnvironment(string $propName, $value)
    {
        $ctx = $this->getTargetContext();
        return $ctx->addToEnvironment($propName, $value);
    }

    public function removeFromEnvironment(string $propName)
    {
        $ctx = $this->getTargetContext();
        return $ctx->removeFromEnvironment($propName);
    }

    public function getEnvironment(): array
    {
        $ctx = $this->getTargetContext();
        return $ctx->getEnvironment();
    }

    public function getNameInNamespace(): string
    {
        $ctx = $this->getTargetContext();
        return $ctx->getNameInNamespace();
    }

    public function resolveToClass(NameInterface | string $name, string $contextType): ResolveResult
    {
        if ($this->cpe->getResolvedObj() == null) {
            throw new NamingException($this->cpe->getTraceAsString());
        }

        $res = NamingManager::getResolver(
            $this->cpe->getResolvedObj(),
            $this->cpe->getAltName(),
            $this->cpe->getAltNameCtx(),
            $this->env
        );
        if ($res == null) {
            throw new NamingException($this->cpe->getTraceAsString());
        }
        return $res->resolveToClass($name, $contextType);
    }

    public function close(): void
    {
        $this->cpe = null;
        $this->env = null;
        if ($this->contCtx != null) {
            $this->contCtx->close();
            $this->contCtx = null;
        }
    }
}
