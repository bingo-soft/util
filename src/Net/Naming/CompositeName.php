<?php

namespace Util\Net\Naming;

class CompositeName implements NameInterface
{
    private NameImpl $impl;

    public function __construct(...$args)
    {
        if (empty($args)) {
            $this->impl = new NameImpl([]);
        } elseif (is_array($args[0]) || is_string($args[0])) {
            $this->impl = new NameImpl([], $args[0]);
        }
    }

    public function __toString(): string
    {
        return strval($this->impl);
    }

    public function equals($obj = null): bool
    {
        return ($obj != null &&
                $obj instanceof CompositeName &&
                $this->impl->equals($obj->impl));
    }

    public function compareTo($obj = null): int
    {
        if (!($obj instanceof CompositeName)) {
            throw new \Exception("Not a CompositeName");
        }
        return $this->impl->compareTo($obj->impl);
    }

    public function clone()
    {
        return (new CompositeName($this->getAll()));
    }

    public function size(): int
    {
        return $this->impl->size();
    }

    public function isEmpty(): bool
    {
        return $this->impl->isEmpty();
    }

    public function getAll(): array
    {
        return $this->impl->getAll();
    }

    public function get(int $posn): ?string
    {
        return $this->impl->get($posn);
    }

    public function getPrefix(int $posn): NameInterface
    {
        $comps = $this->impl->getPrefix($posn);
        return (new CompositeName($comps));
    }

    public function getSuffix(int $posn): NameInterface
    {
        $comps = $this->impl->getSuffix($posn);
        return (new CompositeName($comps));
    }

    public function startsWith(NameInterface $n): bool
    {
        if ($n instanceof CompositeName) {
            return ($this->impl->startsWith($n->size(), $n->getAll()));
        } else {
            return false;
        }
    }

    public function endsWith(NameInterface $n): bool
    {
        if ($n instanceof CompositeName) {
            return ($this->impl->endsWith($n->size(), $n->getAll()));
        } else {
            return false;
        }
    }

    public function addAll(...$args): NameInterface
    {
        $this->impl->addAll(...$args);
        return $this;
    }

    public function add(...$args): NameInterface
    {
        $this->impl->add(...$args);
        return $this;
    }

    public function remove(int $posn)
    {
        return $this->impl->remove($posn);
    }
}
