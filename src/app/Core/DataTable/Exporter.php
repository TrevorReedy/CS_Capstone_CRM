<?php
namespace App\Core\DataTable;

use DOMDocument;

/**
 * Dependency-free exporters for list data.
 *
 *  - CSV  : PHP's built-in fputcsv.
 *  - XML  : PHP's built-in DOMDocument.
 *  - PDF  : NO library. We emit a print-formatted HTML page that calls
 *           window.print() on load; the browser's native "Save as PDF" does the
 *           formatting. (This is the "generate HTML, let the browser format the
 *           PDF" approach.)
 *
 * Each method sends its own HTTP headers and echoes the payload; callers should
 * treat them as terminal (exit after). Rows are plain assoc arrays of *scalar*
 * display values (no HTML) keyed to match $columns.
 *
 * @param array<string,string> $columns  Ordered map of row-key => header label.
 * @param list<array<string,mixed>> $rows
 */
final class Exporter
{
    public static function csv(array $columns, array $rows, string $filename): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . self::safeName($filename) . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, array_values($columns));
        foreach ($rows as $row) {
            fputcsv($out, array_map(
                static fn($key) => self::safeCell($row[$key] ?? ''),
                array_keys($columns)
            ));
        }
        fclose($out);
    }

    /**
     * Neutralize spreadsheet formula injection.
     *
     * fputcsv quotes correctly for CSV, but quoting is not the threat here:
     * Excel and Sheets evaluate any cell whose text begins with =, +, - or @.
     * A customer named `=HYPERLINK("http://evil","Click")` — or the classic
     * `=cmd|'/c calc'!A0` — becomes live content the moment a colleague opens
     * the export. A leading apostrophe forces the cell to be read as text; the
     * spreadsheet does not display it.
     */
    private static function safeCell(mixed $value): string
    {
        $cell = (string)$value;

        // Tab and CR are included because they are stripped before evaluation,
        // so they can be used to hide the trigger character.
        if ($cell !== '' && str_contains("=+-@\t\r", $cell[0])) {
            return "'" . $cell;
        }

        return $cell;
    }

    public static function xml(array $columns, array $rows, string $filename, string $root = 'rows', string $item = 'row'): void
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $rootEl = $doc->createElement($root);
        $doc->appendChild($rootEl);

        foreach ($rows as $row) {
            $itemEl = $doc->createElement($item);
            foreach (array_keys($columns) as $key) {
                $cell = $doc->createElement($key);
                $cell->appendChild($doc->createTextNode((string)($row[$key] ?? '')));
                $itemEl->appendChild($cell);
            }
            $rootEl->appendChild($itemEl);
        }

        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . self::safeName($filename) . '.xml"');
        echo $doc->saveXML();
    }

    /**
     * Print-to-PDF: a self-contained HTML page that auto-opens the browser print
     * dialog. The user chooses "Save as PDF". No server-side PDF engine involved.
     */
    public static function printableHtml(array $columns, array $rows, string $title, ?string $downloadName = null): void
    {
        $h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

        header('Content-Type: text/html; charset=utf-8');

        // The browser derives the "Save as PDF" filename from document.title, so we
        // set it to the dated download name while the on-page <h1> stays readable.
        $docTitle = self::safeName($downloadName ?? $title);

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
        echo '<title>' . $h($docTitle) . '</title><style>'
            . 'body{font:13px/1.4 Arial,Helvetica,sans-serif;color:#111;margin:24px}'
            . 'h1{font-size:18px;margin:0 0 4px}.meta{color:#666;font-size:11px;margin:0 0 16px}'
            . 'table{border-collapse:collapse;width:100%}'
            . 'th,td{border:1px solid #999;padding:5px 8px;text-align:left;vertical-align:top}'
            . 'th{background:#eee;font-weight:bold}tr:nth-child(even) td{background:#f7f7f7}'
            . '@media print{body{margin:0}.noprint{display:none}}'
            . '</style></head><body onload="window.print()">';

        echo '<h1>' . $h($title) . '</h1>';
        echo '<p class="meta">' . count($rows) . ' row(s) · generated ' . $h(date('Y-m-d H:i')) . '</p>';

        echo '<table><thead><tr>';
        foreach ($columns as $label) {
            echo '<th>' . $h($label) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach (array_keys($columns) as $key) {
                echo '<td>' . $h($row[$key] ?? '') . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></body></html>';
    }

    /** Dispatch on ?format= — the shared tail of every *_export.php endpoint. */
    public static function stream(string $format, array $columns, array $rows, string $filename, string $title): void
    {
        // Download filenames are prefixed with the export date: e.g. 2026-07-17-campaigns.csv
        $filename = date('Y-m-d') . '-' . $filename;

        switch ($format) {
            case 'xml':
                self::xml($columns, $rows, $filename);
                break;
            case 'pdf':
                self::printableHtml($columns, $rows, $title, $filename);
                break;
            case 'csv':
            default:
                self::csv($columns, $rows, $filename);
                break;
        }
    }

    private static function safeName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $name) ?? 'export';
        return trim($name, '_') ?: 'export';
    }
}
