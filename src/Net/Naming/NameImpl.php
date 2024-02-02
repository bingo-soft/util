<?php

namespace Util\Net\Naming;

class NameImpl
{
    private const LEFT_TO_RIGHT = 1;
    private const RIGHT_TO_LEFT = 2;
    private const FLAT = 0;

    private $components = [];

    private $syntaxDirection = self::LEFT_TO_RIGHT;
    private string $syntaxSeparator = "/";
    private $syntaxSeparator2 = null;
    private bool $syntaxCaseInsensitive = false;
    private bool $syntaxTrimBlanks = false;
    private string $syntaxEscape = "\\";
    private string $syntaxBeginQuote1 = "\"";
    private string $syntaxEndQuote1 = "\"";
    private string $syntaxBeginQuote2 = "'";
    private string $syntaxEndQuote2 = "'";
    private $syntaxAvaSeparator = null;
    private $syntaxTypevalSeparator = null;

    // $this->escapingStyle gives the method used at creation time for
    // quoting or escaping characters in the name.  It is set to the
    // first style of quote or escape encountered if and when the name
    // is parsed.
    private const STYLE_NONE = 0;
    private const STYLE_QUOTE1 = 1;
    private const STYLE_QUOTE2 = 2;
    private const STYLE_ESCAPE = 3;
    private $escapingStyle = self::STYLE_NONE;

    // Returns true if "match" is not null, and n contains "match" at
    // position i.
    private function isA(string $n, int $i, ?string $match): bool
    {
        return ($match != null && strpos(substr($n, $i), $match) === 0);
    }

    private function isMeta(string $n, int $i): bool
    {
        return ($this->isA($n, $i, $this->syntaxEscape) ||
                $this->isA($n, $i, $this->syntaxBeginQuote1) ||
                $this->isA($n, $i, $this->syntaxBeginQuote2) ||
                $this->isSeparator($n, $i));
    }

    private function isSeparator(string $n, int $i): bool
    {
        return ($this->isA($n, $i, $this->syntaxSeparator) ||
                $this->isA($n, $i, $this->syntaxSeparator2));
    }

    private function skipSeparator(string $name, int $i): int
    {
        if ($this->isA($name, $i, $this->syntaxSeparator)) {
            $i += strlen($this->syntaxSeparator);
        } elseif ($this->isA($name, $i, $this->syntaxSeparator2)) {
            $i += strlen($this->syntaxSeparator2);
        }
        return $i;
    }

