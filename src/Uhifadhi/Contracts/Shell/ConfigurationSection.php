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

namespace Uhifadhi\Contracts\Shell;

/**
 * ONE SECTION OF A CONFIGURE PAGE — one entry in the strip that stands where a
 * data page's tabs stand, and the content under it.
 *
 * A SECTION IS EXACTLY ONE OF TWO SHAPES, which is why there are two named
 * constructors and no public one:
 *
 *   {@see page()}   the shell renders it, inside the configure page, from a
 *                   template the module names. This is the ordinary case and
 *                   the one the ruling means by "one configure page": the
 *                   module writes no head, no strip and no page frame.
 *   {@see screen()} the section already has an address of its own — a library
 *                  screen a module shipped before the frame existed — and the
 *                  strip links out to it. The section still belongs to the
 *                  configure page: the Configure action stays lit there and the
 *                  strip renders the same, because that screen adopts the same
 *                  frame.
 *
 * A value object that let a caller pass both would have a fourth state nobody
 * drew, so the constructor is private and each shape is built by name.
 *
 * THE ORDER IS THE SHELL'S, NOT THE MODULE'S. Every configure page in the
 * platform runs Widget library first and Settings last, whatever a module puts
 * between them, so those two positions are named here ({@see WIDGETS},
 * {@see SETTINGS}) and the collector sorts by them. A module that files its
 * kinds in the middle gets the ruled order without knowing there is one.
 *
 * THE LABEL IS THE MODULE'S WORD. The shell prints "Observation kinds" or
 * "Incident kinds" because a module said so; it has no vocabulary of its own to
 * impose and no word it insists on.
 */
final readonly class ConfigurationSection
{
    /**
     * The first section of every configure page: how the surface's dashboard is
     * composed. Named so the collector can anchor it, not so the shell can
     * render it — the module still says what is in it and what it is called.
     */
    public const string WIDGETS = 'widgets';

    /** The last section of every configure page: the surface's own settings. */
    public const string SETTINGS = 'settings';

    /**
     * @param string                $id         stable within one surface, e.g. "kinds"
     * @param string                $label      what the strip says, in the module's own words
     * @param string|null           $template   the twig the shell renders, or null for a screen
     * @param string|null           $routeName  the section's own address, or null for a rendered page
     * @param array<string, scalar> $parameters route parameters beyond the area's own
     */
    private function __construct(
        public string $id,
        public string $label,
        public ?string $template,
        public ?string $routeName,
        public array $parameters,
    ) {
        if ('' === trim($id)) {
            throw new \InvalidArgumentException('A section is addressed by its id: it cannot be empty.');
        }

        if ('' === trim($label)) {
            throw new \InvalidArgumentException(\sprintf('The "%s" section says nothing in the strip: it cannot have an empty label.', $id));
        }
    }

    /**
     * A SECTION THE SHELL RENDERS. The template is included inside the configure
     * page with the area, the module and the section in scope; it writes the
     * section's content and nothing around it.
     */
    public static function page(string $id, string $label, string $template): self
    {
        if ('' === trim($template)) {
            throw new \InvalidArgumentException(\sprintf('The "%s" section names no template, so the shell has nothing to render for it.', $id));
        }

        return new self($id, $label, $template, null, []);
    }

    /**
     * A SECTION THAT ALREADY HAS AN ADDRESS. The strip links out to it and the
     * shell renders nothing for it; the screen at that route draws the configure
     * frame itself.
     *
     * @param array<string, scalar> $parameters
     */
    public static function screen(string $id, string $label, string $routeName, array $parameters = []): self
    {
        if ('' === trim($routeName)) {
            throw new \InvalidArgumentException(\sprintf('The "%s" section names no route, so the strip would render an entry that goes nowhere.', $id));
        }

        return new self($id, $label, null, $routeName, $parameters);
    }

    /** Whether the shell draws this section's content, rather than linking to it. */
    public function isRendered(): bool
    {
        return null !== $this->template;
    }
}
