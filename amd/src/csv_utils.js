// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Shared helpers for building CSV exports safely.
 *
 * @module     local_datacurso_ratings/csv_utils
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Detects values that spreadsheet applications may interpret as a formula.
 *
 * A value matches when, after optional leading whitespace, it starts with
 * one of "=", "+", "-" or "@", or when it starts with a tab or carriage
 * return. Leading whitespace is tolerated because spreadsheet applications
 * trim it before evaluating the cell, so " =1+1" is still executed as a
 * formula.
 *
 * @type {RegExp}
 */
const FORMULA_PREFIX = /^(?:\s*[=+\-@]|[\t\r])/;

/**
 * Escape a single value for a CSV cell.
 *
 * Every value is converted to a string first, so numeric-looking values
 * (for example a negative number such as -5) are also prefixed with a quote.
 * That is an accepted tradeoff: the exported reports only contain counts and
 * percentages that are never negative, and losing a numeric type on an edge
 * case is preferable to letting a user-supplied comment start a formula.
 *
 * @param {*} cell Raw cell value.
 * @returns {string} The cell wrapped in double quotes, with inner quotes doubled.
 */
export const escapeCsvCell = (cell) => {
    let value = String(cell ?? '');
    if (FORMULA_PREFIX.test(value)) {
        value = `'${value}`;
    }
    return `"${value.replace(/"/g, '""')}"`;
};

/**
 * Build a CSV line from an array of raw cell values.
 *
 * @param {Array} row Raw cell values.
 * @returns {string}
 */
export const toCsvRow = (row) => row.map(escapeCsvCell).join(',');
