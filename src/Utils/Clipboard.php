<?php

declare(strict_types=1);

namespace Libui\Utils;

/**
 * Minimal cross-platform clipboard access.
 *
 * libui has no clipboard API, so this shells out to the platform's native tool.
 * On Linux it tries Wayland then X11 utilities in turn, so it works across
 * common setups (it's a no-op returning false if none are installed):
 *
 *   macOS    pbcopy / pbpaste
 *   Windows  clip / powershell Get-Clipboard
 *   Linux    wl-copy/wl-paste, then xclip, then xsel
 */
final class Clipboard
{
    /** Put $text on the system clipboard. Returns false if no tool is available. */
    public static function copy(string $text): bool
    {
        foreach (self::commands(write: true) as $cmd) {
            $handle = @popen($cmd . self::silence(), 'w');
            if ($handle === false) {
                continue;
            }
            @fwrite($handle, $text);
            if (pclose($handle) === 0) {
                return true;
            }
        }
        return false;
    }

    /** Read the clipboard's text contents, or null if unavailable. */
    public static function paste(): ?string
    {
        foreach (self::commands(write: false) as $cmd) {
            $handle = @popen($cmd . self::silence(), 'r');
            if ($handle === false) {
                continue;
            }
            $out = stream_get_contents($handle);
            if (pclose($handle) === 0 && is_string($out)) {
                return $out;
            }
        }
        return null;
    }

    /**
     * Candidate commands for the current OS, in priority order.
     *
     * @return list<string>
     */
    private static function commands(bool $write): array
    {
        return match (\PHP_OS_FAMILY) {
            'Darwin' => [$write ? 'pbcopy' : 'pbpaste'],
            'Windows' => [$write ? 'clip' : 'powershell -NoProfile -Command Get-Clipboard'],
            default => $write
                ? ['wl-copy', 'xclip -selection clipboard', 'xsel --clipboard --input']
                : ['wl-paste --no-newline', 'xclip -selection clipboard -o', 'xsel --clipboard --output'],
        };
    }

    /** Swallow "command not found" noise from fallback attempts. */
    private static function silence(): string
    {
        return \PHP_OS_FAMILY === 'Windows' ? ' 2>NUL' : ' 2>/dev/null';
    }
}
