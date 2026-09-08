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

namespace Uhifadhi\Bundle\RegistryBundle\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Uhifadhi\Bundle\RegistryBundle\Service\ModuleRouteGate;

/**
 * THE ENFORCEMENT POINT: a parked module's pages are gone, and they are gone
 * before its controller is ever asked.
 *
 * AFTER THE ROUTER, BEFORE THE CONTROLLER (priority 8 on `kernel.request`;
 * Symfony's RouterListener runs at 32). It has to be after the router because
 * the route's own defaults are what the gate reads; it has to be before the
 * controller because "the controller decides" is the per-module check this
 * exists to abolish.
 *
 * 404, NOT 403 — the ruling, and the reason for it. A 403 confirms the thing
 * exists and is being withheld from you; parking withholds nothing, it means
 * this area is not running the module. That is what the area's own screens
 * already say — the module sits in the shop, not the sub-nav — and the URL now
 * says it too.
 *
 * MAIN REQUESTS ONLY. What was ruled is that a parked module must not be
 * reachable by URL, and a sub-request is a render the application asked for
 * itself. A shell that renders a parked module's fragment has a bug one layer
 * up, and turning it into a 404 deep inside a page would hide it rather than
 * fix it.
 *
 * IT PREEMPTS NOTHING ELSE. An area that does not exist, a module page whose
 * own entity is missing, a caller without permission — all of those are
 * answered exactly where they were answered before. This listener has one
 * question and asks it once.
 */
final readonly class ParkedModuleListener
{
    public function __construct(
        private ModuleRouteGate $gate,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($this->gate->closes($request->attributes->all(), $request->getPathInfo())) {
            throw new NotFoundHttpException('This area is not running that module.');
        }
    }
}
