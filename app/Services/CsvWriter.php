<?php

namespace App\Services;

/** Writes CSV rows that are safe to open in Excel / Google Sheets. */
class CsvWriter
{
    /**
     * Write one row, neutralising cells a spreadsheet would run as a formula.
     *
     * @param  resource  $handle
     * @param  array<int, mixed>  $cells
     */
    public static function writeRow($handle, array $cells): void
    {
        fputcsv($handle, array_map(self::escapeFormula(...), $cells));
    }

    /** Prefix text starting with = + - @ (or a tab/CR) with an apostrophe so it is shown, not evaluated. */
    public static function escapeFormula(mixed $cell): mixed
    {
        if (is_string($cell) && $cell !== '' && in_array($cell[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$cell;
        }

        return $cell;
    }
}
