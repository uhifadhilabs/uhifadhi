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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Integration\Theme;

use PHPUnit\Framework\Attributes\DataProvider;
use Uhifadhi\Bundle\ShellBundle\Contract\LayoutContract;
use Uhifadhi\Bundle\ShellBundle\Tests\Integration\ContractTestCase;

/**
 * SPEC 5 — THE COMPONENT VOCABULARY.
 *
 * The tokens say what jade is. This says what a KPI plate is — and the second
 * one turned out to matter just as much, because four modules independently
 * wrote `class="kpi"` against a rule that lived in none of them.
 *
 * WHERE THESE CAME FROM. The design workspace keeps the shared vocabulary in a
 * vendor sheet and every screen's own sheet says "not repeated here". The
 * platform had no vendor sheet, so the first module to need `.kpi` restated it
 * in its own stylesheet and marked the block "on loan — belongs in the shell".
 * This is the shell collecting the loan: one definition, in the frame, so a
 * module that draws a register table gets the platform's register table rather
 * than its own idea of one.
 *
 * THE BOUNDARY THAT DECIDED THE LIST. For every rule: could a third-party
 * Sightings module use this class without the shell knowing Sightings exists?
 * `.kpi`, `table.tbl`, `.avatar`, `.tgl`, the card's tab and the pager all pass
 * — they are what a plate, a number, a table and a person's mark look like on
 * this platform. Anything encoding a particular module's screens does not pass
 * and stays in that module's own sheet, whatever it is named: a shell that
 * shipped `.pm-deptrow` would be a shell that knows what a department is.
 *
 * ONE NAME WAS WRONG AND IS FIXED HERE. The KPI strip's auto-fitting layout
 * shipped as `.dp-kstrip` — a departments-era prefix on a rule two unrelated
 * modules already use for a strip of plates. It is a generic layout, so it
 * hoists under a generic name, `.kstrip`.
 *
 * WHY THERE IS NO DARK HALF OF THIS FILE. Every rule below spends tokens and
 * names no colour of its own, which is what makes all of it correct in both
 * palettes without a single `html.dark` rule — so the test that dark is
 * first-class here is the one that forbids a literal, not one that counts
 * overrides.
 */
final class ComponentContractTest extends ContractTestCase
{
    /**
     * THE COMPONENT LIST, typed out for the reason the tokens are: a list
     * derived from the stylesheet agrees with the stylesheet.
     *
     * These are the ENTRY classes — the name a module writes on an element.
     * Their parts (`.kpi .sub`, `.rdf-page .pg`, `.tbl .num`) are documented in
     * docs/components.md and are not separately frozen, because a part without
     * its entry is not a thing a module can write.
     *
     * @return list<string>
     */
    public static function contractV1(): array
    {
        return [
            // THE PLATE AND ITS VOCABULARY — shipped since 0.1, listed now.
            'c',            // the card every surface is built from
            'chip',         // the status pill: ok / warn / fail / idle / acc
            'cta',          // the call to action
            'grid',         // the page's column system: g2 / g3 / g4 / g32

            // TYPE AND THE COLOUR WORDS — one word of a sentence, coloured.
            'mono',
            'disp',
            'fog',
            'acc',
            'g',            // ok
            'w',            // warn
            'r',            // fail
            'd',            // dim
            'muted',        // dim, spelled for prose

            // THE CARD'S TAB — a widget says what it is on its own top edge.
            'tab',
            'use',          // and the line under it saying what it is FOR

            // THE KPI PLATE, and the strip it sits in.
            'kpi',
            'kstrip',

            // THE REGISTER TABLE, and the pager under it.
            'tbl',
            'rdf-foot',
            'rdf-page',

            // THE PERSON'S MARK, AND THE TWO QUIET BUTTONS.
            'avatar',
            'open-btn',
            'tgl',

            // THE FORM FIELD every filter/search input is drawn as.
            'fld',
        ];
    }

