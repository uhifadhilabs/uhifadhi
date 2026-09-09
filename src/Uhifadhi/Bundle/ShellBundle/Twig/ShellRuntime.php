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

use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\RuntimeExtensionInterface;
use Uhifadhi\Bundle\ShellBundle\Model\AreaTab;
use Uhifadhi\Bundle\ShellBundle\Model\NavSection;
use Uhifadhi\Bundle\ShellBundle\Service\AreaShell;
use Uhifadhi\Bundle\ShellBundle\Service\Navigation;
use Uhifadhi\Bundle\ShellBundle\Service\Theme;
use Uhifadhi\Bundle\ShellBundle\Service\UserBadgeReader;
use Uhifadhi\Contracts\Shell\UserBadge;

/**
 * What the shell's templates actually call. Built lazily, on the first render —
 * see {@see ShellExtension} for why that matters at image-build time.
 *
 * Everything here is a READ. The shell draws; it does not remember, it does not
 * write, and it decides nothing about who may see what.
 */
final class ShellRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly Navigation $navigation,
        private readonly AreaShell $areaShell,
        private readonly UserBadgeReader $userBadge,
        private readonly Theme $theme,
        private readonly RouterInterface $router,
        private readonly string $brandName,
        private readonly string $homeRoute,
    ) {
    }

    /**
     * @return list<NavSection>
     */
    public function nav(): array
    {
        return $this->navigation->sections();
    }

    /**
     * @return list<AreaTab>
     */
    public function tabs(): array
    {
        return $this->areaShell->tabs();
    }

    /**
     * THE PAGE TITLE, COMPOSED ONCE: "<page> — <place> — <brand>".
     *
     * A platform where every page types this join itself is a platform where
     * some pages use a hyphen, some an em dash, and some forget the brand. A
     * page says only what it is; the shell says where it is and whose it is,
     * because those are the two parts a page cannot know reliably.
     */
    public function title(string $page = ''): string
    {
        $parts = array_filter(
            [trim($page), $this->areaShell->place(), $this->brandName],
            static fn (?string $part): bool => null !== $part && '' !== trim($part),
        );

        return implode(' — ', array_map(trim(...), $parts));
    }

    /**
     * WHO THE TOP BAR NAMES, or null when it names nobody — a fresh
     * installation, a sign-in page, an anonymous request. The card is composed
     * by whoever knows who is signed in and reaches the shell already made; see
     * {@see UserBadge} and the registry it arrives through.
     */
    public function userBadge(): ?UserBadge
    {
        return $this->userBadge->badge();
    }

    public function defaultTheme(): string
    {
        return $this->theme->default();
    }

    /**
     * The wordmark and where the tile links.
     *
     * ROUTE-TOLERANT, and this is the stand-alone rule written into the
     * shell: a fresh installation has no home route yet, and a shell
     * that generated one unconditionally would 500 the very first page of every
     * new install. Home is then the site root, which is true and reachable.
     *
     * @return array{name: string, url: string}
     */
    public function brand(): array
    {
        $declared = null !== $this->router->getRouteCollection()->get($this->homeRoute);

        return [
            'name' => $this->brandName,
            'url' => $declared ? $this->router->generate($this->homeRoute) : '/',
        ];
    }
}