    private function extractComp(string $name, int $i, int $len, array &$comps): int
    {
        $beginQuote = null;
        $endQuote = null;
        $start = true;
        $one = false;
        $answer = "";

        while ($i < $len) {
            // handle quoted strings
            if ($start && (($one = $this->isA($name, $i, $this->syntaxBeginQuote1)) ||
                          $this->isA($name, $i, $this->syntaxBeginQuote2))) {
                // record choice of quote chars being used
                $beginQuote = $one ? $this->syntaxBeginQuote1 : $this->syntaxBeginQuote2;
                $endQuote = $one ? $this->syntaxEndQuote1 : $this->syntaxEndQuote2;
                if ($this->escapingStyle == self::STYLE_NONE) {
                    $this->escapingStyle = $one ? self::STYLE_QUOTE1 : self::STYLE_QUOTE2;
                }

                // consume string until matching quote
                for ($i += strlen($beginQuote);
                     (($i < $len) && strpos($name, $endQuote) !== $i);
                     $i++)
                {
                    // skip escape character if it is escaping ending quote
                    // otherwise leave as is.
                    if ($this->isA($name, $i, $this->syntaxEscape) &&
                        $this->isA($name, $i + strlen($this->syntaxEscape), $endQuote)) {
                        $i += strlen($this->syntaxEscape);
                    }
                    $answer .= $name[$i];  // copy char
                }

                // no ending quote found
                if ($i >= $len) {
                    throw new \Exception($name . ": no close quote");
                }

                $i += strlen($endQuote);

                // verify that end-quote occurs at separator or end of string
                if ($i == $len || $this->isSeparator($name, $i)) {
                    break;
                }
                throw (new \Exception($name . ": close quote appears before end of component"));

            } elseif ($this->isSeparator($name, $i)) {
                break;
            } elseif ($this->isA($name, $i, $this->syntaxEscape)) {
                if ($this->isMeta($name, $i + strlen($this->syntaxEscape))) {
                    // if escape precedes meta, consume escape and let
                    // meta through
                    $i += strlen($this->syntaxEscape);
                    if ($this->escapingStyle == self::STYLE_NONE) {
                        $this->escapingStyle = self::STYLE_ESCAPE;
                    }
                } elseif ($i + strlen($this->syntaxEscape) >= $len) {
                    throw (new \Exception($name . ": unescaped " . $this->syntaxEscape . " at end of component"));
                }
            } elseif ($this->isA($name, $i, $this->syntaxTypevalSeparator) &&
        (($one = $this->isA($name, $i + strlen($this->syntaxTypevalSeparator), $this->syntaxBeginQuote1)) ||
            $this->isA($name, $i + strlen($this->syntaxTypevalSeparator), $this->syntaxBeginQuote2))) {
                // Handle quote occurring after typeval separator
                $beginQuote = $one ? $this->syntaxBeginQuote1 : $this->syntaxBeginQuote2;
                $endQuote = $one ? $this->syntaxEndQuote1 : $this->syntaxEndQuote2;

                $i += strlen($this->syntaxTypevalSeparator);
                $answer .= $this->syntaxTypevalSeparator . $beginQuote; // add back

                // consume string until matching quote
                for ($i += strlen($beginQuote);
                     (($i < $len) && strpos($name, $endQuote) !== $i);
                     $i++)
                {
                    // skip escape character if it is escaping ending quote
                    // otherwise leave as is.
                    if ($this->isA($name, $i, $this->syntaxEscape) &&
                        $this->isA($name, $i + strlen($this->syntaxEscape), $endQuote)) {
                        $i += strlen($this->syntaxEscape);
                    }
                    $answer .= $name[$i];  // copy char
                }

                // no ending quote found
                if ($i >= $len) {
                    throw new \Exception($name . ": typeval no close quote");
                }

                $i += strlen($endQuote);
                $answer .= $endQuote; // add back

                // verify that end-quote occurs at separator or end of string
                if ($i == $len || $this->isSeparator($name, $i)) {
                    break;
                }
                throw (new Exception(substr($name, $i) . ": typeval close quote appears before end of component"));
            }
            $answer .= $name[$i++];
            $start = false;
        }

        if ($this->syntaxDirection == self::RIGHT_TO_LEFT) {
            array_splice($comps, 0, 0, $answer);
        } else {
            $comps[] = $answer;
        }
        return $i;
    }

    private static function getBoolean(array $p, string $name): bool
    {
        if (array_key_exists($name, $p)) {
            $val = $p[$name];
            return strtolower($val) == 'true';
        }
        return false;
    }

