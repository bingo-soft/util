<?php

namespace Util\Net\NameService\Dns;

use Util\Net\Naming\{
    CompositeName,
    NameInterface
};

class DnsName implements NameInterface
{
    // If non-null, the domain name represented by this DnsName.
    private $domain = "";

    // The labels of this domain name, as a list of strings.  Index 0
    // corresponds to the leftmost (least significant) label:  note that
    // this is the reverse of the ordering used by the Name interface.
    private array $labels = [];

    // The number of octets needed to carry this domain name in a DNS
    // packet.  Equal to the sum of the lengths of each label, plus the
    // number of non-root labels, plus 1.  Must remain less than 256.
    private $octets = 1;

    /*
     * Returns a new DnsName with its name components initialized to
     * the components of "n" in the range [beg,end).  Indexing is as
     * for the Name interface, with 0 being the most significant.
     */
    public function __construct(DnsName | string $n = null, ?int $beg = null, int $end = null)
    {
        if (is_string($n)) {
            $this->parse($n);
        } else {
            // Compute indexes into "labels", which has least-significant label
            // at index 0 (opposite to the convention used for "beg" and "end").
            $b = $n->size() - $end;
            $e = $n->size() - $beg;
            $this->labels = array_merge($this->labels, array_slice($n->labels, $b, $e - $b));

            if ($this->size() == $n->size()) {
                $this->domain = $n->domain;
                $this->octets = $n->octets;
            } else {
                foreach ($this->labels as $label) {
                    if (strlen($label) > 0) {
                        $this->octets += (strlen($label) + 1);
                    }
                }
            }
        }
    }

    public function __toString(): string
    {
        if (empty($this->domain)) {
            $buf = "";
            foreach ($this->labels as $label) {
                if (strlen($buf) > 0 || strlen($label) == 0) {
                    $buf .= '.';
                }
                self::escape($buf, $label);
            }
            $this->domain = $buf;
        }
        return $this->domain;
    }

    /**
     * Does this domain name follow <em>host name</em> syntax?
     */
    public function isHostName(): bool
    {
        foreach ($this->labels as $label) {
            if (!self::isHostNameLabel($label)) {
                return false;
            }
        }
        return true;
    }

    public function getOctets(): int
    {
        return $this->octets;
    }

    public function size(): int
    {
        return count($this->labels);
    }

    public function isEmpty(): bool
    {
        return ($this->size() == 0);
    }

    public function equals($obj = null): bool
    {
        if (!($obj instanceof NameInterface) || ($obj instanceof CompositeName)) {
            return false;
        }
        return (($this->size() == $obj->size()) &&         // shortcut:  do sizes differ?
                ($this->compareTo($obj) == 0));
    }

    public function compareTo($obj = null): int
    {
        return $this->compareRange(0, $this->size(), $obj);      // never 0 if sizes differ
    }

    public function startsWith(NameInterface $n): bool
    {
        return (($this->size() >= $n->size()) &&
                ($this->compareRange(0, $n->size(), $n) == 0));
    }

    public function endsWith(NameInterface $n): bool
    {
        return (($this->size() >= $n->size()) &&
                ($this->compareRange($this->size() - $n->size(), $this->size(), $n) == 0));
    }

    public function get(int $pos): ?string
    {
        if ($pos < 0 || $pos >= $this->size()) {
            throw new \Exception("Array index out of bounds");
        }
        $i = $this->size() - $pos - 1;       // index of "pos" component in "labels"
        return $this->labels[$i];
    }

    public function getAll(): array
    {
        return $this->labels;
    }

    public function getPrefix(int $pos): NameInterface
    {
        return new DnsName($this, 0, $pos);
    }

    public function getSuffix(int $pos): NameInterface
    {
        return new DnsName($this, $pos, $this->size());
    }

    public function clone()
    {
        return new DnsName($this, 0, $this->size());
    }

    public function remove(int $pos)
    {
        if ($pos < 0 || $pos >= $this->size()) {
            throw new \Exception("Array index out of bounds");
        }
        $i = $this->size() - $pos - 1;     // index of element to remove in "labels"
        $label = $this->labels[$i];
        array_splice($this->labels, $i, 1);
        $len = strlen($label);
        if ($len > 0) {
            $this->octets -= ($len + 1);
        }
        $this->domain = null;          // invalidate "domain"
        return $label;
    }

