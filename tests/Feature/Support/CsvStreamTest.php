<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Support\Csv\CsvStream;
use Symfony\Component\HttpFoundation\StreamedResponse;

function csvBody(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

it('starts with a UTF-8 BOM and the download headers', function (): void {
    $response = CsvStream::download('audit-all-20261010-1200.csv', ['a', 'b'], [['1', '2']]);

    expect(csvBody($response))->toStartWith("\xEF\xBB\xBF")
        ->and($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8')
        ->and($response->headers->get('Content-Disposition'))->toBe('attachment; filename="audit-all-20261010-1200.csv"');
});

it('quotes commas, quotes and newlines per RFC 4180', function (): void {
    $body = csvBody(CsvStream::download('x.csv', ['h'], [['a,b'], ['say "hi"'], ["line1\nline2"], ['plain']]));

    expect($body)->toContain('"a,b"')->toContain('"say ""hi"""')->toContain("\"line1\nline2\"")->toContain("\r\nplain\r\n");
});

it('neutralizes formula injection prefixes', function (string $input, string $expected): void {
    expect(CsvStream::cell($input))->toBe($expected);
})->with([
    ['=1+1', "'=1+1"], ['-2', "'-2"], ['+x', "'+x"], ['@SUM(A1)', "'@SUM(A1)"], ["\tcmd", "'\tcmd"], ["\rcmd", "'\rcmd"],
    ['=HYPERLINK("http://x","click")', "'=HYPERLINK(\"http://x\",\"click\")"], ['safe', 'safe'], ['a=b', 'a=b'], ['', ''],
]);

it('maps null and booleans', function (): void {
    expect(CsvStream::cell(null))->toBe('')->and(CsvStream::cell(true))->toBe('1')->and(CsvStream::cell(false))->toBe('0')->and(CsvStream::cell(12))->toBe('12');
});

it('writes injection-safe cells and keeps Arabic text', function (): void {
    $body = csvBody(CsvStream::download('x.csv', ['name'], [['=cmd|calc'], ['مدير النظام']]));

    expect($body)->toContain("'=cmd|calc")->toContain('مدير النظام');
});

it('streams a generator without materializing it', function (): void {
    $produced = 0;
    $rows = (function () use (&$produced): Generator {
        for ($i = 0; $i < 10000; $i++) {
            $produced++;
            yield [$i, 'row '.$i];
        }
    })();

    $body = csvBody(CsvStream::download('x.csv', ['n', 'label'], $rows));

    expect($produced)->toBe(10000)->and(substr_count($body, "\r\n"))->toBe(10001);
});
