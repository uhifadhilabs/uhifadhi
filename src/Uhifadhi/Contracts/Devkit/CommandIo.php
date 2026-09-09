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

namespace Uhifadhi\Contracts\Devkit;

/**
 * The three streams a command handler is allowed to talk through, described
 * WITHOUT naming symfony/console.
 *
 * A {@see CommandDescriptor}'s handler is the PROCESS contract: an argument tail
 * in, an exit code out. That is most of a command, but not the whole of one — a
 * command that creates something has to say what it created, a command that
 * refuses has to say why, and a command that takes a passphrase has to read it
 * from somewhere that is not a shell history. Without a channel for those, a
 * handler's only recourse is to write to \STDOUT and read from \STDIN itself,
 * and a handler that does that has stepped outside the process it was given:
 * its output ignores `--quiet`, cannot be captured by a caller collecting the
 * command's output, and appears uninvited in a test run.
 *
 * So the descriptor hands the handler this, as the second argument. It is the
 * same trade the descriptor itself makes: three verbs are the lowest common
 * denominator of talking to a terminal, they need nothing from a framework, and
 * devkit — which legitimately requires symfony/console — is where they are
 * finally wired to a real {@see \Symfony\Component\Console\Output\OutputInterface}.
 *
 * IT IS NOT AN OutputInterface, and deliberately not shaped like one. There is
 * no verbosity, no formatter, no section, no `writeln` vs `write` distinction:
 * modelling those would be reimplementing the console in a package whose whole
 * claim is that depending on it costs nothing — the same refusal that keeps an
 * InputDefinition out of {@see CommandDescriptor}. Verbosity is not lost by
 * leaving it out; it is HONOURED by leaving it out, because devkit's adapter
 * writes through the real output, which applies `--quiet` and `-v` itself.
 *
 * THE THREE ARE SEPARATE BECAUSE THE STREAMS ARE. What a command produces goes
 * to stdout, where a pipeline can read it; why a command refused goes to
 * stderr, where it survives that pipeline and does not corrupt it. A handler
 * that sent both to one place would make its own output unpipeable.
 */
interface CommandIo
{
    /**
     * One line of what the command produced, on standard output — the result,
     * the thing a caller piping this command would want to read. devkit's
     * adapter appends the newline and applies the console's verbosity, so a
     * line written here is suppressed under `--quiet` without the handler
     * knowing what `--quiet` is.
     */
    public function write(string $line): void;

    /**
     * One line of diagnostics on standard ERROR — why the command refused, what
     * it could not find, what it is about to do slowly. It goes to the other
     * stream so that a person still sees it when the command's output is being
     * piped somewhere, and so that it never corrupts what is in that pipe.
     */
    public function error(string $line): void;

    /**
     * One line from standard input, WITHOUT its trailing newline, or null at
     * end of input — so a passphrase can be piped in rather than written into a
     * shell history or a process list:
     *
     *     printf '%s' "$PASSPHRASE" | bin/console team:user:create ada@example.test Ada Mwangi
     *
     * Null means the stream ended with nothing more to read, which is a
     * different thing from '' — a line that was there and was empty. A handler
     * that requires input treats both as "nothing was given", but only null
     * tells it that asking again would be pointless.
     */
    public function readLine(): ?string;
}
