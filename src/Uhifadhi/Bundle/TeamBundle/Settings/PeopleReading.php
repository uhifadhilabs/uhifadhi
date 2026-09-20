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

namespace Uhifadhi\Bundle\TeamBundle\Settings;

use Uhifadhi\Bundle\TeamBundle\Repository\UserRepository;
use Uhifadhi\Contracts\People\PersonPostingProviderInterface;

/**
 * HOW MANY PEOPLE THERE ARE AND HOW MANY OF THEM ARE POSTED — read once, for
 * both of the readings that want it.
 *
 * WHERE SOMEBODY WORKS IS NOT THIS BUNDLE'S. A posting is made on a station,
 * in the area that owns the ground, so it arrives through the posting seam —
 * and an installation with no area package answers nothing, which is why
 * "how many are posted" can be genuinely UNKNOWN here rather than zero. The
 * figure and the step both have to be able to say so.
 *
 * ONE CALL FOR THE WHOLE LIST, which is the seam's own rule: asking per
 * person would be a query per row.
 *
 * SHARED BY THE FIGURE AND THE STEP, because a card saying "18 posted" over a
 * checklist saying "4 to post" has to be arithmetic a reader can do.
 */
final class PeopleReading
{
    private ?int $active = null;

    private ?int $posted = null;

    private bool $anybodyAnswers = false;

    /**
     * @param iterable<PersonPostingProviderInterface> $postingProviders
     */
    public function __construct(
        private readonly UserRepository $people,
        private readonly iterable $postingProviders,
    ) {
    }

    /** Everybody who can sign in. A closed account is a record, not a person at work. */
    public function active(): int
    {
        $this->read();

        return (int) $this->active;
    }

    /** Everybody who can sign in, whether or not they can be signed into. */
    public function all(): int
    {
        return $this->people->countAll();
    }

    /**
     * How many of them are posted somewhere, or NULL where nothing in this
     * installation owns the ground and the question has no answer.
     */
    public function posted(): ?int
    {
        $this->read();

        return $this->anybodyAnswers ? $this->posted : null;
    }

    private function read(): void
    {
        if (null !== $this->active) {
            return;
        }

        $uuids = [];
        foreach ($this->people->findAllByName() as $person) {
            if ($person->isActive()) {
                $uuids[] = (string) $person->getUuidString();
            }
        }

        $this->active = \count($uuids);

        $posted = [];
        foreach ($this->postingProviders as $provider) {
            $this->anybodyAnswers = true;
            foreach ($provider->postingsFor($uuids) as $uuid => $postings) {
                if ([] !== $postings) {
                    $posted[$uuid] = true;
                }
            }
        }

        $this->posted = \count($posted);
    }
}