    public function testTheComponentListIsExactlyThis(): void
    {
        self::assertSame(self::contractV1(), LayoutContract::COMPONENTS, <<<'WHY'
            A component class was added, removed or renamed. Module templates
            across the platform write these names — the same change policy
            applies as to the blocks and the tokens (see
            docs/changing-the-contract.md).
            WHY);
    }

    /**
     * Every promised class is actually styled. The list is a promise; the
     * stylesheet is the keeping of it. The failure this catches is a live page
     * whose KPI plates render as one line of running text because the class is
     * written and defined nowhere.
     */
    #[DataProvider('components')]
    public function testEveryPromisedComponentIsStyled(string $class): void
    {
        self::assertMatchesRegularExpression(
            '/\.'.preg_quote($class, '/').'(?![\w-])/',
            $this->stylesheet(),
            \sprintf('.%s is promised by the contract and styled nowhere.', $class),
        );
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function components(): \Generator
    {
        foreach (self::contractV1() as $class) {
            yield $class => [$class];
        }
    }

    /**
     * THE COMPONENTS SPEND TOKENS AND NAME NO COLOUR. This is the whole reason
     * there is no dark half of the component section: a rule written as
     * `color: rgb(var(--c-fog))` is already correct under both palettes, and
     * the first `#8a8a8a` in this file is the first component that will look
     * wrong after dark on somebody else's page.
     */
    public function testNoComponentRuleNamesAColourOfItsOwn(): void
    {
        $section = $this->componentSection();

        self::assertDoesNotMatchRegularExpression(
            '/#[0-9a-fA-F]{3,8}\b|\brgba?\(\s*\d/',
            $section,
            'A component named a literal colour. Spend a token, or it is wrong in one of the two palettes.',
        );
    }

    /**
     * AND THEY ARE UNSCOPED, on purpose. A module's sheet loads after this one
     * and may override; what it must not have to do is opt in. A component
     * section scoped to a shell wrapper would be a vocabulary only the shell's
     * own pages could speak, which is the opposite of the point.
     */
    #[DataProvider('components')]
    public function testTheComponentsAreNotScopedToTheShellsOwnPages(string $class): void
    {
        self::assertDoesNotMatchRegularExpression(
            '/^\s*(?:\.shell|\.page|\.welcome)\b[^{,]*\.'.preg_quote($class, '/').'(?![\w-])/m',
            $this->componentSection(),
            \sprintf('.%s is only styled inside the shell\'s own furniture; a module cannot use it.', $class),
        );
    }

    /**
     * THE FRAME'S OWN BODY WRAPPER IS STYLED. `.pgbody` is emitted by the page
     * frame on every screen the platform draws and, until this release, was
     * styled by nobody — so a module's first element inherited whatever margin
     * it happened to carry and collapsed it through the wrapper into the frame,
     * which is why the gap under a page's tabs moved depending on what the
     * module put first. It is furniture, not vocabulary: the shell writes it,
     * a module never does, which is why it is not on the frozen list above.
     */
    public function testTheFramesPageBodyWrapperIsStyled(): void
    {
        self::assertMatchesRegularExpression(
            '/\.pgbody\s*\{/',
            $this->stylesheet(),
            'The frame emits .pgbody on every page. A class the shell writes and nobody styles is a class that behaves differently on every module.',
        );
    }

    /**
     * The component section, delimited by its own banner so the two tests above
     * judge the vocabulary rather than the whole sheet — which does name
     * colours, in the one place it is allowed to: the palettes.
     */
    private function componentSection(): string
    {
        $css = $this->stylesheet();

        $start = strpos($css, self::SECTION);
        self::assertIsInt($start, 'The component vocabulary ships in a section of its own, so it can be read as one.');

        $end = strpos($css, '/* ====', $start + \strlen(self::SECTION));

        return false === $end ? substr($css, $start) : substr($css, $start, $end - $start);
    }

    private const string SECTION = 'THE COMPONENT VOCABULARY';
}
