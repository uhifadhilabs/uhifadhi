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

namespace Uhifadhi\Contracts\Settings;

/**
 * WHOEVER RECORDS A CHANGE TO THE INSTALLATION PUBLISHES IT HERE.
 *
 * The card is bounded — the latest few, newest first, with the way into
 * whatever keeps the rest — so a source returns its own latest few rather
 * than everything it has and lets the section merge and cut. A source that
 * returned a year of rows would pay for a page nobody asked for.
 *
 * Tag it by hand; see {@see SettingsFigureSourceInterface} on why.
 */
interface SettingsChangeSourceInterface
{
    public const string TAG = 'shell.settings_change';

    /**
     * @param positive-int $limit the most this source need return
     *
     * @return iterable<SettingsChange>
     */
    public function settingsChanges(int $limit): iterable;
}
