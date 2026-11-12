<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Version details for the Datacurso Ratings plugin.
 *
 * @package     local_datacurso_ratings
 * @copyright   2025 Josue <josue@datacurso.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_datacurso_ratings';
$plugin->version   = 2026111200;
$plugin->requires = 2025041400;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '2.0.0';
$plugin->supported = [500, 503];
$plugin->dependencies = [
    'aiprovider_datacurso' => 2026110900,
];
