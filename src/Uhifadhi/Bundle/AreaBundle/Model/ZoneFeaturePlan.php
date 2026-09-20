<?php

declare(strict_types=1);

/*
 * This file is part of the Uhifadhi core.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Uhifadhi\Bundle\AreaBundle\Model;

/**
 * ONE FEATURE OF AN UPLOADED FILE, AND WHETHER IT IS ARRIVING.
 *
 * AN IMPORT ADDS AND NEVER OVERWRITES, so a feature the area cannot take is not
 * a reason to refuse the file: it is a line in the preview with the reason
 * beside it. A feature is arriving when nothing stands in its way, and flagged
 * when something does — and the flag carries the sentence the preview prints,
 * because "2 features were skipped" is a number nobody can act on.
 *
 * THE GEOMETRY TRAVELS WITH THE VERDICT. A plan is made in one request and
 * confirmed in the next, and the file is gone by then: what the confirm writes
 * is this string, re-checked against the area as it stands at that moment.
 *
 * A QUALIFIER IS NOT A REASON. A feature that reaches past the area boundary
 * still arrives — a gazetted edge and an operational subdivision are drawn by
 * different people — so what the row carries there is a NOTE, and the feature
 * stays in the arriving set.
 *
 * A REASON IS ONE LINE, and it names a zone or a feature — never the area.
 * The list is read by scanning it, the area is the page, and a sentence that
 * wraps to three rows turns the scan into reading. The length is held by
 * {@see \Uhifadhi\Bundle\AreaBundle\Tests\Unit\Model\ZoneFeaturePlanTest}.
 *
 * THE REASON COMES IN THREE PIECES — a lead, the thing it is about, and a tail
 * — because the preview names the conflicting zone in bold and a single
 * sentence carrying markup would be a template written in PHP. {@see why()}
 * puts them back together for a log line and for the suite.
 */
final readonly class ZoneFeaturePlan
{
    /**
     * @param string      $name       the zone name read out of the file's name property
     * @param string|null $geom       the MultiPolygon GeoJSON a zone's column takes, or null where the feature had none
     * @param int|null    $km2        the ground it covers, rounded, or null where there is no geometry to measure
     * @param string      $whyLead    the reason up to the thing it names; empty when the feature is arriving
     * @param string      $whySubject the zone or feature the reason is about, printed in bold; empty where there is none
     * @param string      $whyTail    the rest of the reason
     * @param string      $note       a qualifier on a feature that is still arriving; empty when there is nothing to say
     * @param string      $fileSaid   the name as the FILE wrote it, where the
     *                                importer read it differently — a shouted
     *                                name title-cased. Empty where the name
     *                                arrived as it is. The change is shown in
     *                                the preview rather than recorded in a
     *                                column: the preview is where somebody
     *                                still has a chance to object to it
     */
    private function __construct(
        public string $name,
        public ?string $geom,
        public ?int $km2,
        public string $whyLead = '',
        public string $whySubject = '',
        public string $whyTail = '',
        public string $note = '',
        public string $fileSaid = '',
    ) {
    }

    public static function arriving(string $name, string $geom, ?int $km2, string $fileSaid = ''): self
    {
        return new self($name, $geom, $km2, fileSaid: $fileSaid === $name ? '' : $fileSaid);
    }

    /** A name the area already carries. The file is the thing to change, or the zone. */
    public function nameAlreadyHere(): self
    {
        return $this->because('name already here — rename it in the file, or edit the zone');
    }

    /** A second feature in the same file wearing a name an earlier one already took. */
    public function nameUsedTwiceInTheFile(): self
    {
        return $this->because('this name is used twice in the file');
    }

    /**
     * The ring shares interior with a zone the area already has. Zones may touch
     * along an edge; identical geometry is the extreme case of overlap.
     */
    public function overlapsZone(string $zoneName, ?int $km2): self
    {
        return $this->because('overlaps ', $zoneName, null === $km2 ? '' : \sprintf(' by %s km²', number_format($km2)));
    }

    /** Two features of one file cannot both arrive when they share more than a sliver. */
    public function overlapsFeatureInTheFile(string $featureName, int $km2): self
    {
        return $this->because('overlaps ', $featureName, \sprintf(' in this file by %s km²', number_format($km2)));
    }

    /**
     * A ZONE MAY LIE OUTSIDE THE BOUNDARY, so this is a QUALIFIER and not a
     * reason: the feature is still arriving, and the row says how far past the
     * line it goes because somebody should know without opening a map.
     *
     * THE AREA IS NOT NAMED, because the area is the page: the heading above
     * this list already says which one, and repeating it costs two thirds of
     * the row to tell the reader nothing they cannot see.
     */
    public function extendingBeyondTheBoundary(int $km2): self
    {
        return new self(
            $this->name,
            $this->geom,
            $this->km2,
            $this->whyLead,
            $this->whySubject,
            $this->whyTail,
            \sprintf('extends %s km² beyond the boundary', number_format($km2)),
        );
    }

    /** A zone name with nothing behind it — somebody meant it to be a zone. */
    public function noGeometry(): self
    {
        return $this->because('no geometry in the file');
    }

    /** A feature that declares a polygon the reader could not make one out of. */
    public function notAPolygon(): self
    {
        return $this->because('not a usable polygon');
    }

    public function isArriving(): bool
    {
        return '' === $this->whyLead && null !== $this->geom;
    }

    /** The reason as one sentence, for a history line and for the suite. */
    public function why(): string
    {
        return $this->whyLead.$this->whySubject.$this->whyTail;
    }

    private function because(string $lead, string $subject = '', string $tail = ''): self
    {
        return new self($this->name, $this->geom, $this->km2, $lead, $subject, $tail, $this->note, $this->fileSaid);
    }
}
