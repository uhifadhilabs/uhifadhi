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
use Uhifadhi\Bundle\AreaBundle\Exception\AreaCreationException;

/**
 * AN AREA IS BORN FROM ITS IDENTITY — a name, and the gazetted facts that happen
 * to be known. The boundary is deliberately NOT here: an area is created without
 * one and gets its edge afterwards through {@see BoundaryImport}, now or later,
 * which is the whole point of the split.
 *
 * REGISTERED BESIDE THE ENTITY, NOT WITH THE SCREENS — a console importer or a
 * fixture loader in an installation with no twig must be able to make an area
 * too, so creation is a model concern and lives with the model's services.
 *
 * THE GEOMETRY IS HANDED IN ALREADY-READ, NEVER PARSED HERE. When an area is
 * created with its boundary in the same step, the create screen has already
 * turned the file into a MultiPolygon string through {@see BoundaryImport}
 * BEFORE calling this — so a bad file is refused before any area exists, and
 * this service never leaves a half-made one behind. Boundary-less creation
 * simply omits it.
 */
final readonly class AreaCreator
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * Create and persist an area from its identity, with or without a boundary.
     *
     * $geom is a MultiPolygon GeoJSON string already read from a file (or null
     * for none), and $source is where it came from — set only when $geom is,
     * because provenance is a fact about a boundary.
     *
     * @throws AreaCreationException when the name is blank
     */
    public function create(
        string $name,
        ?string $iucnCategory = null,
        ?int $establishedYear = null,
        ?string $geom = null,
        ?string $source = null,
    ): AreaOfInterest {
        $name = trim($name);
        if ('' === $name) {
            throw new AreaCreationException('An area needs a name — as its official record names it.');
        }

        $area = new AreaOfInterest()
            ->setName($name)
            ->setIucnCategory($iucnCategory)
            ->setEstablishedYear($establishedYear);

        if (null !== $geom) {
            $area->setGeom($geom)->setSource($source ?? '');
        }

        $this->entityManager->persist($area);
        $this->entityManager->flush();

        return $area;
    }
}
