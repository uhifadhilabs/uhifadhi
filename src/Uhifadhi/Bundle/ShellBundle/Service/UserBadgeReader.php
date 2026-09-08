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

namespace Uhifadhi\Bundle\ShellBundle\Service;

use Uhifadhi\Contracts\Shell\UserBadge;
use Uhifadhi\Contracts\Shell\UserBadgeSourceInterface;

/**
 * THE TOP BAR'S VIEWER CARD, READ — and nothing else.
 *
 * The thinnest of the readers, and deliberately so: it asks the one optional
 * source who the top bar should name and hands the answer to a template. It
 * decides nothing about identity, holds no security service and reads no token —
 * a renderer with opinions about the account model would be the second place in
 * the platform where "who is signed in" is interpreted.
 *
 * The source is OPTIONAL, which is the ring gate written into the reader: a
 * fresh installation declares none, and a page then draws a top bar with no card
 * rather than a container that will not compile.
 *
 * READ LIVE, NEVER CACHED. Called on every render, so signing out — or switching
 * to an installation that has no team — takes the card with it on the next
 * request rather than after a deploy.
 */
final class UserBadgeReader
{
    public function __construct(private readonly ?UserBadgeSourceInterface $source = null)
    {
    }

    /**
     * The viewer's card, or null when there is no source or no viewer to name.
     */
    public function badge(): ?UserBadge
    {
        return $this->source?->badge();
    }
}
