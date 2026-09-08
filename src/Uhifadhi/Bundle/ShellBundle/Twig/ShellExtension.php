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

namespace Uhifadhi\Bundle\ShellBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Declares the shell's Twig functions. Nothing more — every one of them is
 * built by {@see ShellRuntime}.
 *
 * THE SPLIT IS NOT DECORATION, and the host learned it the hard way. Twig
 * constructs every EXTENSION as soon as the `twig` service is built, and an
 * image build does exactly that: asset compilation fires an event, UX Icons
 * warms its cache off it, and the icon finder needs Twig. A build stage has no
 * database and no request, so an extension holding anything that reads either
 * kills the BUILD rather than a page. A runtime is constructed lazily, on the
 * first call — which is a render, which is a request.
 */
final class ShellExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            // The sidebar's content, collected from the tagged sources.
            new TwigFunction('shell_nav', [ShellRuntime::class, 'nav']),
            // The tab strip: the sibling screens of wherever the viewer is.
            new TwigFunction('shell_tabs', [ShellRuntime::class, 'tabs']),
            // "<page> — <place> — <brand>", composed once, by the shell.
            new TwigFunction('shell_title', [ShellRuntime::class, 'title']),
            // Who the top bar names — the viewer's card — or null when nobody.
            new TwigFunction('shell_user_badge', [ShellRuntime::class, 'userBadge']),
            // What a visitor who has never chosen a theme gets.
            new TwigFunction('shell_default_theme', [ShellRuntime::class, 'defaultTheme']),
            // The wordmark beside the brand tile, and where the tile links.
            new TwigFunction('shell_brand', [ShellRuntime::class, 'brand']),
            // NOTHING HERE READS THE INSTALLATION. What is installed is one
            // page's data, and it reaches that page from its own controller as
            // an ordinary variable — a global that exists to serve one template
            // is a global in scope on every page in the platform.
        ];
    }
}
