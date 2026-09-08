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

namespace Uhifadhi\Bundle\ShellBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Uhifadhi\Bundle\ShellBundle\Service\Installation;

/**
 * THE WELCOME PAGE'S CONTROLLER — the shell's first, and the shape every one
 * after it takes.
 *
 * A PRESENTATION CONTROLLER, which is the only kind this bundle has: it reads
 * what the shell can read for itself — Composer's runtime metadata, the shell's
 * own configured state — and renders one of the shell's own templates. It asks
 * the registry nothing, reads no entity, opens no connection and knows no module by
 * name. Anything that needs domain data arrives through the tagged source
 * interfaces in src/Contract, exactly as the sidebar's rows do.
 *
 * IT IS REACHABLE ONLY THROUGH THE APPLICATION'S IMPORT. This class is
 * addressed by config/routes/welcome.php, and that file is loaded by nothing in
 * this bundle: an application imports it in its own config/routes/shell.yaml,
 * or it does not, and until it does there is no URL here at all. That —
 * consent, not abstinence — is the boundary the shell holds, and
 * tests/Integration/Routing/RouteResourceTest is where it is enforced.
 *
 * NO BASE CLASS. A reusable bundle's controller does not extend
 * AbstractController: that would tie it to a service-subscriber container it
 * cannot assume and hide its dependencies behind a container lookup. It takes
 * what it needs in its constructor and is wired explicitly in
 * config/services.php, like every other service here.
 *
 * @see https://symfony.com/doc/current/bundles/best_practices.html
 */
final class WelcomeController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly Installation $installation,
    ) {
    }

    /**
     * WHAT IS INSTALLED, READ AT REQUEST TIME AND HANDED TO THE TEMPLATE.
     *
     * A page whose whole job is to report on an installation cannot report a
     * list somebody typed: the first module anybody installs makes it wrong,
     * and installing one is the instruction this same page gives. So the list
     * is Composer's, read on every render, and it reaches the template as an
     * ordinary variable — the shell publishes no global for it, because one
     * page's data has no business being in scope on every page in the platform.
     */
    public function __invoke(): Response
    {
        return new Response($this->twig->render('@Shell/welcome.html.twig', [
            'packages' => $this->installation->packages(),
        ]));
    }
}
