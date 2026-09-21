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

use Uhifadhi\Contracts\Shell\Scope;
use Uhifadhi\Contracts\Shell\ScopeSourceInterface;

/**
 * WHAT THIS VIEWER MAY LOOK AT — the stand-in host\'s answer.
 *
 * The shell holds no areas and no voters, so the slices arrive from here the
 * way they arrive from a real host: already narrowed to what the account may
 * open, organization first where it is offered at all.
 */
final class FixtureScopeSource implements ScopeSourceInterface
{
    /** @var list<Scope> */
    public static array $scopes = [];

    public static function reset(): void
    {
        self::$scopes = [];
    }

    public function scopes(): iterable
    {
        return self::$scopes;
    }
}
