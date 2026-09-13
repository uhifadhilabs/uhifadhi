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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Integration\Chrome;

use PHPUnit\Framework\Attributes\DataProvider;
use Uhifadhi\Bundle\ShellBundle\Model\TimeShape;
use Uhifadhi\Bundle\ShellBundle\ShellBundle;
use Uhifadhi\Bundle\ShellBundle\Tests\Integration\ContractTestCase;

/**
 * SPEC — THE FRAME DRAWS THE STAMP THE DESIGN DRAWS.
 *
 * The frame localised every `<time>` from the day it shipped, and the modules
 * did not use it. The reason was not doubt about the mechanism: it offered three
 * shapes, all of them Intl's own verbose readings — "Sep 7, 2026, 11:00 PM" —
 * and every design in the product draws a compact monospace stamp instead. A
 * module asked to choose between the right zone and the right typography chose
 * the typography, printed the instant server-side, and the reader three
 * timezones away read it wrong. Telemetry wrote the reason down in a template
 * comment and opted out.
 *
 * So the shapes are the fix, and they belong here rather than in each module:
 * one list, rendered one way, by the frame that already owns the rewriting.
 *
 * WHAT THIS FILE PINS. Not the rendered string — no browser runs in this suite,
 * and an Intl reading is the browser's to produce. What it pins is everything
 * that would silently stop producing it: that the frame answers every shape the
 * core names and no shape it does not, that the compact shapes are assembled
 * from Intl PARTS (so a locale's own month name survives) rather than from a
 * format string, that the clock is forced to 24 hours, that the separator is
 * the house middle dot, and that a node inserted after the first paint is swept
 * too.
 */
final class LocaltimeShapeTest extends ContractTestCase
{
    /**
     * ONE LIST, BOTH SIDES. The names live in JavaScript because the rewriting
     * happens in the browser; the enum is what a template, a test and a
     * document can read. A shape in one and not the other is a template that
     * asks for a stamp and silently gets Intl's paragraph.
     */
    public function testTheFrameAnswersEveryShapeTheCoreNamesAndNoOther(): void
    {
        preg_match_all("/^\s+[A-Z]+: '([a-z]+)',$/m", self::controller(), $answered);

        $shapes = array_values(array_unique($answered[1]));
        sort($shapes);

        $named = TimeShape::names();
        sort($named);

        self::assertSame($named, $shapes);
    }

    /**
     * A MONTH NAME IS THE LOCALE'S, THE ORDER AND THE PUNCTUATION ARE THE
     * HOUSE'S. `format()` would hand back "Sep 12, 2026" — the locale's order,
     * the locale's comma and a capital — so the compact shapes read
     * `formatToParts` and assemble the parts themselves, lowercasing the names
     * Intl produced. Losing this is not a crash; it is a design that quietly
     * stops matching.
     */
    public function testTheCompactShapesAreAssembledFromLocaleParts(): void
    {
        $js = self::controller();

        self::assertStringContainsString('formatToParts', $js, 'A compact shape is built from parts, not from a format string.');
        self::assertStringContainsString('toLocaleLowerCase', $js, 'The design draws the month name in lower case.');
        self::assertStringContainsString("const DOT = ' \u{B7} ';", $js, 'The separator is the middle dot with a space on either side.');
        self::assertStringContainsString("hourCycle: 'h23'", $js, 'The product reads a 24-hour clock; the locale does not get to decide.');
        self::assertStringContainsString('Intl.DateTimeFormat(undefined', $js, 'No locale and no timeZone argument: the reader\'s own is the point.');
        self::assertStringNotContainsString('timeZone:', $js, 'Naming a zone would defeat viewer-local formatting.');
    }

    /**
     * A CALENDAR DAY IS LEFT ALONE. `datetime="2026-08-19"` is a day — a day
     * key, a calendar cell, a date a form collected — and a day is the same day
     * in every zone. Read as an instant it is midnight UTC, so a reader west of
     * Greenwich would be shown the day before: the one case where localising an
     * element makes it wrong. The time part is what separates the two, so the
     * frame skips any value without one.
     */
    public function testACalendarDayIsNotAnInstantAndIsLeftAsItIs(): void
    {
        self::assertStringContainsString(
            "includes('T')",
            self::controller(),
            'Without the time-part test, a date-only value is localised as midnight UTC and moves a day.',
        );
    }

    /**
     * A TIME THAT ARRIVES AFTER THE FIRST PAINT IS LOCALISED TOO. Half the
     * instants in the product are not in the first response: a Turbo
     * navigation swaps the body, a filter swaps a region, the widget canvas
     * clones its cards out of `<template>` elements, and a fetch fills a tail.
     * A sweep that only ran on connect would leave every one of those in the
     * server's zone — and it would look right on the first load, which is the
     * load a developer checks.
     */
    public function testATimeInsertedAfterTheFirstPaintIsLocalisedToo(): void
    {
        $js = self::controller();

        self::assertStringContainsString('MutationObserver', $js);
        self::assertStringContainsString('childList: true, subtree: true', $js, 'A time nested in an inserted subtree counts.');
        self::assertStringContainsString('turbo:load', $js, 'A Turbo-navigated page must localise too.');
        self::assertStringContainsString('turbo:render', $js);
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function pages(): \Generator
    {
        yield 'the bare document' => ['@fixtures/bare_document_page.html.twig'];
        yield 'the framed page' => ['@fixtures/bare_shell_page.html.twig'];
        yield 'a module page' => ['@fixtures/module_page.html.twig'];
        yield 'a page filling only the body' => ['@fixtures/body_only_page.html.twig'];
        yield 'a page with body attributes of its own' => ['@fixtures/body_attributes_page.html.twig'];
    }

    /**
     * EVERY PAGE THE SHELL FRAMES, not only the one the mechanism was written
     * against. The scanner rides on the `<body>` of the document every layout
     * descends from, so a page that fills one socket and a page that draws the
     * whole frame both localise; a layout that grew a `<body>` of its own would
     * be a page whose times stayed in the server's zone, and only this says so.
     */
    #[DataProvider('pages')]
    public function testEveryPageTheShellFramesMountsTheScanner(string $page): void
    {
        self::assertMatchesRegularExpression(
            '/<body[^>]*\bdata-controller="[^"]*'.preg_quote(ShellBundle::CONTROLLER_PREFIX.'localtime', '/').'/',
            $this->render($page),
        );
    }

    /**
     * ONE `<body>` IN THE WHOLE BUNDLE, so there is one place the scanner can
     * be mounted and one place it can be lost from. A second layout with a body
     * tag of its own is the way this defect comes back.
     */
    public function testOnlyTheDocumentDrawsABody(): void
    {
        $drawing = [];
        foreach (glob(\dirname(__DIR__, 3).'/templates/*.html.twig') ?: [] as $path) {
            $twig = (string) preg_replace('/\{#.*?#\}/s', '', (string) file_get_contents($path));
            if (str_contains($twig, '<body')) {
                $drawing[] = basename($path);
            }
        }

        self::assertSame(['document.html.twig'], $drawing);
    }

    private static function controller(): string
    {
        $js = file_get_contents(\dirname(__DIR__, 3).'/assets/controllers/localtime_controller.js');
        self::assertIsString($js);

        return $js;
    }
}
