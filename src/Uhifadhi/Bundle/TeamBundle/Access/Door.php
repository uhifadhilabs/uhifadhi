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

namespace Uhifadhi\Bundle\TeamBundle\Access;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Uhifadhi\Contracts\Access\Grant;
use Uhifadhi\Contracts\Access\Verb;

/**
 * WHETHER A DOOR OPENS — the one helper every drawn control asks.
 *
 * A DOOR IS A LINK, A BUTTON OR A SECTION that leads somewhere a permission
 * guards. Drawing one the reader cannot walk through is the small daily
 * dishonesty of an administrative product: they click, they are refused, and
 * they learn to distrust the page. So a door is drawn only where the person
 * holds the pair the thing behind it enforces.
 *
 * ONE HELPER, NOT A CONVENTION. Every door in the product goes through this,
 * and a conformance test refuses a template that asks the checker directly.
 * That is what makes the second proof possible: a test can walk the doors
 * because they are all spelled one way, so a door naming a pair nothing
 * enforces — or a pair no door names — is caught here rather than discovered
 * by somebody clicking.
 *
 * IT NAMES THE SAME PAIR THE ROUTE DOES. The door and the gate behind it are
 * two statements of one fact, and the whole point of spelling both as
 * `<concern>.<verb>` is that a test can hold them together.
 *
 * IT IS NOT THE GATE. Hiding a control is a courtesy; refusing the request is
 * the security, and the route still enforces its own pair. A door that forgot
 * to ask leaks nothing but a disappointing click.
 */
final readonly class Door
{
    public function __construct(
        private AuthorizationCheckerInterface $checker,
    ) {
    }

    /**
     * WHETHER TO DRAW IT, given the pair the thing behind it enforces and the
     * ground it is about.
     *
     * THE SUBJECT IS THE SAME ONE THE ROUTE PASSES — the area a record lies
     * in, or null where there is no area in context. A door that asked
     * without the ground would be answering a different question from the
     * gate, and would sometimes draw a control that then refuses.
     */
    public function opens(string $pair, mixed $subject = null): bool
    {
        return $this->checker->isGranted($pair, $subject);
    }

    /** The same, for a caller that already holds the two halves apart. */
    public function opensFor(string $concern, Verb $verb, mixed $subject = null): bool
    {
        return $this->opens((string) Grant::of($concern, $verb), $subject);
    }
}
