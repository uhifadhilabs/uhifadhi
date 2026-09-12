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

namespace Uhifadhi\Bundle\AreaBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Uhifadhi\Bundle\AreaBundle\Api\FieldRoster;
use Uhifadhi\Bundle\AreaBundle\ApiResource\AreasMine;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Repository\AreaOfInterestRepository;

/**
 * Answers `GET /api/areas/mine`: builds a field client's offline cache.
 *
 * "MINE" IS THE PLATFORM'S OWN AUTHORITY QUESTION, ASKED ONCE PER AREA, and not a
 * query written for this endpoint. An area is in the answer when `area.view` is
 * granted FOR THAT AREA — the same question every area screen asks — so the
 * narrowing is the permission model's and can never drift from it: a tier holds
 * every area, an org-level position holds every area, and somebody whose
 * department is confined to one area is handed that one.
 *
 * THE AREA IS PASSED AS THE SUBJECT, which is the whole reason the voter takes
 * one. Asking without it would answer "does this person have authority anywhere?",
 * which is a different and much weaker question, and it is the one an endpoint that
 * hands out a list must not ask.
 *
 * AN EMPTY LIST IS AN ANSWER, NOT A REFUSAL. The contract has no "you have no
 * areas" failure, and a client that met a 403 here would stop syncing instead of
 * showing an empty picker.
 *
 * THE PERMISSION IS WRITTEN AS A STRING, as every gated screen in this bundle
 * writes it: the catalogue values are the platform's published vocabulary, and an
 * enum constant would make the package that owns the account a hard dependency of
 * owning ground.
 *
 * @implements ProviderInterface<AreasMine>
 */
final readonly class AreasMineProvider implements ProviderInterface
{
    /** Seeing an area and everything recorded inside it — the platform's own value. */
    private const string PERMISSION = 'area.view';

    /**
     * Roughly 55 m on the ground. A client caches the boundary on a phone and
     * draws it at zooms where a vertex every few metres is invisible, so the full
     * survey geometry would be megabytes spent on nothing. PreserveTopology, not
     * plain Simplify: a ring that self-intersects after thinning draws as a torn
     * shape.
     */
    private const float FIELD_SIMPLIFY_DEGREES = 0.0005;

    public function __construct(
        private AreaOfInterestRepository $areas,
        private FieldRoster $roster,
        private AuthorizationCheckerInterface $authorization,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AreasMine
    {
        // Read once, outside the loop: the roster is the same list whichever
        // piece of ground somebody is standing on.
        $team = $this->roster->members();

        $areas = [];
        // BY NAME, because a client's picker must not reorder between two syncs;
        // insertion order is a fact nobody outside the database can see.
        foreach ($this->areas->findBy([], ['name' => 'ASC']) as $area) {
            if (!$this->authorization->isGranted(self::PERMISSION, $area)) {
                continue;
            }

            $areas[] = [
                // The public address, never the sequential key: an area is a
                // UUID everywhere in this product, and a client only ever
                // round-trips the value.
                'id' => (string) $area->getUuidString(),
                'name' => (string) $area->getName(),
                'areaKm2' => $this->areaKm2($area),
                'stations' => [],
                'team' => $team,
                'boundary' => $this->boundary($area),
            ];
        }

        return new AreasMine($areas);
    }

    /**
     * MEASURED ON THE SPHEROID, IN THE DATABASE — the way a surveyor measures
     * ground rather than by multiplying degrees. Rounded to a tenth, which is
     * finer than any figure a handset prints and coarser than the noise between
     * two spheroid models.
     *
     * An area whose edge has not been imported measures nothing, and says so as
     * 0.0 rather than as a null the contract does not describe for this field.
     */
    private function areaKm2(AreaOfInterest $area): float
    {
        $id = $area->getId();

        return null === $id ? 0.0 : round($this->areas->stAreaKm2(['id' => $id]), 1);
    }

    /**
     * The edge as GeoJSON, already thinned, as an OBJECT: PostGIS hands it back
     * as text and in lon/lat, which is the order the contract wants.
     *
     * NULL FOR AN AREA WITH NO EDGE, and for one thinned away to nothing — an area
     * is created from its name and its boundary is imported now or later, so
     * "no geometry" is an ordinary state and not a fault.
     *
     * @return array<string, mixed>|null
     */
    private function boundary(AreaOfInterest $area): ?array
    {
        $id = $area->getId();
        if (null === $id || !$area->hasBoundary()) {
            return null;
        }

        $text = $this->areas->stSimplifiedBoundary($id, self::FIELD_SIMPLIFY_DEGREES);
        if (null === $text || '' === $text) {
            return null;
        }

        $decoded = json_decode($text, true);

        /** @var array<string, mixed>|null */
        return \is_array($decoded) ? $decoded : null;
    }
}
