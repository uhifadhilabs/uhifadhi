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

namespace Uhifadhi\Bundle\AreaBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Entity\ZoneEvent;
use Uhifadhi\Bundle\AreaBundle\Enum\ZoneEventKind;
use Uhifadhi\Bundle\AreaBundle\Model\ZoneImportResult;

/**
 * THE ZONE LOG, WRITTEN — one line per event, in the product's own words.
 *
 * ONE PLACE COMPOSES THE SENTENCES, so that "9 zones added" and "11 zones
 * imported" cannot drift into two vocabularies written by two screens. Every
 * caller hands over facts; the words are decided here.
 *
 * A LINE IS WRITTEN WHERE THE EVENT HAPPENS, not derived later from the rows.
 * A rename leaves no trace in the zone table at all — the name is simply
 * different — and a refused file leaves none anywhere, which is precisely why
 * both are worth a line.
 */
final readonly class ZoneEventService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * WHAT AN IMPORT DID, INCLUDING WHAT IT LEFT OUT. An import that wrote nine
     * of eleven did what it was asked; a log that recorded only the nine would
     * leave somebody re-uploading the file to find the other two.
     */
    public function imported(AreaOfInterest $area, ZoneImportResult $result, ?string $actor, bool $intoAnEmptyArea): void
    {
        $detail = [];
        if ($result->skippedCount() > 0) {
            $detail[] = \sprintf('%d feature%s skipped', $result->skippedCount(), 1 === $result->skippedCount() ? '' : 's');
        }
        if ($intoAnEmptyArea) {
            $detail[] = 'into an empty area';
        }
        $detail[] = \sprintf('names from %s', $result->nameProperty);

        $this->write($area, ZoneEventKind::Imported, \sprintf(
            '%d zone%s added%s',
            $result->count(),
            1 === $result->count() ? '' : 's',
            null === $actor ? '' : ' by '.$actor,
        ), implode(' · ', $detail), $actor);
    }

    /** A rename moves nothing: the ground is where it was and every record is still on it. */
    public function renamed(AreaOfInterest $area, string $from, string $to, ?string $actor): void
    {
        $this->write($area, ZoneEventKind::Renamed, \sprintf('“%s” renamed to “%s”', $from, $to), implode(' · ', array_filter([
            $actor,
            'no record moved',
        ])), $actor);
    }

    public function ringReplaced(AreaOfInterest $area, string $name, ?string $actor): void
    {
        $this->write($area, ZoneEventKind::RingReplaced, \sprintf('“%s” redrawn', $name), implode(' · ', array_filter([
            $actor,
            'the earlier ring is not kept',
        ])), $actor);
    }

    public function removed(AreaOfInterest $area, string $name, ?string $actor): void
    {
        $this->write($area, ZoneEventKind::Removed, \sprintf('“%s” removed', $name), implode(' · ', array_filter([
            $actor,
            'its ground is unzoned',
        ])), $actor);
    }

    public function cleared(AreaOfInterest $area, int $count, ?string $actor): void
    {
        $this->write($area, ZoneEventKind::Cleared, \sprintf(
            'all %d zone%s removed%s',
            $count,
            1 === $count ? '' : 's',
            null === $actor ? '' : ' by '.$actor,
        ), 'nothing else was deleted', $actor);
    }

    /** Nothing changed, which is why it is worth a line: somebody tried. */
    public function refused(AreaOfInterest $area, string $why, ?string $actor): void
    {
        $this->write($area, ZoneEventKind::Refused, 'File refused', $why, $actor);
    }

    private function write(AreaOfInterest $area, ZoneEventKind $kind, string $headline, ?string $detail, ?string $actor): void
    {
        $this->entityManager->persist(new ZoneEvent()
            ->setArea($area)
            ->setKind($kind)
            ->setHeadline(self::within($headline, 255))
            ->setDetail(null === $detail || '' === $detail ? null : self::within($detail, 255))
            ->setActor($actor)
            ->setOccurredAt(new \DateTimeImmutable()));
        $this->entityManager->flush();
    }

    /**
     * A zone name is 128 characters and a sentence can hold two of them, so the
     * line is cut rather than allowed to break the insert. A truncated line is
     * a legible answer; a failed write in the middle of a successful import is
     * not.
     */
    private static function within(string $sentence, int $limit): string
    {
        return mb_strlen($sentence) <= $limit ? $sentence : mb_substr($sentence, 0, $limit - 1).'…';
    }
}
