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

            // THE WAY BACK — a detail screen's link to the list it came from.
            'backbtn',

            // THE IDENTITY BAND a detail screen opens with.
            'factband',     // .f the fact, .k/.v its halves, .sp and .more the tail

            // The one left mark a card may carry, and it means focus.
            'focusline',

            // THE QUIET FORWARD LINK AND THE MODULE DOT — both were defined
            // only inside one parent (`.factband .more`, `.ntree .ntm .mdot`)
            // and both are written elsewhere throughout the core: a `.more`
            // at the end of a card's row came out blue and underlined, and a
            // dot in a table of modules came out as nothing at all. The
            // contract describes what the core actually writes.
            'more',
            'mdot',

            // THE KPI PLATE, and the strip it sits in.
            'kpi',
            'kstrip',

            // THE REGISTER TABLE, THE META ROW, and the pager under them.
            'tbl',
            'rln',          // a label and its value, dashed between
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
     * THE PAGE-ACTION ROW IS THE FRAME'S, WHOLE. `page.html.twig` writes
     * `<div class="pgact">` itself and gives a module filling
     * `shell_page_actions` no way to add a class to it, so the row's layout can
     * only be stated here. A module that needs two actions side by side and
     * finds `.pgact` is not a row has one move left — restate `.pgact` in its
     * own sheet — and that is the drift the vocabulary conformance test forbids.
     *
     * The values are the design's, read from the two sheets that between them
     * define the row: the slot in nav.css and the row in widgets.css, which
     * every screen wears together as `class="pgact w-pgact"` (129 of the design's
     * 136 page heads). Where the two disagree — the icon size — widgets.css is
     * linked after nav.css at equal specificity and wins, so 14px is what the
     * design renders.
     *
     * @see /Users/eemjema/Programming/DesignsProjects/uhifadhi-web/nav.css lines 86-89
     * @see /Users/eemjema/Programming/DesignsProjects/uhifadhi-web/widgets.css lines 229-246
     */
    #[DataProvider('pageActionRowDeclarations')]
    public function testTheFrameLaysOutThePageActionRow(string $selector, string $property, string $value): void
    {
        $rule = $this->rule($selector);

        self::assertMatchesRegularExpression(
            '/(?:^|;)\s*'.preg_quote($property, '/').'\s*:\s*'.preg_quote($value, '/').'\s*(?:;|$)/',
            $rule,
            \sprintf(
                '%s must state `%s: %s` — the design\'s own value. Without it a module with two page '
                .'actions has to restate the row in its own sheet, and the same header renders differently '
                .'on every screen that does.',
                $selector,
                $property,
                $value,
            ),
        );
    }

    /**
     * EVERY CONTROL IN THE ROW SHARES ONE BASELINE, and this is the test
     * that says so in numbers.
     *
     * A page head may hold a bare button, a labelled select and a
     * segmented group at once. They are three different kinds of thing
     * and they must read as one row: the same 32px box, bottom-aligned,
     * with any caption in a line above rather than inside. The rule
     * lives in the frame — `page.html.twig` writes the wrapper and a
     * module cannot add a class to it — so a module that needs a
     * labelled control gets the alignment without asking.
     */
    #[DataProvider('oneBaselineDeclarations')]
    public function testEveryControlInThePageActionRowSharesOneBaseline(string $selector, string $property, string $value): void
    {
        self::assertMatchesRegularExpression(
            '/(?:^|;)\s*'.preg_quote($property, '/').'\s*:\s*'.preg_quote($value, '/').'\s*(?:;|$)/',
            $this->rule($selector),
            \sprintf('%s must state `%s: %s`, or the row reads as three heights on one line.', $selector, $property, $value),
        );
    }

    /**
     * @return \Generator<string, array{string, string, string}>
     */
    public static function oneBaselineDeclarations(): \Generator
    {
        $declarations = [
            // A LABELLED CONTROL IS A COLUMN: caption above, box below.
            '.pgact .ov-ctl' => ['display' => 'flex', 'flex-direction' => 'column', 'gap' => '4px'],
            // AND THE SEGMENTED GROUP AND THE CHIP BESIDE IT are the row's
            // own height, like everything else in it.
            '.pgact .periodpick' => ['height' => '32px', 'box-sizing' => 'border-box'],
            '.pgact .mchip' => ['height' => '32px', 'box-sizing' => 'border-box'],
            // The buttons inside the group fill it without growing it.
            '.pgact .periodpick button' => ['height' => '30px', 'border-radius' => '0'],
        ];

        foreach ($declarations as $selector => $properties) {
            foreach ($properties as $property => $value) {
                yield \sprintf('%s { %s }', $selector, $property) => [$selector, $property, $value];
            }
        }
    }

    /**
     * @return \Generator<string, array{string, string, string}>
     */
    public static function pageActionRowDeclarations(): \Generator
    {
        $declarations = [
            // The row itself: a wrapping line of equal-height controls, held to
            // its content beside a title that may run long.
            '.pgact' => [
                'display' => 'flex',
                // THE ROW ALIGNS ON ITS BOTTOM EDGE. A labelled control — a
                // caption above a field — is taller than a bare button, so a
                // row centred on its middle put Configure half a caption
                // higher than the selects beside it, which is the
                // misalignment the owner caught on the performance header.
                // Every box is 32px, so aligning bottoms aligns tops.
                'align-items' => 'flex-end',
                'flex-wrap' => 'wrap',
                'gap' => '9px',
                'flex-shrink' => '0',
                'padding-top' => '4px',
            ],
            // The primary, and the whole reason the row reads as designed: every
            // control in it is one height, and only weight and fill separate the
            // primary from the quiet ones.
            '.pgact .cta' => [
                'height' => '32px',
                'padding' => '0 13px',
                'border-radius' => '9px',
                'font-size' => '12px',
                'font-weight' => '700',
                'white-space' => 'nowrap',
                'text-decoration' => 'none',
            ],
            '.pgact .cta svg' => [
                'width' => '14px',
                'height' => '14px',
            ],
        ];

        foreach ($declarations as $selector => $properties) {
            foreach ($properties as $property => $value) {
                yield $selector.' — '.$property => [$selector, $property, $value];
            }
        }
    }

    /**
     * A CARD'S ACTION CLUSTER IS ONE CLUSTER: the labelled disclosure and
     * the way into the record share a height, a radius and a baseline.
     *
     * BOTH HEIGHTS ARE STATED, not left to type and padding. That is what
     * let `Open` drift to 27 against the disclosure's 24 the day the page's
     * base line-height changed — a pair of controls two pixels out of line
     * reads as a mistake from across the room, and nothing in either rule
     * said what the height was supposed to be.
     */
    #[DataProvider('actionClusterDeclarations')]
    public function testACardsActionClusterIsOneCluster(string $selector, string $property, string $value): void
    {
        self::assertMatchesRegularExpression(
            '/(?:^|;)\s*'.preg_quote($property, '/').'\s*:\s*'.preg_quote($value, '/').'\s*(?:;|$)/',
            $this->rule($selector),
            \sprintf('%s must state `%s: %s` — the design\'s own value.', $selector, $property, $value),
        );
    }

    /**
     * @return \Generator<string, array{string, string, string}>
     */
    public static function actionClusterDeclarations(): \Generator
    {
        $declarations = [
            '.ov-open' => ['height' => '24px', 'border-radius' => '7px', 'padding' => '0 9px', 'line-height' => '1'],
            '.ovx.xdisc' => ['height' => '24px', 'border-radius' => '7px'],
        ];

        foreach ($declarations as $selector => $properties) {
            foreach ($properties as $property => $value) {
                yield $selector.' — '.$property => [$selector, $property, $value];
            }
        }
    }

    /**
     * A CARD CARRIES AT MOST ONE QUIET DOOR, AND IT IS PINNED TO THE EDGE.
     *
     * The same gesture as the identity band's `.more`, in the same type and
     * colour, drawn where a card carries its way out. Page actions belong in
     * the page header and row actions on the row, so a card with two doors is
     * two cards.
     *
     * IT IS THE SHELL'S BECAUSE IT IS THE CARD'S. The roster's tabs and the
     * house cards all want it; the first module to draw one without it here
     * would pin it with a rule of its own, which is the drift the vocabulary
     * test forbids and exactly why `.more` itself stopped being scoped to
     * `.factband`.
     *
     * `text-transform: none` IS THE ONE LINE THAT IS NOT A COPY. In the design
     * `.more` is band-scoped and the card's door inherits nothing; here `.more`
     * is unscoped on purpose, so without this the door comes out uppercase at a
     * letter-spacing meant for lower case. The inheritance is turned off rather
     * than the base rule bent.
     *
     * The values are the design's, read value for value.
     *
     * @see /Users/eemjema/Programming/DesignsProjects/uhifadhi-web/uhifadhi.css lines 323-333
     */
    #[DataProvider('cardDoorDeclarations')]
    public function testACardsQuietDoorIsPinnedToItsEdge(string $selector, string $property, string $value): void
    {
        self::assertMatchesRegularExpression(
            '/(?:^|;)\s*'.preg_quote($property, '/').'\s*:\s*'.preg_quote($value, '/').'\s*(?:;|$)/',
            $this->rule($selector),
            \sprintf('%s must state `%s: %s` — the design\'s own value.', $selector, $property, $value),
        );
    }

    /**
     * @return \Generator<string, array{string, string, string}>
     */
    public static function cardDoorDeclarations(): \Generator
    {
        $declarations = [
            '.c > .more' => [
                'position' => 'absolute',
                'top' => '-8px',
                'right' => '13px',
                'z-index' => '2',
                'font-size' => '9.5px',
                'letter-spacing' => '.06em',
                // Not uppercase: the base rule is unscoped here and the design's
                // card door is lower case.
                'text-transform' => 'none',
                'padding' => '3px 9px',
                'border-radius' => '7px',
                'white-space' => 'nowrap',
            ],
        ];

        foreach ($declarations as $selector => $properties) {
            foreach ($properties as $property => $value) {
                yield $selector.' — '.$property => [$selector, $property, $value];
            }
        }
    }

    /**
     * THE FOCUS LINE IS THE ONE LEFT MARK A CARD MAY CARRY.
     *
     * RULED 2026-09-21. It means FOCUS — this is the card the reader is on.
     * Not open, not selected-and-showing, and never a CATEGORY: a category is
     * a chip or an 8px hue dot, a state is a chip or a stamp. One class draws
     * every focus mark in the product, so a preset card and a register card
     * cannot come out two different widths.
     *
     * PAINT ONLY, AND THAT IS WHY IT IS A PSEUDO-ELEMENT. A border would add
     * to the box and shove everything below the card down the moment focus
     * arrived; `::after` over the card's own border line changes nothing.
     *
     * INSET BY THE RADIUS. 13px top and bottom is the card's corner radius,
     * so the line starts where the top corner ends and stops where the bottom
     * corner begins rather than crossing them.
     *
     * The values are the design's, read value for value.
     *
     * @see /Users/eemjema/Programming/DesignsProjects/uhifadhi-web/uhifadhi.css lines 323-325
     */
    #[DataProvider('focusLineDeclarations')]
    public function testTheFocusLineIsTheOneLeftMarkACardMayCarry(string $selector, string $property, string $value): void
    {
        self::assertMatchesRegularExpression(
            '/(?:^|;)\s*'.preg_quote($property, '/').'\s*:\s*'.preg_quote($value, '/').'\s*(?:;|$)/',
            $this->rule($selector),
            \sprintf('%s must state `%s: %s` — the design\'s own value.', $selector, $property, $value),
        );
    }

    /**
     * @return \Generator<string, array{string, string, string}>
     */
    public static function focusLineDeclarations(): \Generator
    {
        $declarations = [
            // The card is the containing block, or the line lands on the page.
            '.focusline' => ['position' => 'relative'],
            '.focusline::after' => [
                'content' => '""',
                'position' => 'absolute',
                // On the card's own border line, not beside it.
                'left' => '-1px',
                // Inset by the card's 13px radius, top and bottom.
                'top' => '13px',
                'bottom' => '13px',
                'width' => '2px',
                'border-radius' => '2px',
                // Never a hue: focus is the accent, and a category is not focus.
                'background' => 'rgb(var(--c-acc))',
                'z-index' => '2',
                // It is a mark, not a target.
                'pointer-events' => 'none',
            ],
        ];

        foreach ($declarations as $selector => $properties) {
            foreach ($properties as $property => $value) {
                yield $selector.' — '.$property => [$selector, $property, $value];
            }
        }
    }

    /**
     * AND IT IS PAINT, NOT BOX. A border on `.focusline` itself would move
     * every card below it the moment focus arrived, which is the whole reason
     * the mark is a pseudo-element.
     */
    public function testTheFocusLineAddsNothingToTheBox(): void
    {
        $rule = $this->rule('.focusline');

        self::assertDoesNotMatchRegularExpression(
            '/(?:^|;)\s*(?:border|padding|margin|width)\s*:/',
            $rule,
            'the focus line is paint: a box change would shove the page down when focus arrives.',
        );
    }

    /**
     * A BUTTON IS NOT AN ANCHOR THAT HAPPENS TO BE PRESSABLE.
     *
     * `.btn` and `.cta` are written on all three of `<a>`, `<button>` and
     * `<input type="submit">` across the product — a link out of a card, a
     * Discard beside a Save, a form that submits. The user agent gives the
     * last two a ground, a border, a font and a line box of their own, and
     * every one of them has to be turned off in the rule or the pair renders
     * as one house control beside one browser-grey button.
     *
     * `.cta` WAS THE ONE THAT GOT IT WRONG: it named no font at all, so the
     * accent button came out in the system font wherever a form submitted
     * rather than linked. `.btn` had the font and still had no `appearance`
     * and no line-height.
     *
     * LINE-HEIGHT IS `inherit`, NOT A NUMBER. A button's UA line-height is
     * `normal` and an anchor takes the page's, which is what sat the two a
     * few pixels apart; inheriting makes the button match the anchor and
     * moves the anchor not at all, where a stated number would move both.
     *
     * WHAT THIS PROMISES AND WHAT IT DOES NOT. It is a check over the SHEET,
     * like the plate's height: it says the rule carries the declarations that
     * make the three element types render alike, and — with the sibling test
     * below — that nothing narrows the rule to one of them. It cannot say the
     * three COMPUTE alike, because no browser runs here; that is a sweep.
     */
    #[DataProvider('buttonNeutraliserDeclarations')]
    public function testTheButtonRulesReachEveryElementTypeTheyAreWrittenOn(string $selector, string $property, string $value): void
    {
        self::assertMatchesRegularExpression(
            '/(?:^|;)\s*'.preg_quote($property, '/').'\s*:\s*'.preg_quote($value, '/').'\s*(?:;|$)/',
            $this->rule($selector),
            \sprintf(
                '%s must state `%s: %s`, or a <button> wearing it renders as the browser\'s own.',
                $selector,
                $property,
                $value,
            ),
        );
    }

    /**
     * @return \Generator<string, array{string, string, string}>
     */
    public static function buttonNeutraliserDeclarations(): \Generator
    {
        // The four a user agent supplies for a <button> and would otherwise
        // win: the chrome, the font, the line box, and the ground the house
        // rule states for itself.
        $neutralisers = [
            'appearance' => 'none',
            '-webkit-appearance' => 'none',
            'font-family' => 'inherit',
            'line-height' => 'inherit',
        ];

        foreach (['.btn', '.cta'] as $selector) {
            foreach ($neutralisers as $property => $value) {
                yield $selector.' — '.$property => [$selector, $property, $value];
            }
        }

        // And the house's own ground, border and radius, stated on both so
        // neither falls back to anything.
        yield '.btn — border-radius' => ['.btn', 'border-radius', '9px'];
        yield '.cta — border-radius' => ['.cta', 'border-radius', '9px'];
        yield '.btn — cursor' => ['.btn', 'cursor', 'pointer'];
        yield '.cta — cursor' => ['.cta', 'cursor', 'pointer'];
    }

    /**
     * AND NOTHING NARROWS THEM TO AN ELEMENT TYPE.
     *
     * A single `a.btn` anywhere in the chain would make the rule an anchor's
     * rule, and every `<button class="btn">` in the product would quietly
     * stop being a house control. The neutralisers above are only worth
     * stating if the selector they are stated on reaches all three.
     */
    public function testNoSheetNarrowsAButtonRuleToOneElementType(): void
    {
        $narrowed = [];
        foreach (['btn', 'cta'] as $class) {
            if (1 === preg_match('/\b(?:a|button|input)\.'.$class.'\b/', $this->stylesheet(), $match)) {
                $narrowed[] = $match[0];
            }
        }

        self::assertSame(
            [],
            $narrowed,
            'a rule typed to one element is a rule the other two element types do not get.',
        );
    }

    /**
     * A FILTER ROW IS ONE LINE, AND THE PANEL UNDER IT IS ONE PANEL.
     *
     * FOUR BUNDLES DRAW THIS ROW — the incidents register, patrol's list, the
     * zones pages and the stations register — and every one of them writes
     * the shell's classes and nothing of its own. So the row is the shell's,
     * whole: the chip, the search field, the panel, its head, its options and
     * its dots. A module that found the panel here was the wrong panel would
     * restate it in its own sheet, which is the drift the vocabulary test
     * forbids, and four bundles would then have four filter bars.
     *
     * EVERY CONTROL IS 30px ON ONE BASELINE. The chip's own padding computes
     * to 28 and the search field to 32, which sat the row on two tops; inside
     * a filter row both are 30, the vertical padding traded for a line-height.
     *
     * THE LABEL IS NOT CLIPPED. A chip reading "all categories · 47" loses the
     * count to an ellipsis the moment the label is truncated, and the count is
     * the half the reader is filtering on.
     *
     * THE CHOSEN OPTION IS A TINTED ROW, not coloured text: a panel of twelve
     * options is scanned down its left edge, and a word two shades different
     * from its neighbours is not a selection anybody sees.
     *
     * The values are the design's, read value for value.
     *
     * @see /Users/eemjema/Programming/DesignsProjects/uhifadhi-web/uhifadhi.css lines 234-256, 1268-1278
     */
    #[DataProvider('filterRowDeclarations')]
    public function testTheFrameDrawsTheFilterRowAndItsDropdown(string $selector, string $property, string $value): void
    {
        $rule = $this->rule($selector);

        self::assertMatchesRegularExpression(
            '/(?:^|;)\s*'.preg_quote($property, '/').'\s*:\s*'.preg_quote($value, '/').'\s*(?:;|$)/',
            $rule,
            \sprintf(
                '%s must state `%s: %s` — the design\'s own value. Four bundles draw this row, and one '
                .'of them finding it wrong here restates it in its own sheet.',
                $selector,
                $property,
                $value,
            ),
        );
    }

    /**
     * @return \Generator<string, array{string, string, string}>
     */
    public static function filterRowDeclarations(): \Generator
    {
        $declarations = [
            // The row, and the one rule that puts every control on one line.
            '.lfilt' => [
                'display' => 'flex',
                'gap' => '8px',
                'align-items' => 'center',
            ],
            // Asserted a side at a time, because a grouped prelude is read
            // one selector at a time — and both sides have to carry it, which
            // is the whole point of the rule.
            '.lfilt .mchip' => [
                'height' => '30px',
                'padding-top' => '0',
                'padding-bottom' => '0',
                'line-height' => '28px',
            ],
            '.lfilt .lsearch .fld' => [
                'height' => '30px',
                'line-height' => '28px',
            ],
            // The search sits at the far end of the row, at one width.
            '.lsearch' => [
                'margin-left' => 'auto',
            ],
            '.lsearch .fld' => [
                'width' => '246px',
            ],
            // The trigger and its caret.
            '.i-dd' => [
                'display' => 'inline-flex',
            ],
            '.i-ddcaret' => [
                'font-size' => '9px',
                'opacity' => '.7',
            ],
            // The panel.
            '.i-ddmenu' => [
                'min-width' => '196px',
                'padding' => '5px',
                'gap' => '1px',
                'border-radius' => '11px',
            ],
            '.i-ddhead' => [
                'font-size' => '8.5px',
                'letter-spacing' => '.14em',
                'padding' => '6px 9px 4px',
            ],
            '.i-ddopt' => [
                'gap' => '9px',
                'font-size' => '12px',
                'padding' => '7px 9px',
                'border-radius' => '7px',
            ],
            '.i-ddopt-l' => [
                'flex' => '1',
                'white-space' => 'nowrap',
            ],
            '.i-ddopt-n' => [
                'font-size' => '10.5px',
            ],
            '.i-ddsep' => [
                'height' => '1px',
                'margin' => '4px 2px',
            ],
            '.i-dot' => [
                'width' => '8px',
                'height' => '8px',
                'border-radius' => '2px',
            ],
        ];

        foreach ($declarations as $selector => $properties) {
            foreach ($properties as $property => $value) {
                yield $selector.' — '.$property => [$selector, $property, $value];
            }
        }
    }

    /**
     * THE CHOSEN OPTION IS A TINTED ROW. Stated apart from the values above
     * because it is the one rule of the family that is a DECISION rather than
     * a measurement: the design tints the row and accents its count, and an
     * implementation that coloured the label instead would pass every size
     * assertion and still not read as a selection.
     */
    public function testTheChosenOptionIsATintedRowAndNotColouredText(): void
    {
        self::assertMatchesRegularExpression(
            '/background:\s*color-mix\(in srgb,\s*rgb\(var\(--c-acc\)\)\s*12%/',
            $this->rule('.i-ddopt.on'),
            'A panel of twelve options is scanned down its left edge; a word two shades different is not a selection anybody sees.',
        );

        self::assertMatchesRegularExpression(
            '/color:\s*rgb\(var\(--c-acc\)\)/',
            $this->rule('.i-ddopt.on .i-ddopt-n'),
            'The count on the chosen row carries the accent, so the row reads as one thing.',
        );
    }

    /**
     * THE EVIDENCE TILE SHOWS THE PICTURE STORAGE MADE. The tile family is the
     * frame's, and the module that keeps files emits the markup for it: one
     * thumbnail per photograph, drawn in the same `.sh` shell wherever the file
     * is listed, so one file looks like itself on an incident and in the files
     * hub. Until these rules shipped here, the module had markup the frame did
     * not style — a picture layer with no box, a state pill with no pill — and
     * the module's only move was to restate the family in its own sheet, which
     * is the drift the vocabulary conformance test forbids.
     *
     * The values are the design's, read value for value.
     *
     * @see /Users/eemjema/Programming/DesignsProjects/uhifadhi-web/uhifadhi.css lines 1117-1120, 1139-1159
     */
    #[DataProvider('evidenceTileDeclarations')]
    public function testTheFrameDrawsTheEvidenceTilesThumbnailAndItsStates(string $selector, string $property, string $value): void
    {
        $rule = $this->rule($selector);

        self::assertMatchesRegularExpression(
            '/(?:^|;)\s*'.preg_quote($property, '/').'\s*:\s*'.preg_quote($value, '/').'\s*(?:;|$)/',
            $rule,
            \sprintf(
                '%s must state `%s: %s` — the design\'s own value. Without it the module that ships the '
                .'markup has to restate the tile family in its own sheet.',
                $selector,
                $property,
                $value,
            ),
        );
    }

    /**
     * @return \Generator<string, array{string, string, string}>
     */
    public static function evidenceTileDeclarations(): \Generator
    {
        $declarations = [
            // The picture layer: a link filling the tile, clipped to the tile's
            // own radius rather than to a radius of its own.
            '.upl-tile.done .sh' => [
                'position' => 'absolute',
                'inset' => '0',
                'display' => 'block',
                'border-radius' => 'inherit',
                'overflow' => 'hidden',
                'text-decoration' => 'none',
            ],
            '.upl-tile.done .sh img' => [
                'width' => '100%',
                'height' => '100%',
                'object-fit' => 'cover',
                'display' => 'block',
            ],
            // It is a link, so it is reachable by keyboard and says so inside
            // its own edge — an outline outside it would be clipped away.
            '.upl-tile.done .sh:focus-visible' => [
                'outline' => '2px solid var(--acc)',
                'outline-offset' => '-2px',
            ],
            // The radial ground is the EMPTY photo slot; under an actual picture
            // a flat ground is what a transparent thumbnail shows through to.
            '.upl-tile.done.shot' => [
                'background' => '#20241F',
            ],
            // Nothing to look at yet, so the tile does not pretend to open.
            '.upl-tile.done.making' => ['cursor' => 'default'],
            '.upl-tile.done.nothumb' => ['cursor' => 'default'],
            // The state pill, on the remove control's line.
            '.upl-tile .th' => [
                'position' => 'absolute',
                'left' => '5px',
                'top' => '5px',
                'height' => '20px',
                'text-transform' => 'uppercase',
            ],
            '.upl-tile.making .th' => [
                'color' => '#F0C368',
                'border-color' => 'rgba(240, 195, 104, .45)',
            ],
            '.upl-tile.nothumb .th' => [
                'color' => '#B9C6BB',
                'border-style' => 'dashed',
            ],
            // A kept DOCUMENT is not a photograph: the same box, the interface's
            // ground, and the chrome in tokens rather than in the photo overlay's
            // literals.
            '.upl-tile.done.doc' => [
                'background' => 'color-mix(in srgb, var(--fog) 8%, transparent)',
                'color' => 'var(--fog)',
            ],
            '.upl-tile.done.doc .fn' => [
                'color' => 'var(--fog)',
                'background' => 'none',
                'padding' => '0',
            ],
            '.upl-tile.done.doc .rm' => [
                'background' => 'var(--cv)',
                'border-color' => 'var(--ln2)',
                'color' => 'var(--fog)',
            ],
            '.upl-tile.done.doc .rm:hover' => [
                'color' => 'var(--fail)',
                'border-color' => 'color-mix(in srgb, var(--fail) 55%, transparent)',
            ],
        ];

        foreach ($declarations as $selector => $properties) {
            foreach ($properties as $property => $value) {
                yield $selector.' — '.$property => [$selector, $property, $value];
            }
        }
    }

    /**
     * NO STATE CHANGES THE TILE'S SIZE OR ITS RADIUS — the promise the family's
     * own comment makes, and the reason a grid of tiles does not jump under the
     * pointer as one of them finishes uploading or grows a thumbnail. The box is
     * stated once, on `.upl-tile`; every state rule may change the ground, the
     * border colour and what is inside, and nothing else.
     *
     * `border-radius: inherit` on the picture layer is the one exception and is
     * the opposite of a drift: it takes the tile's radius rather than naming one.
     */
    public function testNoStateOfTheTileRestatesTheBox(): void
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $this->stylesheet());

        preg_match_all('/([^{}@]*\.upl-tile[^{}@]*)\{([^{}]*)\}/s', $css, $matches, \PREG_SET_ORDER);
        self::assertNotSame([], $matches, 'The tile family ships in the frame\'s sheet.');

        $offenders = [];
        foreach ($matches as $match) {
            $selector = trim((string) preg_replace('/\s+/', ' ', $match[1]));
            if ('.upl-tile' === $selector) {
                continue;
            }

            foreach (['aspect-ratio', 'width', 'height', 'border-radius'] as $property) {
                if (1 !== preg_match('/(?:^|;)\s*'.$property.'\s*:\s*([^;]+)/', $match[2], $stated)) {
                    continue;
                }

                // The parts inside the box have their own size; only a rule on
                // the tile itself would move the grid.
                if (!str_ends_with($selector, '.upl-tile') && !preg_match('/\.upl-tile[\w.]*$/', $selector)) {
                    continue;
                }

                if ('inherit' === trim($stated[1])) {
                    continue;
                }

                $offenders[] = $selector.' { '.$property.': '.trim($stated[1]).' }';
            }
        }

        sort($offenders);

        self::assertSame([], $offenders, \sprintf(
            'A state restates the tile\'s box: [%s]. A tile that resized while uploading would make the '
            .'grid jump under the pointer.',
            implode(', ', $offenders),
        ));
    }

    /**
     * The declarations of one rule, by exact selector. A selector written as
     * part of a comma-separated group counts: the group is how a sheet states
     * one rule for several selectors, and the row's height is stated that way.
     */
    private function rule(string $selector): string
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $this->stylesheet());
        $quoted = preg_quote($selector, '/');

        // A prelude cannot contain a brace, so it starts where the rule before
        // it ended without the two rules having to share the brace between them.
        preg_match_all('/([^{}@]*)\{([^{}]*)\}/s', $css, $matches, \PREG_SET_ORDER);

        $declarations = [];
        foreach ($matches as $match) {
            foreach (explode(',', $match[1]) as $written) {
                if (1 === preg_match('/^\s*'.$quoted.'\s*$/', (string) preg_replace('/\s+/', ' ', $written))) {
                    $declarations[] = trim($match[2]);
                }
            }
        }

        self::assertNotSame([], $declarations, $selector.' is stated nowhere in the frame\'s sheet.');

        return implode(';', $declarations);
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