    private function recordNamingConvention(array $p): void
    {
        $syntaxDirectionStr = null;
        if (array_key_exists("ndi.syntax.direction", $p)) {
            $syntaxDirectionStr = $p["ndi.syntax.direction"];
        }
        if ($syntaxDirectionStr == "left_to_right") {
            $this->syntaxDirection = self::LEFT_TO_RIGHT;
        } elseif ($syntaxDirectionStr =="right_to_left") {
            $this->syntaxDirection = self::RIGHT_TO_LEFT;
        } elseif ($syntaxDirectionStr == "flat") {
            $this->syntaxDirection = self::FLAT;
        } else {
            throw new \Exception($syntaxDirectionStr . " is not a valid value for the ndi.syntax.direction property");
        }

        if ($this->syntaxDirection != self::FLAT) {
            if (array_key_exists("ndi.syntax.separator", $p)) {
                $this->syntaxSeparator = $p["ndi.syntax.separator"];
            } else {
                $this->syntaxSeparator = null;
            }

            if (array_key_exists("ndi.syntax.separator2", $p)) {
                $this->syntaxSeparator2 = $p["ndi.syntax.separator2"];
            } else {
                $this->syntaxSeparator2 = null;
            }

            if ($this->syntaxSeparator == null) {
                throw new \Exception("ndi.syntax.separator property required for non-flat syntax");
            }
        } else {
            $this->syntaxSeparator = null;
        }
        if (array_key_exists("ndi.syntax.escape", $p)) {
            $this->syntaxEscape = $p["ndi.syntax.escape"];
        } else {
            $this->syntaxEscape = null;
        }

        $this->syntaxCaseInsensitive = self::getBoolean($p, "ndi.syntax.ignorecase");
        $this->syntaxTrimBlanks = self::getBoolean($p, "ndi.syntax.trimblanks");

        if (array_key_exists("ndi.syntax.beginquote", $p)) {
            $this->syntaxBeginQuote1 = $p["ndi.syntax.beginquote"];
        } else {
            $this->syntaxBeginQuote1 = null;
        }
        if (array_key_exists("ndi.syntax.endquote", $p)) {
            $this->syntaxEndQuote1 = $p["ndi.syntax.endquote"];
        } else {
            $this->syntaxEndQuote1 = null;
        }
        if ($this->syntaxEndQuote1 == null && $this->syntaxBeginQuote1 != null) {
            $this->syntaxEndQuote1 = $this->syntaxBeginQuote1;
        } elseif ($this->syntaxBeginQuote1 == null && $this->syntaxEndQuote1 != null) {
            $this->syntaxBeginQuote1 = $this->syntaxEndQuote1;
        }
        if (array_key_exists("ndi.syntax.beginquote2", $p)) {
            $this->syntaxBeginQuote2 = $p["ndi.syntax.beginquote2"];
        } else {
            $this->syntaxBeginQuote2 = null;
        }
        if (array_key_exists("ndi.syntax.endquote2", $p)) {
            $this->syntaxEndQuote2 = $p["ndi.syntax.endquote2"];
        } else {
            $this->syntaxEndQuote2 = null;
        }
        if ($this->syntaxEndQuote2 == null && $this->syntaxBeginQuote2 != null) {
            $this->syntaxEndQuote2 = $this->syntaxBeginQuote2;
        } elseif ($this->syntaxBeginQuote2 == null && $this->syntaxEndQuote2 != null) {
            $this->syntaxBeginQuote2 = $this->syntaxEndQuote2;
        }
        if (array_key_exists("ndi.syntax.separator.ava", $p)) {
            $this->syntaxAvaSeparator = $p["ndi.syntax.separator.ava"];
        } else {
            $this->syntaxAvaSeparator = null;
        }
        if (array_key_exists("ndi.syntax.separator.typeva", $p)) {
            $this->syntaxTypevalSeparator = $p["ndi.syntax.separator.typeva"];
        } else {
            $this->syntaxTypevalSeparator = null;
        }
    }

    public function __construct(...$args)
    {
        if (is_array($args[0]) && count($args) == 1) {
            if (!empty($args[0])) {
                $this->recordNamingConvention($args[0]);
            }
            $this->components = [];
        } elseif (is_array($args[0]) && is_string($args[1])) {
            self::__construct($args[0]);
            $n = $args[1];
            $rToL = ($this->syntaxDirection == self::RIGHT_TO_LEFT);
            $compsAllEmpty = true;
            $len = strlen($n);
    
            for ($i = 0; $i < $len; ) {
                $i = $this->extractComp($n, $i, $len, $this->components);
                if (!empty($this->components)) {
                    $comp = $rToL
                        ? $this->components[0]
                        : $this->components[count($this->components) - 1];
                } else {
                    $comp = null;
                }
                if (strlen($comp) >= 1) {
                    $compsAllEmpty = false;
                }
    
                if ($i < $len) {
                    $i = $this->skipSeparator($n, $i);
                    if (($i == $len) && !$compsAllEmpty) {
                        // Trailing separator found.  Add an empty component.
                        if ($rToL) {
                            array_splice($this->components, 0, 0, [ "" ]);
                        } else {
                            $this->components[] = "";
                        }
                    }
                }
            }
        } elseif (is_array($args[0]) && is_array($args[1])) {
            self::__construct($args[0]);
            foreach ($args[1] as $comp) {
                $this->components[] = $comp;
            }
        }
    }

