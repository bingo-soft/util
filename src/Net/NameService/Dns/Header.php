<?php

namespace Util\Net\NameService\Dns;

class Header
{
    public const HEADER_SIZE = 12;  // octets in a DNS header

    // Masks and shift amounts for DNS header flag fields.
    public const QR_BIT =         0x8000;
    public const OPCODE_MASK =    0x7800;
    public const OPCODE_SHIFT =   11;
    public const AA_BIT =         0x0400;
    public const TC_BIT =         0x0200;
    public const RD_BIT =         0x0100;
    public const RA_BIT =         0x0080;
    public const RCODE_MASK =     0x000F;

    public int $xid = 0;                    // ID:  16-bit query identifier
    public bool $query = false;              // QR:  true if query, false if response
    public int $opcode = 0;                 // OPCODE:  4-bit opcode
    public bool $authoritative = false;      // AA
    public bool $truncated = false;          // TC
    public bool $recursionDesired = false;   // RD
    public bool $recursionAvail = false;     // RA
    public int $rcode = 0;                  // RCODE:  4-bit response code
    public int $numQuestions = 0;
    public int $numAnswers = 0;
    public int $numAuthorities = 0;
    public int $numAdditionals = 0;

    /*
     * Returns a representation of a decoded DNS message header.
     * Does not modify or store a reference to the msg array.
     */
    public function __construct(array $msg, int $msgLen)
    {
        $this->decode($msg, $msgLen);
    }

    /*
     * Decodes a DNS message header.  Does not modify or store a
     * reference to the msg array.
     */
    private function decode(array $msg, int $msgLen): void
    {

        try {
            $pos = 0;        // current offset into msg

            if ($msgLen < self::HEADER_SIZE) {
                throw new \Exception("DNS error: corrupted message header");
            }

            $this->xid = self::getShort($msg, $pos);
            $pos += 2;

            // Flags
            $flags = self::getShort($msg, $pos);
            $pos += 2;
            $this->query = ($flags & self::QR_BIT) == 0;
            $this->opcode = ($flags & self::OPCODE_MASK) >> self::OPCODE_SHIFT;
            $this->authoritative = ($flags & self::AA_BIT) != 0;
            $this->truncated = ($flags & self::TC_BIT) != 0;
            $this->recursionDesired = ($flags & self::RD_BIT) != 0;
            $this->recursionAvail = ($flags & self::RA_BIT) != 0;
            $this->rcode = ($flags & self::RCODE_MASK);

            // RR counts
            $this->numQuestions = self::getShort($msg, $pos);
            $pos += 2;
            $this->numAnswers = self::getShort($msg, $pos);
            $pos += 2;
            $this->numAuthorities = self::getShort($msg, $pos);
            $pos += 2;
            $this->numAdditionals = self::getShort($msg, $pos);
            $pos += 2;

        } catch (\Throwable $e) {
            throw new \Exception("DNS error: corrupted message header");
        }
    }

    /*
     * Returns the 2-byte unsigned value at msg[pos].  The high
     * order byte comes first.
     */
    private static function getShort(array $msg, int $pos): int
    {
        return (((hexdec($msg[$pos]) & 0xFF) << 8) | (hexdec($msg[$pos + 1]) & 0xFF));
    }
}
