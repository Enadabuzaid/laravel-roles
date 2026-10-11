<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Support\Csv;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** Streams rows as a UTF-8 CSV with a BOM (Excel and Arabic text) whose cells cannot be read as formulas. */
final class CsvStream
{
    private const FLUSH_EVERY = 500;

    /**
     * @param  list<string>  $headers
     * @param  iterable<list<scalar|null>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?? 'export.csv';

        return new StreamedResponse(static function () use ($headers, $rows): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map(static fn (string $h): string => self::cell($h), $headers), ',', '"', '', "\r\n");

            $count = 0;
            foreach ($rows as $row) {
                fputcsv($out, array_map(static fn (mixed $v): string => self::cell(is_scalar($v) ? $v : null), $row), ',', '"', '', "\r\n");

                if (++$count % self::FLUSH_EVERY === 0) {
                    flush();
                }
            }

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$safe.'"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Null is empty, booleans are 1/0 and a leading `= + - @ TAB CR` is neutralized with a single quote. */
    public static function cell(string|int|float|bool|null $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $text = (string) $value;

        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$text;
        }

        return $text;
    }
}