    private function stringifyComp(string $comp): string
    {
        $len = strlen($comp);
        $escapeSeparator = false;
        $escapeSeparator2 = false;
        $beginQuote = null;
        $endQuote = null;
        $strbuf = "";

        // determine whether there are any separators; if so escape
        // or quote them
        if ($this->syntaxSeparator != null && strpos($comp, $this->syntaxSeparator) !== false) {
            if ($this->syntaxBeginQuote1 != null) {
                $beginQuote = $this->syntaxBeginQuote1;
                $endQuote = $this->syntaxEndQuote1;
            } elseif ($this->syntaxBeginQuote2 != null) {
                $beginQuote = $this->syntaxBeginQuote2;
                $endQuote = $this->syntaxEndQuote2;
            } elseif ($this->syntaxEscape != null) {
                $escapeSeparator = true;
            }
        }
        if ($this->syntaxSeparator2 != null && strpos($comp, $this->syntaxSeparator2) !== false) {
            if ($this->syntaxBeginQuote1 != null) {
                if ($beginQuote == null) {
                    $beginQuote = $this->syntaxBeginQuote1;
                    $endQuote = $this->syntaxEndQuote1;
                }
            } elseif ($this->syntaxBeginQuote2 != null) {
                if ($beginQuote == null) {
                    $beginQuote = $this->syntaxBeginQuote2;
                    $endQuote = $this->syntaxEndQuote2;
                }
            } elseif ($this->syntaxEscape != null) {
                $escapeSeparator2 = true;
            }
        }

        // if quoting component,
        if ($beginQuote != null) {

            // start string off with opening quote
            $strbuf .= $beginQuote;

            // component is being quoted, so we only need to worry about
            // escaping end quotes that occur in component
            for ($i = 0; $i < $len; ) {
                if (strpos($comp, $endQuote) === $i) {
                    // end-quotes must be escaped when inside a quoted string
                    $strbuf .= $this->syntaxEscape . $endQuote;
                    $i += strlen($endQuote);
                } else {
                    // no special treatment required
                    $strbuf .= $comp[$i++];
                }
            }

            // end with closing quote
            $strbuf .= $endQuote;

        } else {

            // When component is not quoted, add escape for:
            // 1. leading quote
            // 2. an escape preceding any meta char
            // 3. an escape at the end of a component
            // 4. separator

            // go through characters in component and escape where necessary
            $start = true;
            for ($i = 0; $i < $len; ) {
                // leading quote must be escaped
                if ($start && $this->isA($comp, $i, $this->syntaxBeginQuote1)) {
                    $strbuf .= $this->syntaxEscape . $this->syntaxBeginQuote1;
                    $i += strlen($this->syntaxBeginQuote1);
                } elseif ($start && $this->isA($comp, $i, $this->syntaxBeginQuote2)) {
                    $strbuf .= $this->syntaxEscape . $this->syntaxBeginQuote2;
                    $i += strlen($this->syntaxBeginQuote2);
                } elseif ($this->isA($comp, $i, $this->syntaxEscape)) {
                    if ($i + strlen($this->syntaxEscape) >= len) {
                        // escape an ending escape
                        $strbuf .= $this->syntaxEscape;
                    } elseif ($this->isMeta($comp, $i + strlen($this->syntaxEscape))) {
                        // escape meta strings
                        $strbuf .= $this->syntaxEscape;
                    }
                    $strbuf .= $this->syntaxEscape;
                    $i += strlen($this->syntaxEscape);
                } elseif ($escapeSeparator && strpos($comp, $this->syntaxSeparator) === $i) {
                    // escape separator
                    $strbuf .= $this->syntaxEscape . $this->syntaxSeparator;
                    $i += strlen($this->syntaxSeparator);
                } elseif ($escapeSeparator2 && strpos($comp, $this->syntaxSeparator2) === $i) {
                    // escape separator2
                    $strbuf .= $this->syntaxEscape . $this->syntaxSeparator2;
                    $i += strlen($this->syntaxSeparator2);
                } else {
                    // no special treatment required
                    $strbuf .= $comp[$i++];
                }
                $start = false;
            }
        }
        return $strbuf;
    }

