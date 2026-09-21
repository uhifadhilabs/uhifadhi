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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Integration\Fixtures;

use Uhifadhi\Contracts\Shell\OrgPage;
use Uhifadhi\Contracts\Shell\OrgPagesInterface;

/**
 * A MODULE THAT ANSWERS AT ORGANIZATION LEVEL — the stand-in contributor.
 *
 * NOT A STUB: it impersonates nobody. It is an ordinary implementation of a
 * published interface, written here because the shell mounts org-level page
 * sets and ships none of its own — which is the boundary these tests exist
 * to hold. The slug is invented on purpose: a seam that only works for the
 * modules that exist today is a hardcoded list with extra steps.
 */
final class FixtureOrgModule implements OrgPagesInterface
{
    /** @var list<OrgPage> */
    public static array $pages = [];

    /** What the sidebar row says, where the module also declares a name. */
    public static ?string $name = 'Sightings';

    public static ?string $icon = 'shell:map';

    public static function reset(): void
    {
        self::$pages = [];
        self::$name = 'Sightings';
        self::$icon = 'shell:map';
    }

    public function orgPages(): array
    {
        return self::$pages;
    }

    public function name(): string
    {
        return (string) self::$name;
    }

    public function icon(): ?string
    {
        return self::$icon;
    }
}
