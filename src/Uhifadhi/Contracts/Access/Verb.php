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

namespace Uhifadhi\Contracts\Access;

/**
 * SIX VERBS, AND NO MORE.
 *
 * Every concern in the product is acted on through the same six words. They
 * are fixed here, in the contracts, so that a module cannot invent a seventh:
 * a matrix whose columns differ per module is a matrix nobody can read across,
 * and an organization deciding who may delete would have to learn a new word
 * for it in every group.
 *
 * A concern declares WHICH of the six it supports ({@see ConcernInterface::verbs()}),
 * and the matrix draws a cell only where it does — so there is never a
 * checkbox that would mean nothing.
 *
 * TWO OF THE SIX ARE SEPARATED DELIBERATELY. {@see self::Delete} is kept apart
 * from {@see self::Manage} because managing is the ordinary work of a
 * supervisor and deleting is not recoverable; somebody can be trusted with one
 * and not the other. {@see self::Export} is kept apart from {@see self::Read}
 * for the same kind of reason: reading a case on screen and carrying it out of
 * the building are different acts.
 *
 * ADMINISTERING THE TEAM IS NOT A SEVENTH VERB. It is the team's own concerns
 * with {@see self::Configure} and {@see self::Delete} on them.
 */
enum Verb: string
{
    case Read = 'read';
    case Record = 'record';
    case Manage = 'manage';
    case Configure = 'configure';
    case Delete = 'delete';
    case Export = 'export';

    /** The column head in the grants matrix. */
    public function label(): string
    {
        return match ($this) {
            self::Read => 'Read',
            self::Record => 'Record',
            self::Manage => 'Manage',
            self::Configure => 'Configure',
            self::Delete => 'Delete',
            self::Export => 'Export',
        };
    }

    /** The sentence printed under the column, for the person handing the power over. */
    public function meaning(): string
    {
        return match ($this) {
            self::Read => 'Open a page or a record, and see the figures.',
            self::Record => 'Create a fact from the field — a patrol, an incident, a check-in, an observation, a file on a record.',
            self::Manage => 'Act on facts other people recorded: hold, resolve, publish, approve, amend, end an assignment, remove a file.',
            self::Configure => 'Set what a module or a section runs on: types, lists, stations and their catchments, rotations, storage targets, a module being on or off.',
            self::Delete => 'Remove something irreversibly.',
            self::Export => 'Take data out of the system: a spreadsheet, a track file, a report, an API bundle.',
        };
    }
}
