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

namespace Uhifadhi\Bundle\RegistryBundle\Access;

use Uhifadhi\Contracts\Access\Concern;
use Uhifadhi\Contracts\Access\ConcernSourceInterface;
use Uhifadhi\Contracts\Access\ScopeKind;
use Uhifadhi\Contracts\Access\Verb;

/**
 * THE CATALOGUE'S ONE CONCERN: which modules an area runs.
 *
 * The registry renders nothing and owns no capability of its own; what it
 * owns is the ledger saying a module is switched on here and parked there.
 * Reading it is how a page knows which cells exist; configuring it is
 * switching a module on for an area.
 *
 * IT IS NOT A MODULE'S OWN CONCERN. Each module declares what its own pages
 * do; this row is about the switch, and the switch is the installation's.
 */
final readonly class RegistryConcerns implements ConcernSourceInterface
{
    /** The key, spelt once, so a gate, a door and a test cannot disagree. */
    public const string MODULES = 'modules';

    public function declaredBy(): string
    {
        return 'Modules';
    }

    public function concerns(): iterable
    {
        yield new Concern(
            key: self::MODULES,
            label: 'Modules',
            description: 'Which modules an area runs, and switching one on or off for it.',
            verbs: [Verb::Read, Verb::Configure],
            scopeKinds: [ScopeKind::Organization, ScopeKind::Area],
        );
    }
}
