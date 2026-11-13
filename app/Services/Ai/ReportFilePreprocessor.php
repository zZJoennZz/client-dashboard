<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Turns whatever was uploaded into Gemini "parts" (text or inline binary),
 * and strips sensitive content BEFORE anything leaves your server.
 *
 * ASSUMPTION: each file model has: disk, path, original_name. Adjust to your schema.
 */
class ReportFilePreprocessor
{
    private const MAX_INLINE_BYTES = 15 * 1024 * 1024; // keep request under Gemini's inline limit
    private const MAX_TEXT_CHARS   = 150000;
    private const MAX_ROWS         = 300;               // per sheet

    private const SENSITIVE_SHEET = '/account|password|login|credential|secret/i';
    private const SENSITIVE_ROW   = '/password|passcode|passwd|api[_ -]?key|secret/i';

    private const MIME = [
        'pdf' => 'application/pdf', 'png' => 'image/png',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp',
    ];

    /** @return array{parts: array, manifest: array, hash: string} */
    public function process(iterable $files): array
    {
        $parts = []; $manifest = []; $hashes = []; $inline = 0;

        foreach ($files as $file) {
            $name = $file->original_name;
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            try {
                $bytes = Storage::disk($file->disk)->get($file->path);
            } catch (\Throwable) {
                $manifest[] = ['name' => $name, 'status' => 'skipped', 'note' => 'Could not be read from storage'];
                continue;
            }
            $hashes[] = sha1($bytes);

            if (isset(self::MIME[$ext])) {
                if ($inline + strlen($bytes) > self::MAX_INLINE_BYTES) {
                    $manifest[] = ['name' => $name, 'status' => 'skipped', 'note' => 'Too large to send'];
                    continue;
                }
                $inline += strlen($bytes);
                $parts[] = ['text' => "FILE: {$name}"];
                $parts[] = ['inlineData' => ['mimeType' => self::MIME[$ext], 'data' => base64_encode($bytes)]];
                $manifest[] = ['name' => $name, 'status' => 'read', 'note' => null];
            } elseif (in_array($ext, ['txt', 'md', 'csv'], true)) {
                $text = mb_substr(mb_scrub($bytes), 0, self::MAX_TEXT_CHARS);
                $parts[] = ['text' => "FILE: {$name}\n{$text}"];
                $manifest[] = ['name' => $name, 'status' => 'read', 'note' => null];
            } elseif (in_array($ext, ['xlsx', 'xls'], true)) {
                [$text, $status, $note] = $this->spreadsheetToText($bytes, $ext);
                if ($text === null) {
                    $manifest[] = ['name' => $name, 'status' => 'skipped', 'note' => $note];
                    continue;
                }
                $parts[] = ['text' => "FILE: {$name}\n{$text}"];
                $manifest[] = ['name' => $name, 'status' => $status, 'note' => $note];
            } else {
                $manifest[] = ['name' => $name, 'status' => 'skipped', 'note' => "Unsupported type .{$ext}"];
            }
        }

        sort($hashes);

        return ['parts' => $parts, 'manifest' => $manifest, 'hash' => hash('sha256', implode('', $hashes))];
    }

    /** @return array{0: ?string, 1: string, 2: ?string} [text, status, note] */
    private function spreadsheetToText(string $bytes, string $ext): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'rpt') . '.' . $ext;
        file_put_contents($tmp, $bytes);

        try {
            $book = IOFactory::load($tmp);
        } catch (\Throwable $e) {
            return [null, 'skipped', 'Spreadsheet could not be opened'];
        } finally {
            @unlink($tmp);
        }

        $out = ''; $notes = []; $status = 'read';

        foreach ($book->getWorksheetIterator() as $sheet) {
            $title = $sheet->getTitle();

            if (preg_match(self::SENSITIVE_SHEET, $title)) {
                $notes[] = "Sheet \"{$title}\" skipped (sensitive)";
                $status = 'partial';
                continue;
            }
            if (count($sheet->getDrawingCollection()) > 0) {
                $notes[] = "Sheet \"{$title}\" has images/charts that were not read";
                $status = 'partial';
            }

            // calculate formulas, apply number formats (so date serials become dates)
            $rows = $sheet->toArray(null, true, true, false);
            $lines = [];
            foreach ($rows as $row) {
                $line = implode("\t", array_map(fn ($c) => trim((string) $c), $row));
                if (trim($line) === '') continue;
                if (preg_match(self::SENSITIVE_ROW, $line)) continue; // safety net only
                $lines[] = $line;
                if (count($lines) >= self::MAX_ROWS) {
                    $notes[] = "Sheet \"{$title}\" truncated to " . self::MAX_ROWS . ' rows';
                    $status = 'partial';
                    break;
                }
            }
            $out .= "## Sheet: {$title}\n" . ($lines ? implode("\n", $lines) : '(no readable cells)') . "\n\n";
        }

        return [mb_substr($out, 0, self::MAX_TEXT_CHARS), $status, $notes ? implode('; ', $notes) : null];
    }
}