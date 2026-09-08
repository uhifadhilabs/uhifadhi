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

namespace Uhifadhi\Bundle\ShellBundle\Widget\Service;

use Doctrine\ORM\EntityManagerInterface;
use Uhifadhi\Bundle\ShellBundle\Widget\Registry\WidgetSurfaceRegistry;
use Uhifadhi\Bundle\ShellBundle\Widget\Repository\WidgetCustomPresetRepository;
use Uhifadhi\Bundle\ShellBundle\Widget\Repository\WidgetPreferenceRepository;

/**
 * PRUNE, NOT PURGE — the broom for layouts nothing claims any more.
 *
 * Removing a module leaves its stored layouts behind, and that is the design
 * rather than an oversight. `composer remove` runs no migrations and
 * un-configuration touches configuration; neither has any business deleting an
 * organisation's data. And the rows are harmless where they are: every one is
 * keyed by a surface STRING, nothing reads a surface no registry claims, and
 * putting the module back gives everybody the dashboard they had.
 *
 * So cleanup is a decision somebody makes, out loud. NOTHING HERE MAKES IT:
 * this service is wired to no event, no scheduler and no command — the core
 * ships none — and the only thing that calls {@see prune()} is a person
 * answering a prompt in devkit, which is where the platform's commands live.
 * {@see orphanedSurfaces()} is the half that answers without deleting, so that
 * prompt can show its list first.
 *
 * IT IS ONLY EVER AS RIGHT AS THE CONTAINER IT ASKS. Run it on an installation
 * with a module temporarily uninstalled and it will report that module's
 * layouts as orphaned, correctly and — if pruned — irreversibly.
 */
final readonly class WidgetPruneService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WidgetSurfaceRegistry $surfaces,
        private WidgetPreferenceRepository $preferences,
        private WidgetCustomPresetRepository $savedPresets,
    ) {
    }

    /**
     * The surfaces the database holds layouts for that no installed module
     * claims any more, in a stable order.
     *
     * @return list<string>
     */
    public function orphanedSurfaces(): array
    {
        $stored = array_unique([...$this->preferences->storedSurfaces(), ...$this->savedPresets->storedSurfaces()]);
        sort($stored);

        return array_values(array_diff($stored, $this->surfaces->surfaces()));
    }

    /**
     * Delete every stored layout for the surfaces nothing claims, and report
     * what went.
     */
    public function prune(): WidgetPruneResult
    {
        $orphans = $this->orphanedSurfaces();

        if ([] === $orphans) {
            return new WidgetPruneResult([], 0, 0);
        }

        // One transaction: an installation must never be left having lost the
        // preferences but kept the saved presets that point at the same surface.
        $rows = ['preferences' => 0, 'presets' => 0];
        $this->entityManager->wrapInTransaction(function () use ($orphans, &$rows): void {
            $rows = [
                'preferences' => $this->preferences->deleteForSurfaces($orphans),
                'presets' => $this->savedPresets->deleteForSurfaces($orphans),
            ];
        });

        /** @var array{preferences: int, presets: int} $rows */
        return new WidgetPruneResult($orphans, $rows['preferences'], $rows['presets']);
    }
}
