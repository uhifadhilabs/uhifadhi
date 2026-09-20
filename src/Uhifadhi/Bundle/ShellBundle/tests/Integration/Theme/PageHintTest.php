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

use Uhifadhi\Bundle\ShellBundle\Tests\Integration\ContractTestCase;

/**
 * THE PAGE HINT — one row, at the bottom, where a page has something to
 * explain.
 *
 * RULED: a page that needs guidance says it ONCE, as a fragment, under the
 * thing it is about. The files hub carried two cards of prose ABOVE its
 * register and the design deleted them: somebody comes to a register to find
 * something, and the explanation is what they want after they have failed to.
 * A hint above the content is read by everybody on every visit forever; one
 * below it is read by the person still wondering.
 *
 * AND THE SHELL DRAWS IT, because the same dashed-accent card had been drawn
 * four times in four module sheets under four names before the design hoisted
 * it. What is checked here is the component; that no module ships a fifth
 * copy is the conformance suite's, and it is watched failing on a fixture.
 */
final class PageHintTest extends ContractTestCase
{
    public function testTheHintIsOneRowWithALeadAndTheRestOfIt(): void
    {
        $html = $this->render('@Shell/_page_hint.html.twig', [
            'lead' => 'Every file belongs to a record.',
            'say' => 'Browse, find, check, tidy — never upload.',
        ]);

        self::assertStringContainsString('class="pghint"', $html);
        self::assertStringContainsString('<b>Every file belongs to a record.</b>', $html);
        self::assertStringContainsString('Browse, find, check, tidy — never upload.', $html);
    }

    /** It carries a mark, and the shell's own where a page names none. */
    public function testTheHintCarriesAnIconAndTheShellsOwnByDefault(): void
    {
        $plain = $this->render('@Shell/_page_hint.html.twig', ['lead' => 'A.', 'say' => 'B.']);
        $named = $this->render('@Shell/_page_hint.html.twig', ['lead' => 'A.', 'say' => 'B.', 'icon' => 'shell:map']);

        self::assertStringContainsString('<svg', $plain);
        self::assertNotSame($plain, $named, 'a page may name its own mark');
    }

    /**
     * THE VOCABULARY IS THE SHELL'S, and it is the design's values: a dashed
     * accent edge, because this is the one place a page speaks in its own
     * voice rather than showing somebody's data.
     */
    public function testTheHintsVocabularyIsShippedByTheShell(): void
    {
        $css = $this->stylesheet();

        self::assertMatchesRegularExpression('/\.pghint \{[^}]*border: 1px dashed/s', $css);
        self::assertMatchesRegularExpression('/\.pghint \{[^}]*margin: 22px 0 0;/s', $css, 'it sits under the content');
        self::assertStringContainsString('.pghint svg {', $css);
        self::assertStringContainsString('.pghint b {', $css);
    }
}