    public function add(...$args): NameInterface
    {
        if (count($args) == 1 && is_string($args[0])) {
            return $this->add($this->size(), $args[0]);
        }

        $pos = $args[0];
        $comp = $args[1];

        if ($pos < 0 || $pos > $this->size()) {
            throw new \Exception("Array index out of bounds");
        }
        // Check for empty labels:  may have only one, and only at end.
        $len = strlen($comp);
        if (($pos > 0 && $len == 0) || ($pos == 0 && $this->hasRootLabel())) {
                throw new \Exception("Empty label must be the last label in a domain name");
        }
        // Check total name length.
        if ($len > 0) {
            if ($this->octets + $len + 1 >= 256) {
                throw new InvalidNameException("Name too long");
            }
            $this->octets += ($len + 1);
        }

        $i = $this->size() - $pos;   // index for insertion into "labels"
        self::verifyLabel($comp);
        array_splice($this->labels, $i, 0, [ $comp ]);

        $this->domain = null;          // invalidate "domain"
        return $this;
    }

    public function addAll(...$args): NameInterface
    {
        if (count($args) == 1 && $args[0] instanceof NameInterface) {
            return $this->addAll($this->size(), $args[0]);
        }
        $pos = $args[0];
        $n = $args[1];

        if ($n instanceof DnsName) {
            // "n" is a DnsName so we can insert it as a whole, rather than
            // verifying and inserting it component-by-component.
            // More code, but less work.
            $dn = $n;

            if ($dn->isEmpty()) {
                return $this;
            }
            // Check for empty labels:  may have only one, and only at end.
            if (($pos > 0 && $dn->hasRootLabel()) || ($pos == 0 && $this->hasRootLabel())) {
                    throw new \Exception("Empty label must be the last label in a domain name");
            }

            $newOctets = ($this->octets + $dn->octets - 1);
            if ($newOctets > 255) {
                throw new InvalidNameException("Name too long");
            }
            $this->octets = $newOctets;
            $i = $this->size() - $pos;       // index for insertion into "labels"
            array_splice($this->labels, $i, 0, $dn->labels);

            // Preserve "domain" if we're appending or prepending,
            // otherwise invalidate it.
            if ($this->isEmpty()) {
                $this->domain = $dn->domain;
            } elseif ($this->domain == null || $dn->domain == null) {
                //
            } elseif ($pos == 0) {
                $this->domain .= ($dn->domain == "." ? "" : ".") . $dn->domain;
            } elseif ($pos == $this->size()) {
                $this->domain = $dn->domain . (($this->domain == ".") ? "" : "." . $this->domain);
            } else {
                $this->domain = null;
            }

        } elseif ($n instanceof CompositeName) {
            //$n = (DnsName) $n;            // force ClassCastException
            throw new \Exception("Can not cast CompositeName to DnsName");
        } else {                // "n" is a compound name, but not a DnsName.
            // Add labels least-significant first:  sometimes more efficient.
            for ($i = $n->size() - 1; $i >= 0; $i -= 1) {
                $this->add($pos, $n->get($i));
            }
        }
        return $this;
    }

    public function hasRootLabel(): bool
    {
        return (!$this->isEmpty() && $this->get(0) == "");
    }

    /*
     * Helper method for public comparison methods.  Lexicographically
     * compares components of this name in the range [beg,end) with
     * all components of "n".  Indexing is as for the Name interface,
     * with 0 being the most significant.  Returns negative, zero, or
     * positive as these name components are less than, equal to, or
     * greater than those of "n".
     */
    private function compareRange(int $beg, int $end, NameInterface $n): int
    {
        if (n instanceof CompositeName) {
            //$n = (DnsName) $n;            // force ClassCastException
            throw new \Exception("Can not cast CompositeName to DnsName");
        }
        // Loop through labels, starting with most significant.
        $minSize = min($end - $beg, $n->size());
        for ($i = 0; $i < $minSize; $i += 1) {
            $label1 = $this->get($i + $beg);
            $label2 = $n->get($i);

            $j = $this->size() - ($i + $beg) - 1;     // index of label1 in "labels"
            // assert (label1 == labels.get(j));

            $c = self::compareLabels($label1, $label2);
            if ($c != 0) {
                return $c;
            }
        }
        return (($end - $beg) - $n->size());        // longer range wins
    }

