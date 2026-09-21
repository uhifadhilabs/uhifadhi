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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures;

use Uhifadhi\Contracts\Access\Concern;
use Uhifadhi\Contracts\Access\ConcernSourceInterface;
use Uhifadhi\Contracts\Access\ScopeKind;
use Uhifadhi\Contracts\Access\Verb;

/**
 * A MODULE THAT DECLARES A CONCERN, played by a fixture — the surveys module
 * of the seam tests, saying what there is to have a permission about.
 *
 * IT EXISTS SO THE THIRD QUESTION CAN BE ASKED AT ALL. A concern belongs to
 * the departments that run the module which declared it, so without a
 * module-owned concern in the kernel the department dimension of a check has
 * nothing to be about and the test could only ever exercise two of the three
 * questions.
 */
final readonly class DeclaringConcernSource implements ConcernSourceInterface
{
    public function declaredBy(): string
    {
        return 'Surveys';
    }

    public function concerns(): iterable
    {
        yield new Concern(
            key: 'surveys',
            label: 'Surveys',
            description: 'The surveys this module records and the figures it publishes.',
            verbs: [Verb::Read, Verb::Record],
            scopeKinds: [ScopeKind::Organization, ScopeKind::Area, ScopeKind::Department],
            moduleSlug: 'surveys',
        );
    }
}
