<?php

namespace Util\Net\NameService\Dns;

class ZoneNode extends NameNode
{
    private $contentsRef = null;   // the zone's namespace
    private int $serialNumber = -1;     // the zone data's serial number
    private $expiration = null;     // time when the zone's data expires

    public function __construct(string $label)
    {
        parent::__construct($label);
    }

    protected function newNameNode(string $label): NameNode
    {
        return new ZoneNode($label);
    }

    /*
     * Clears the contents of this node.  If the node was flagged as
     * expired, it remains so.
     */
    public function depopulate(): void
    {
        $this->contentsRef = null;
        $this->serialNumber = -1;
    }

    /*
     * Is this node currently populated?
     */
    public function isPopulated(): bool
    {
        return ($this->getContents() != null);
    }

    /*
     * Returns the zone's contents, or null if the zone is not populated.
     */
    public function getContents(): ?NameNode
    {
        return ($this->contentsRef != null)
                ? $this->contentsRef // $this->contentsRef->get()
                : null;
    }

    /*
     * Has this zone's data expired?
     */
    public function isExpired(): bool
    {
        return (($this->expiration != null) && $expiration->getTimestamp() < ((new \DateTime())->getTimestamp()));
    }

    /*
     * Returns the deepest populated zone on the path specified by a
     * fully-qualified domain name, or null if there is no populated
     * zone on that path.  Note that a node may be depopulated after
     * being returned.
     */
    public function getDeepestPopulated(DnsName $fqdn): ?ZoneNode
    {
        $znode = $this;
        $popNode = $this->isPopulated() ? $this : null;
        for ($i = 1; $i < $fqdn->size(); $i += 1) { //     "i=1" to skip root label
            $znode = $znode->get($fqdn->getKey($i));
            if ($znode == null) {
                break;
            } elseif ($znode->isPopulated()) {
                $popNode = $znode;
            }
        }
        return $popNode;
    }

    /*
     * Populates (or repopulates) a zone given its own fully-qualified
     * name and its resource records.  Returns the zone's new contents.
     */
    public function populate(DnsName $zone, ResourceRecords $rrs): NameNode
    {
        // assert zone.get(0).equals("");               // zone has root label
        // assert (zone.size() == (depth() + 1));       // +1 due to root label

        $newContents = new NameNode(null);

        for ($i = 0; $i < count($rrs->answer); $i += 1) {
            $rr = $rrs->answer[$i];
            $n = $rr->getName();

            // Ignore resource records whose names aren't within the zone's
            // domain.  Also skip records of the zone's top node, since
            // the zone's root NameNode is already in place.
            if (($n->size() > $zone->size()) && $n->startsWith($zone)) {
                $nnode = $newContents->add($n, $zone->size());
                if ($rr->getType() == ResourceRecord::TYPE_NS) {
                    $nnode->setZoneCut(true);
                }
            }
        }
        // The zone's SOA record is the first record in the answer section.
        $soa = $rrs->answer[0];
        $this->contentsRef = $newContents;
        $this->serialNumber = self::getSerialNumber($soa);
        $this->setExpiration(self::getMinimumTtl($soa));
        return $newContents;
    }

    /*
     * Set this zone's data to expire in <tt>secsToExpiration</tt> seconds.
     */
    private function setExpiration(int $secsToExpiration): void
    {
        $this->expiration = (new \DateTime())->setTimestamp((new \DateTime())->getTimestamp() + $secsToExpiration);
    }

    /*
     * Returns an SOA record's minimum TTL field.
     */
    private static function getMinimumTtl(ResourceRecord $soa): int
    {
        $rdata = $soa->getRdata();
        $pos = strrpos($rdata, ' ') + 1;
        return intval(trim(substr($rdata, $pos)));
    }

    /*
     * Compares this zone's serial number with that of an SOA record.
     * Zone must be populated.
     * Returns a negative, zero, or positive integer as this zone's
     * serial number is less than, equal to, or greater than the SOA
     * record's.
     * See ResourceRecord.compareSerialNumbers() for a description of
     * serial number arithmetic.
     */
    public function compareSerialNumberTo(ResourceRecord $soa): int
    {
        // assert isPopulated();
        return ResourceRecord::compareSerialNumbers($this->serialNumber, self::getSerialNumber($soa));
    }

    /*
     * Returns an SOA record's serial number.
     */
    private static function getSerialNumber(ResourceRecord $soa): int
    {
        $rdata = $soa->getRdata();

        // An SOA record ends with:  serial refresh retry expire minimum.
        // Set "beg" to the space before serial, and "end" to the space after.
        // We go "backward" to avoid dealing with escaped spaces in names.
        $beg = strlen($rdata);
        $end = -1;
        for ($i = 0; $i < 5; $i += 1) {
            $end = $beg;
            $beg = strrpos(substr($rdata, 0, $end), ' ');
        }
        return intval(substr($rdata, $beg + 1, $end - $beg - 1));
    }
}
