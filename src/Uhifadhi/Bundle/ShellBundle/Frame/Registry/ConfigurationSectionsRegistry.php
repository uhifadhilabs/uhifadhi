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

use Uhifadhi\Contracts\Shell\ConfigurationSection;
use Uhifadhi\Contracts\Shell\ConfigurationSectionsInterface;

/**
 * WHAT IS ON EACH SURFACE'S CONFIGURE PAGE — every tagged declaration, filed
 * under the slug that made it, and put in the ruled order on the way out.
 *
 * A MODULE SAYS WHAT ITS SECTIONS ARE; THIS SAYS WHAT ORDER THEY COME IN.
 * Widget library first, Settings last, whatever a module wrote — so a person who
 * has learnt one configure page has learnt all of them, and a module author gets
 * that for free instead of being asked to remember it.
 */
final class ConfigurationSectionsRegistry
{
    /** @var array<string, ConfigurationSectionsInterface>|null */
    private ?array $bySlug = null;

    /** @param iterable<ConfigurationSectionsInterface> $declarations */
    public function __construct(private readonly iterable $declarations)
    {
    }

    public function has(string $slug): bool
    {
        return isset($this->indexed()[$slug]);
    }

    /**
     * The declaration itself, for the two things only it can answer — what the
     * page is called and what it is for. Null for a surface nothing declared.
     */
    public function declaration(string $slug): ?ConfigurationSectionsInterface
    {
        return $this->indexed()[$slug] ?? null;
    }

    /**
     * The surface's configure sections, ordered. Nothing at all for a surface
     * that declared none, which is a surface with no Configure action and no
     * configure page — the right answer for something with nothing to configure.
     *
     * @return list<ConfigurationSection>
     */
    public function sections(string $slug): array
    {
        return self::ordered(($this->indexed()[$slug] ?? null)?->sections() ?? []);
    }

    /**
     * THE RULED ORDER: the widget library opens every configure page, settings
     * closes it, and whatever a surface files between them keeps the order it
     * declared. Two positions are fixed, not the whole list.
     *
     * @param list<ConfigurationSection> $sections
     *
     * @return list<ConfigurationSection>
     */
    private static function ordered(array $sections): array
    {
        $rank = static fn (ConfigurationSection $section): int => match ($section->id) {
            ConfigurationSection::WIDGETS => 0,
            ConfigurationSection::SETTINGS => 2,
            default => 1,
        };

        // usort is stable in PHP 8, so equal ranks keep the declared order.
        usort($sections, static fn (ConfigurationSection $a, ConfigurationSection $b): int => $rank($a) <=> $rank($b));

        return $sections;
    }

    /**
     * @return array<string, ConfigurationSectionsInterface>
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
                throw new \LogicException(\sprintf('%s declares configure sections for no surface. A declaration is filed under the slug that made it, and an unnamed one cannot be filed at all.', $declaration::class));
            }

            if (isset($indexed[$slug])) {
                throw new \LogicException(\sprintf('Both %s and %s declare the configure sections of "%s". One surface has one configure page; the shell will not pick.', $indexed[$slug]::class, $declaration::class, $slug));
            }

            $indexed[$slug] = $declaration;
        }

        return $this->bySlug = $indexed;
    }
}
