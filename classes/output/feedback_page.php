<?php
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
 * Class feedback_page.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_datacurso_ratings\output;

use renderable;
use templatable;
use renderer_base;
use stdClass;

/**
 * Feedback page class for rendering ratings feedback.
 */
class feedback_page implements renderable, templatable {
    /** @var array Feedback items. */
    private $items;

    /** @var string Type of feedback (like/dislike). */
    private $type;

    /**
     * Constructor.
     *
     * @param string $type Type of feedback to display.
     * @param int $tenantid Tenant ID for multi-tenancy support.
     */
    public function __construct(string $type, int $tenantid) {
        global $DB;
        $this->type = $type;

        // Fetch feedback items from the database.
        $this->items = $DB->get_records(
            'local_datacurso_ratings_feedback',
            ['type' => $this->type, 'tenant_id' => $tenantid],
            'id DESC'
        );
    }

    /**
     * Export data for mustache template.
     *
     * @param renderer_base $output The renderer instance used for generating output
     * @return array Data to render template.
     */
    public function export_for_template(renderer_base $output) {
        $items = [];
        foreach ($this->items as $rec) {
            $items[] = [
                'id' => $rec->id,
                'feedbacktext' => format_text($rec->feedbacktext, FORMAT_PLAIN),
            ];
        }

        return [
            'items' => $items,
            'type' => $this->type,
            'sesskey' => sesskey(),
        ];
    }
}
