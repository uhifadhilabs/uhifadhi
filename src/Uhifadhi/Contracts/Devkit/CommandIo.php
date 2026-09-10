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
 * same trade the descriptor itself makes: these verbs are the lowest common
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
 * READING IS TWO VERBS RATHER THAN ONE FLAG, and that is the one place this
 * models something a stream alone does not: whether what is typed appears on
 * the screen. A passphrase read like an ordinary line is a passphrase left
 * standing in a terminal's scrollback and in whatever records it, which is the
 * same leak as writing it down. Asking for it is therefore its own verb, so
 * that a handler cannot ask for a secret and be given an echoed one by
 * accident.
 *
 * THE OUTPUT VERBS ARE SEPARATE BECAUSE THE STREAMS ARE. What a command produces goes
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

    /**
     * One line the person typed that is NEVER PUT ON THE SCREEN, without its
     * trailing newline, or null at end of input — the passphrase somebody is
     * asked for at a prompt rather than passing on the command line, where it
     * would be read back out of a shell history or a process list.
     *
     * It answers exactly as {@see readLine()} does: the line, or null once the
     * stream is closed and asking again would be pointless. '' is a line that
     * was there and was empty, and a handler that requires a secret refuses
     * both — but only null says the asking is over.
     *
     * WHAT AN IMPLEMENTATION OWES IS THE SILENCE, and the terminal is the only
     * place it can be had: switching the echo off is a property of a terminal,
     * not of a stream. So the promise is that where there is a terminal the
     * typing does not appear on it. Where there is none — a pipe, a log, a test
     * — there is no echo to suppress and nothing is leaked by reading the line
     * plainly, which is what keeps this the same verb for a passphrase piped in
     * and a passphrase typed at a prompt. An implementation that meets a
     * terminal it cannot silence says so on the error stream rather than
     * refusing, because a first administrator who cannot be created is worse
     * than one created in view of the person creating it.
     */
    public function readSecret(): ?string;
}