    public function __toString(): string
    {
        $answer = "";
        $comp = null;
        $compsAllEmpty = true;
        $size = count($this->components);

        for ($i = 0; $i < $size; $i++) {
            if ($this->syntaxDirection == self::RIGHT_TO_LEFT) {
                $comp =
                    $this->stringifyComp($this->components[$size - 1 - $i]);
            } else {
                $comp = $this->stringifyComp($this->components[$i]);
            }
            if (($i != 0) && ($this->syntaxSeparator != null)) {
                $answer .= $this->syntaxSeparator;
            }
            if (strlen($comp) >= 1) {
                $compsAllEmpty = false;
            }
            $answer .= $comp;
        }
        if ($compsAllEmpty && ($size >= 1) && ($this->syntaxSeparator != null)) {
            $answer .= $this->syntaxSeparator;
        }
        return $answer;
    }

    public function equals($obj = null): bool
    {
        if (($obj != null) && ($obj instanceof NameImpl)) {
            $target = $obj;
            if ($target->size() ==  $this->size()) {
                $mycomps = $this->getAll();
                $comps = $target->getAll();
                for ($i = 0; $i < count($mycomps); $i++) {
                    $my = $mycomps[$i];
                    $his = $comps[$i];
                    if ($this->syntaxTrimBlanks) {
                        $my = trim($my);
                        $his = trim($his);
                    }
                    if ($this->syntaxCaseInsensitive) {
                        if (trim($my) != trim($his)) {
                            return false;
                        }
                    } else {
                        if ($my != $his) {
                            return false;
                        }
                    }
                }
                return true;
            }
        }
        return false;
    }

    /**
      * Compares obj to this NameImpl to determine ordering.
      * Takes into account syntactic properties such as
      * elimination of blanks, case-ignore, etc, if relevant.
      *
      * Note: using syntax of this NameImpl and ignoring
      * that of comparison target.
      */
    public function compareTo(NameImpl $obj): int
    {
        if ($this == $obj) {
            return 0;
        }

        $len1 = $this->size();
        $len2 = $obj->size();
        $n = min($len1, $len2);

        $index1 = 0;
        $index2 = 0;

        while ($n-- != 0) {
            $comp1 = $this->get($index1++);
            $comp2 = $obj->get($index2++);

            // normalize according to syntax
            if ($this->syntaxTrimBlanks) {
                $comp1 = trim($comp1);
                $comp2 = trim($comp2);
            }

            $local = 0;
            if ($this->syntaxCaseInsensitive) {
                $local = strtolower($comp1) == strtolower($comp2) ? 0 : (strtolower($comp1) < strtolower($comp2) ? -1 : 1);
            } else {
                $local = $comp1 == $comp2 ? 0 : ($comp1 < $comp2 ? -1 : 1);
            }

            if ($local != 0) {
                return $local;
            }
        }

        return $len1 - $len2;
    }

    public function size(): int
    {
        return count($this->components);
    }

    public function getAll(): array
    {
        return array_values($this->components);
    }

    public function get(int $posn): ?string
    {
        if (array_key_exists($posn, $this->components)) {
            return $this->components[$posn];
        }
        return null;
    }

