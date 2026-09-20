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

use Symfony\Component\HttpFoundation\RequestStack;
use Uhifadhi\Contracts\Shell\Scope;
use Uhifadhi\Contracts\Shell\ScopeSourceInterface;

/**
 * HOW WIDE THE PAGE IS LOOKING, and what else it could look at.
 *
 * THE SHELL OWNS THE CONTROL AND NONE OF ITS CONTENT. It asks the tagged
 * sources what this viewer may look at — the host has the areas, the viewer
 * and the voters — and it resolves the current one off the address. A module
 * states no scope control of its own and reads no query parameter for it:
 * it is handed a {@see Scope} and answers `forScope()`.
 *
 * ONE PARAMETER, NAMED ONCE. `?area=<uuid>` is the whole vocabulary, so a
 * link from anywhere in the product to "this page, that area" is a link
 * anybody can write, and the parameter's name lives here rather than in
 * every module that reads a page.
 *
 * AN ADDRESS NAMING A SLICE THE VIEWER CANNOT SEE IS THE FIRST ONE, not a
 * refusal: a stale link to an area somebody wound down — or to one this
 * account was never given — should open the page it was sent from rather
 * than a wall. The list is already only what they may look at, so falling
 * back to its first row leaks nothing.
 */
final readonly class Scopes
{
    /** The query parameter the control writes and every link can copy. */
    public const string PARAMETER = 'area';

    /**
     * @param iterable<ScopeSourceInterface> $sources the tagged contributors, in registration order
     */
    public function __construct(
        private iterable $sources,
        private RequestStack $requests,
    ) {
    }

    /**
     * Everything this viewer may look at, in the order it is offered.
     *
     * @return list<Scope>
     */
    public function available(): array
    {
        $scopes = [];
        foreach ($this->sources as $source) {
            foreach ($source->scopes() as $scope) {
                $scopes[] = $scope;
            }
        }

        return $scopes;
    }

    /**
     * THE SLICE THIS REQUEST IS ABOUT — the one the address names, or the
     * first one offered.
     *
     * NULL WHERE NOTHING IS OFFERED AT ALL, which is a real state and not an
     * empty organisation: an installation with no areas, or an account that
     * may see none, has nothing to scope and the page says so rather than
     * drawing a control with no rows in it.
     */
    public function current(): ?Scope
    {
        $available = $this->available();
        if ([] === $available) {
            return null;
        }

        $named = $this->requests->getCurrentRequest()?->query->get(self::PARAMETER);
        if (\is_string($named) && '' !== $named) {
            foreach ($available as $scope) {
                if ($scope->areaUuid === $named) {
                    return $scope;
                }
            }
        }

        return $available[0];
    }
}
