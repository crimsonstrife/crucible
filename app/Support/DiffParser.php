<?php

namespace App\Support;

/**
 * Parses a raw unified diff (from `git diff`) into a structured array
 * of per-file sections with hunk / line-number information.
 *
 * Output shape for each file entry:
 * [
 *   'header'      => string,   // "diff --git a/… b/…"
 *   'from_path'   => string,   // "a/src/Foo.php" or "/dev/null"
 *   'to_path'     => string,   // "b/src/Foo.php" or "/dev/null"
 *   'file_name'   => string,   // "src/Foo.php"  (display name)
 *   'is_new'      => bool,
 *   'is_deleted'  => bool,
 *   'is_binary'   => bool,
 *   'is_renamed'  => bool,
 *   'additions'   => int,
 *   'deletions'   => int,
 *   'hunks'       => [
 *     [
 *       'header'     => string,  // "@@ -10,7 +10,9 @@ …"
 *       'lines'      => [
 *         ['type' => 'context'|'add'|'del'|'meta', 'old' => int|null, 'new' => int|null, 'content' => string],
 *         …
 *       ],
 *     ],
 *     …
 *   ],
 * ]
 */
class DiffParser
{
    /**
     * MIME categories for visual diff rendering.
     * Set on each file entry as 'diff_category'.
     */
    /**
     * Parse a raw unified-format diff string.
     *
     * @return array<int, array>
     */
    public static function parse(string $rawDiff): array
    {
        if (trim($rawDiff) === '') {
            return [];
        }

        $files = [];
        // Split into per-file chunks at each "diff --git" boundary.
        $chunks = preg_split('/(?=^diff --git )/m', $rawDiff, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($chunks as $chunk) {
            $file = self::parseFileChunk($chunk);
            if ($file !== null) {
                $files[] = $file;
            }
        }

        return $files;
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private static function parseFileChunk(string $chunk): ?array
    {
        $lines    = explode("\n", $chunk);
        $lineCount = count($lines);
        $i        = 0;

        // First line must be "diff --git …"
        $headerLine = $lines[$i++] ?? '';
        if (! str_starts_with($headerLine, 'diff --git ')) {
            return null;
        }

        $file = [
            'header'     => $headerLine,
            'from_path'  => '',
            'to_path'    => '',
            'file_name'  => '',
            'is_new'     => false,
            'is_deleted' => false,
            'is_binary'  => false,
            'is_renamed' => false,
            'additions'  => 0,
            'deletions'  => 0,
            'hunks'      => [],
        ];

        // Parse extended headers (index, new/deleted file mode, rename, binary, ---, +++)
        while ($i < $lineCount) {
            $line = $lines[$i];

            if (str_starts_with($line, 'new file mode'))          { $file['is_new'] = true; $i++; continue; }
            if (str_starts_with($line, 'deleted file mode'))      { $file['is_deleted'] = true; $i++; continue; }
            if (str_starts_with($line, 'rename from') || str_starts_with($line, 'rename to')) { $file['is_renamed'] = true; $i++; continue; }
            if (str_starts_with($line, 'index '))                 { $i++; continue; }
            if (str_starts_with($line, 'old mode') || str_starts_with($line, 'new mode')) { $i++; continue; }
            if (str_starts_with($line, 'similarity index'))       { $i++; continue; }
            if (str_starts_with($line, 'Binary files'))           { $file['is_binary'] = true; $i++; continue; }

            if (str_starts_with($line, '--- ')) {
                $file['from_path'] = substr($line, 4);
                $i++;
                continue;
            }
            if (str_starts_with($line, '+++ ')) {
                $file['to_path'] = substr($line, 4);
                $i++;
                break; // hunks follow
            }

            // Anything else (e.g. blank line before hunk) — if it starts with '@@', break to hunk parsing
            if (str_starts_with($line, '@@')) {
                break;
            }

            $i++;
        }

        // Derive display file_name and diff category for visual rendering
        $file['file_name'] = self::displayName($file['from_path'], $file['to_path'], $headerLine);
        $file['diff_category'] = MimeDetector::diffCategory($file['file_name']);
        $file['mime_type'] = MimeDetector::mimeFromExtension($file['file_name']);

        // Parse hunks
        $currentHunk = null;
        $oldLine     = 0;
        $newLine     = 0;

        while ($i < $lineCount) {
            $line = $lines[$i];

            if (str_starts_with($line, '@@')) {
                if ($currentHunk !== null) {
                    $file['hunks'][] = $currentHunk;
                }
                [$oldLine, $newLine] = self::parseHunkHeader($line);
                $currentHunk = ['header' => $line, 'lines' => []];
                $i++;
                continue;
            }

            if ($currentHunk === null) {
                $i++;
                continue;
            }

            if (str_starts_with($line, '+') && ! str_starts_with($line, '+++ ')) {
                $currentHunk['lines'][] = ['type' => 'add', 'old' => null, 'new' => $newLine++, 'content' => substr($line, 1)];
                $file['additions']++;
            } elseif (str_starts_with($line, '-') && ! str_starts_with($line, '--- ')) {
                $currentHunk['lines'][] = ['type' => 'del', 'old' => $oldLine++, 'new' => null, 'content' => substr($line, 1)];
                $file['deletions']++;
            } elseif (str_starts_with($line, '\\')) {
                // "\ No newline at end of file" — attach as meta to last real line
                $currentHunk['lines'][] = ['type' => 'meta', 'old' => null, 'new' => null, 'content' => $line];
            } else {
                // Context line (space prefix) or bare line
                $content = (strlen($line) > 0 && $line[0] === ' ') ? substr($line, 1) : $line;
                $currentHunk['lines'][] = ['type' => 'context', 'old' => $oldLine++, 'new' => $newLine++, 'content' => $content];
            }

            $i++;
        }

        if ($currentHunk !== null) {
            $file['hunks'][] = $currentHunk;
        }

        return $file;
    }

    /**
     * Extract old and new starting line numbers from a @@ header.
     *
     * "@@ -10,7 +10,9 @@" → [10, 10]
     * "@@ -0,0 +1 @@"     → [0, 1]  (count omitted means 1)
     */
    private static function parseHunkHeader(string $header): array
    {
        if (preg_match('/@@ -(\d+)(?:,\d+)? \+(\d+)(?:,\d+)? @@/', $header, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }

        return [1, 1];
    }

    /**
     * Derive a human-readable display name for the file from the --- / +++ paths.
     *
     * Git prefixes paths with "a/" and "b/"; /dev/null means new or deleted.
     */
    private static function displayName(string $fromPath, string $toPath, string $diffHeader): string
    {
        $path = $toPath !== '/dev/null' ? $toPath : $fromPath;

        // Strip git's "a/" / "b/" prefix
        if (str_starts_with($path, 'a/') || str_starts_with($path, 'b/')) {
            return substr($path, 2);
        }

        // Fall back: extract from "diff --git a/foo b/foo"
        if (preg_match('/^diff --git a\/(.+?) b\/.+$/', $diffHeader, $m)) {
            return $m[1];
        }

        return $path;
    }
}
