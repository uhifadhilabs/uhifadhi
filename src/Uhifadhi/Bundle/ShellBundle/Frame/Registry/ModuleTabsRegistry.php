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

namespace Uhifadhi\Bundle\ShellBundle\Frame\Registry;

use Uhifadhi\Contracts\Shell\ModuleTab;
use Uhifadhi\Contracts\Shell\ModuleTabsInterface;

/**
 * WHICH MODULE'S DATA PLACES ARE WHOSE — every tagged declaration, filed under
 * the slug that made it.
 *
 * The same collector shape the widget surfaces use, for the same reason: the
 * shell never has to know which modules exist, and a module written by somebody
 * else declares a strip without either of them changing.
 *
 * WALKED LAZILY, ON THE FIRST QUESTION AND ONCE. The iterator is walked into a
 * map the first time anything is asked, so a render that asks twice — the strip
 * and the sidebar's tree, which is the ordinary render — pays for it once.
 */
final class ModuleTabsRegistry
{
    /** @var array<string, ModuleTabsInterface>|null */
    private ?array $bySlug = null;

    /** @param iterable<ModuleTabsInterface> $declarations */
    public function __construct(private readonly iterable $declarations)
    {
    }

    public function has(string $slug): bool
    {
        return isset($this->indexed()[$slug]);
    }

    /**
     * The module's data places, in the order it declared them — and nothing at
     * all for a module that declared none, which is a module with one screen
     * and no strip to draw.
     *
     * @return list<ModuleTab>
     */
    public function tabs(string $slug): array
    {
        return ($this->indexed()[$slug] ?? null)?->tabs() ?? [];
    }

    /**
     * Every module that declared a strip, for a sidebar that has to ask about
     * modules it is handed rather than modules it knows.
     *
     * @return list<string>
     */
    public function slugs(): array
    {
        return array_keys($this->indexed());
    }

    /**
     * @return array<string, ModuleTabsInterface>
     */
    private function indexed(): array
    {
        if (null !== $this->bySlug) {
            return $this->bySlug;
        }

        $indexed = [];
        foreach ($this->declarations as $declaration) {
            $slug = $declaration->slug();

            if ('' === trim($slug)) {
                throw new \LogicException(\sprintf('%s declares tabs for no module. A declaration is filed under the slug that made it, and an unnamed one cannot be filed at all.', $declaration::class));
            }

            /*
             * TWO DECLARATIONS FOR ONE MODULE IS A DISAGREEMENT, NOT A MERGE.
             * Whichever one won would be decided by the order the definitions
             * happen to compile in, which is the worst possible way to decide
             * what a strip says.
             */
            if (isset($indexed[$slug])) {
                throw new \LogicException(\sprintf('Both %s and %s declare the tabs of the "%s" module. One module has one strip; the shell will not pick.', $indexed[$slug]::class, $declaration::class, $slug));
            }

            $indexed[$slug] = $declaration;
        }

        return $this->bySlug = $indexed;
    }
}