    public function getPrefix(int $posn): array
    {
        if ($posn < 0 || $posn > $this->size()) {
            throw new \Exception("Index out of bounds: " . $posn);
        }
        return array_slice($this->components, 0, $posn);
    }

    public function getSuffix(int $posn): array
    {
        $cnt = $this->size();
        if ($posn < 0 || $posn > $cnt) {
            throw new \Exception("Index out of bounds: " . $posn);
        }
        return array_slice($this->components, $posn, $cnt);
    }

    public function isEmpty(): bool
    {
        return empty($this->components);
    }

    public function startsWith(int $posn, array $prefix): bool
    {
        if ($posn < 0 || $posn > $this->size()) {
            return false;
        }
        try {
            $mycomps = $this->getPrefix($posn);
            for ($i = 0; $i < min(count($mycomps), count($prefix)); $i += 1) {
                $my = $mycomps[$i];
                $his = $prefix[$i];
                if ($this->syntaxTrimBlanks) {
                    $my = trim($my);
                    $his = trim($his);
                }
                if ($this->syntaxCaseInsensitive) {
                    if (strtolower($my) != strtolower($his)) {
                        return false;
                    }
                } else {
                    if ($my != $his) {
                        return false;
                    }
                }
            }
        } catch (\Throwable $e) {
            return false;
        }
        return true;
    }

    public function endsWith(int $posn, array $suffix): bool
    {
        // posn is number of elements in suffix
        // startIndex is the starting position in this name
        // at which to start the comparison. It is calculated by
        // subtracting 'posn' from size()
        $startIndex = $this->size() - $posn;
        if ($startIndex < 0 || $startIndex > $this->size()) {
            return false;
        }
        try {
            $mycomps = $this->getSuffix($startIndex);
            for ($i = 0; $i < count($mycomps); $i += 2) {
                $my = $mycomps[$i];
                $his = $mycomps[$i + 1];
                if ($this->syntaxTrimBlanks) {
                    $my = trim($my);
                    $his = trim($his);
                }
                if ($this->syntaxCaseInsensitive) {
                    if (strtolower($my) != strtolower($his)) {
                        return false;
                    }
                } else {
                    if ($my != $his) {
                        return false;
                    }
                }
            }
        } catch (\Throwable $e) {
            return false;
        }
        return true;
    }

    public function addAll(...$args): bool //int posn, Enumeration<String> comps
    {
        $added = false;
        if (is_array($args[0])) {
            foreach ($args[0] as $comp) {
                if ($this->size() > 0 && $this->syntaxDirection == self::FLAT) {
                    throw new \Exception("A flat name can only have a single component");
                }
                $this->components[] = $comp;
                $added = true;
            }
        } elseif (is_int($args[0]) && is_array($args[1])) {
            $i = $args[0];
            foreach ($args[1] as $comp) {
                if ($this->size() > 0 && $this->syntaxDirection == self::FLAT) {
                    throw new \Exception("A flat name can only have a single component");
                }
                array_splice($this->components, $i, 0, [ $comp ]);
                $added = true;
                $i += 1;
            }
        }
        return $added;
    }

    public function add(...$args): void
    {
        if (is_string($args[0])) {
            if ($this->size() > 0 && $this->syntaxDirection == self::FLAT) {
                throw new \Exception("A flat name can only have a single component");
            } else {
                $this->components[] = $args[0];
            }
        } elseif (is_int($args[0]) && is_string($args[1])) {
            if ($this->size() > 0 && $this->syntaxDirection == self::FLAT) {
                throw new \Exception("A flat name can only zero or one component");
            } else {
                array_splice($this->components, $args[0], 0, [ $args[1] ]);
            }
        }
    }

    public function remove(int $posn) {
        if (array_key_exists($posn, $this->components)) {
            $r = $this->components[$posn];
            array_splice($this->components, $posn, 1);
            return $r;
        }
        return null;
    }
}
