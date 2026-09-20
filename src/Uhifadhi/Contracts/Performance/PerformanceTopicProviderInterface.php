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

namespace Uhifadhi\Contracts\Performance;

use Uhifadhi\Contracts\Kpi\FigurePeriod;

/**
 * HOW A MODULE PUBLISHES A TOPIC ON THE PERFORMANCE PAGE.
 *
 * A TOPIC, NOT A COLUMN. The board this replaces imposed one module's
 * columns on every department, and half its cells were about departments
 * that had never attached the module — a matrix of comparable figures
 * where the figures were not comparable. So a module publishes its OWN
 * page section instead: four headline figures, two or three charts, and a
 * matrix of the departments that actually read it. Adding a module adds a
 * topic and touches nothing else — no host column changes, no department
 * row changes, no shared list to edit.
 *
 * THE HOST PUBLISHES ITS OWN THE SAME WAY. Staffing, Goals and Attention
 * are figures every department has whatever it attaches, so they are the
 * host's topics — and they are producers of exactly this shape, so one
 * renderer draws all of them and the host's cannot quietly acquire an
 * ability a module's lacks.
 *
 * THE ORDER IS RULED: the host's topics first, then the module topics in
 * the ORDER THE AREA RUNS THEM — the order its Modules tab lists them in,
 * which is the order somebody arranged. Alphabetical would be the
 * dictionary's opinion about an organisation's priorities.
 *
 * SCOPE AND PERIOD ARE ASKED, NEVER ASSUMED. A topic is drawn for the
 * whole organisation or for one area, over a month, a quarter or a year,
 * and a provider that ignored either would put the organisation's figures
 * on an area's page — the one mistake a director cannot see from the page.
 *
 * NULL IS NEVER NOUGHT. A figure nobody published is null and every
 * surface says so in words; a period nobody wrote down is a hole in the
 * history, and a department a column does not apply to is
 * {@see MatrixCell::notMine()}. Three different absences, three different
 * marks, because collapsing them turns "nobody measured" into "measured
 * nothing".
 *
 * HOW AN IMPLEMENTOR IS COLLECTED. The {@see TAG} is applied EXPLICITLY at
 * both ends: a module bundle tags its provider in its extension, because a
 * reusable bundle is not autoconfigured; a service in an application
 * carries `#[AutoconfigureTag(self::TAG)]` ON ITS OWN CLASS, because
 * Symfony reads autoconfigure attributes off the definition's own class
 * and PHP does not inherit attributes from an interface.
 */
interface PerformanceTopicProviderInterface
{
    /** The tag that puts a topic on the page. */
    public const string TAG = 'uhifadhi.performance_topic';

    /**
     * THE SLUG A HOST TOPIC ANSWERS WITH. The underscore is what makes it
     * safe: a module slug is lowercase letters only, so no module written
     * by anybody can claim it.
     */
    public const string HOST = '_host';

    /**
     * WHOSE TOPIC THIS IS — a module's slug, the same one its
     * {@see \Uhifadhi\Contracts\ModuleProviderInterface::slug()} returns,
     * or {@see HOST} for one of the host's own. That is how a topic
     * disappears when an area switches the module off, and how the page
     * knows which order to draw the rest in.
     */
    public function moduleSlug(): string;

    /** The topic's own address: `staffing`, `patrols`. */
    public function key(): string;

    /** What it is called, in the module's own words. */
    public function title(): string;

    /**
     * EXACTLY FOUR HEADLINE FIGURES, in one row — ruled 2026-09-21, and
     * the same rule every figure row in the product keeps: four to a
     * row, never five, and never two rows on a record. A fifth card
     * wrapped an orphan onto a second line on a small laptop, and the
     * owner's verdict on that was plain.
     *
     * A TOPIC WITH LESS TO SAY FILLS THE SLOT with a figure that states
     * its own absence: a row of three where the design has four is a
     * different design, and a reader cannot tell a short row from a
     * quiet month.
     *
     * A TOPIC WITH MORE TO SAY FOLDS, rather than dropping. Two verdicts
     * that a reader acts on the same way are one card with both in its
     * fragment — the goals topic's "Off track · 2 at risk · 1 missed" —
     * and a figure another card already implies is not a figure.
     *
     * @return list<TopicKpi>
     */
    public function kpis(PerformanceScope $scope, FigurePeriod $period): array;

    /**
     * TWO OR THREE CHARTS, each a stated shape rather than a drawing.
     *
     * @return list<TopicChart>
     */
    public function charts(PerformanceScope $scope, FigurePeriod $period): array;

    /**
     * THE DEPARTMENTS THIS TOPIC APPLIES TO, as a matrix. A department
     * that does not read the module is left out rather than drawn as a
     * row of empties.
     */
    public function matrix(PerformanceScope $scope, FigurePeriod $period): TopicMatrix;
}