    /*
     * Returns a key suitable for hashing the label at index i.
     * Indexing is as for the Name interface, with 0 being the most
     * significant.
     */
    public function getKey(int $i): string
    {
        return self::keyForLabel($this->get($i));
    }

    /*
     * Parses a domain name, setting the values of instance vars accordingly.
     */
    private function parse(string $name): void
    {
        $label = "";        // label being parsed

        for ($i = 0; $i < strlen($name); $i += 1) {
            $c = $name[$i];

            if ($c == '\\') {                    // found an escape sequence
                $c = self::getEscapedOctet($name, $i++);
                if (self::isDigit($name[$i])) {  // sequence is \DDD
                    $i += 2;                     // consume remaining digits
                }
                $label .= $c;

            } elseif ($c != '.') {              // an unescaped octet
                $label .= $c;
            } else {                            // found '.' separator
                $this->add(0, $label);       // check syntax, then add label to end of name
                $label = substr_replace($label, "", 0, $i); // clear buffer for next label
            }
        }

        // If name is neither "." nor "", the octets (zero or more)
        // from the rightmost dot onward are now added as the final
        // label of the name.  Those two are special cases in that for
        // all other domain names, the number of labels is one greater
        // than the number of dot separators.
        if ($name != "" && $name != ".") {
            $this->add(0, $label);
        }

        $this->domain = $name;          // do this last, since add() sets it to null
    }

    /*
     * Returns (as a char) the octet indicated by the escape sequence
     * at a given position within a domain name.
     * @throws InvalidNameException if a valid escape sequence is not found.
     */
    private static function getEscapedOctet(string $name, int $pos): string
    {
        try {
            // assert (name.charAt(pos) == '\\');
            $c1 = $name[++$pos];
            if (self::isDigit($c1)) {          // sequence is `\DDD'
                $c2 = $name[++$pos];
                $c3 = $name[++$pos];
                if (self::isDigit($c2) && self::isDigit($c3)) {
                    return intval($c1 . $c2 . $c3);
                } else {
                    throw new \Exception("Invalid escape sequence in " . $name);
                }
            } else {                    // sequence is `\C'
                return $c1;
            }
        } catch (\Throwable $e) {
            throw new InvalidNameException("Invalid escape sequence in " . $name);
        }
    }

    /*
     * Checks that this label is valid.
     * @throws InvalidNameException if label is not valid.
     */
    private static function verifyLabel(string $label): void
    {
        if (strlen($label) > 63) {
            throw new InvalidNameException(
                    "Label exceeds 63 octets: " . $label);
        }
        // Check for two-byte characters.
        for ($i = 0; $i < strlen($label); $i += 1) {
            $c = $label[$i];
            if ((mb_ord($c, "UTF-8") & 0xFF00) != 0) {
                throw new \Exception("Label has two-byte char: " . $label);
            }
        }
    }

    /*
     * Does this label conform to host name syntax?
     */
    private static function isHostNameLabel(string $label): bool
    {
        for ($i = 0; $i < strlen($label); $i += 1) {
            $c = $label[$i];
            if (!self::isHostNameChar($c)) {
                return false;
            }
        }
        return !(strpos($label, "-") === 0 || substr($label, -1) === "-");
    }

    private static function isHostNameChar(string $c): bool
    {
        return preg_match('/^[a-zA-Z0-9-]+$/', $c);
    }

    private static function isDigit(string $c): bool
    {
        return ctype_digit($c);
    }

    /*
     * Append a label to buf, escaping as needed.
     */
    private static function escape(string &$buf, string $label): void
    {
        for ($i = 0; $i < strlen($label); $i += 1) {
            $c = $label[$i];
            if ($c == '.' || $c == '\\') {
                $buf .= '\\';
            }
            $buf .= $c;
        }
    }

    /*
     * Compares two labels, ignoring case for ASCII values.
     * Returns negative, zero, or positive as the first label
     * is less than, equal to, or greater than the second.
     * See keyForLabel().
     */
    private static function compareLabels(string $label1, string $label2): int
    {
        return strcmp(strtolower($label1), strtolower($label2));
    }

    /*
     * Returns a key suitable for hashing a label.  Two labels map to
     * the same key iff they are equal, taking possible case-folding
     * into account.  See compareLabels().
     */
    private static function keyForLabel(string $label): string
    {
        $buf = "";
        for ($i = 0; $i < strlen($label); $i += 1) {
            $c = strtolower($label[$i]);
            $buf .= $c;
        }
        return $buf;
    }
}