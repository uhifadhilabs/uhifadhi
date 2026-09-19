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

namespace Uhifadhi\Contracts\Storage;

/**
 * A MODULE SAYING THAT IT STORES FILES, AND WHAT IT CALLS ONE.
 *
 * TWO FACTS, AND THEY ARE THE ONLY TWO THE FILES HUB CANNOT WORK OUT. How
 * many files a module holds and how many bytes they are is on the file rows
 * already; which record a file belongs to is on the file; whether it may be
 * removed is the owning record's answer. What no row anywhere says is that a
 * module stores files AT ALL — an installed module with nothing stored yet
 * and one that will never store anything look identical — and what the module
 * calls them in the words its own people use.
 *
 * WHY THE FIRST FACT MATTERS. Two of four installed modules may store files
 * and two may not, and the difference is invisible on a register: a reader
 * asking "why is Roster not here" has nowhere to look. A module that declares
 * itself is drawn as storing nothing; one that does not declare is drawn as
 * declaring none, and those are different answers.
 *
 * WHY THE SECOND. "Evidence", "an observation's photographs", "an
 * application's documents" — the hub has no word of its own for somebody
 * else's files and must not invent one. The phrase is the module's, printed
 * verbatim, exactly as a configure section's label is.
 *
 * IT DECLARES NO SCHEMA AND NO STORE. A module does not tell the hub where
 * its bytes are or how to read them; the hub already knows both, because the
 * files were written through the storage the installation configured. This
 * seam adds a sentence, not a capability.
 *
 * NOTHING IS UPLOADED THROUGH IT EITHER. A file arrives by being attached to
 * a record, on that record's own page; the hub browses and manages and never
 * overrules the record that owns the file.
 *
 * TAGGED EXPLICITLY AT BOTH ENDS. Nothing autoconfigures a reusable bundle's
 * services, and an `#[AutoconfigureTag]` on this interface would be silently
 * dead — PHP does not inherit attributes from an interface, and the only
 * symptom would be a module that stores thousands of files reading as
 * declaring none.
 */
interface FileSourceInterface
{
    public const string TAG = 'uhifadhi.file_source';

    /**
     * The module this source belongs to, and it must be the same slug the
     * module's `ModuleProviderInterface` returns: that is how a source
     * disappears when an installation removes the module.
     */
    public function moduleSlug(): string;

    /**
     * WHAT THIS MODULE CALLS A FILE, in its own words and in the plural a
     * reader would say — "an observation's photographs", "evidence —
     * photographs and documents".
     *
     * It is a phrase and not a noun, because a single noun cannot say whose
     * the files are, and whose is the half of the answer that tells a reader
     * where to go and change one.
     */
    public function fileWord(): string;
}
