<?php

namespace Util\Net\NameService\Dns;

class NameNode
{
    private $label;               // name of this node relative to its
                                        // parent, or null for root of a tree
    private array $children = [];  // child nodes
    private bool $isZoneCut = false;  // true if this node is a zone cut
    private int $depth = 0;              // depth in tree (0 for root)

    public function __construct(string $label)
    {
        $this->label = $label;
    }

    /*
     * Returns a newly-allocated NameNode.  Used to allocate new nodes
     * in a tree.  Should be overridden in a subclass to return an object
     * of the subclass's type.
     */
    protected function newNameNode(string $label): NameNode
    {
        return new NameNode($label);
    }

    /*
     * Returns the name of this node relative to its parent, or null for
     * the root of a tree.
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /*
     * Returns the depth of this node in the tree.  The depth of the root
     * is 0.
     */
    public function depth(): int
    {
        return $this->depth;
    }

    public function isZoneCut(): bool
    {
        return $this->isZoneCut;
    }

    public function setZoneCut(bool $isZoneCut): void
    {
        $this->isZoneCut = $isZoneCut;
    }

    /*
     * Returns the children of this node, or null if there are none.
     * The caller must not modify the Hashtable returned.
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /*
     * Returns the child node given the hash key (the down-cased label)
     * for its name relative to this node, or null if there is no such
     * child.
     */
    public function get(DnsName | string $key, ?int $idx = null): ?NameNode
    {
        if (is_string($key)) {
            return (!empty($this->children)) && array_key_exists($key, $this->children)
                ? $this->children[$key]
                : null;
        } elseif (is_object($key) && $key instanceof DnsName) {
            $node = $this;
            for ($i = $idx; $i < $key->size() && $node != null; $i += 1) {
                $node = $node->get($key->getKey($i));
            }
            return $node;
        }
        return null;
    }

    /*
     * Returns the node at the end of a path, creating it and any
     * intermediate nodes as needed.
     * The path is specified by the labels of <tt>name</tt>, beginning
     * at index idx.
     */
    public function add(DnsName $name, int $idx): NameNode
    {
        $node = $this;
        for ($i = $idx; $i < $name->size(); $i += 1) {
            $label = $name->get($i);
            $key = $name->getKey($i);

            $child = null;
            if (array_key_exists($key, $node->children)) {
                $child = $node->children[$key];
            }

            if ($child == null) {
                $child = $this->newNameNode($label);
                $child->depth = $node->depth + 1;
                $node->children[$key] = $child;
            }
            $node = $child;
        }
        return $node;
    }
}
