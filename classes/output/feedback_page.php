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
     * @param int|null $tenantid Tenant whose phrases are shown; defaults to the tenant of the current user.
     */
    public function __construct(string $type, ?int $tenantid = null) {
        global $DB;
        $this->type = $type;
        $tenantid = $tenantid ?? \local_datacurso_ratings\local\tenancy::get_tenant_id();

        // Fetch feedback items from the database: those of the tenant and the shared ones (tenant 0),
        // which is where the phrases of a site that ran the plugin without tenancy remain.
        $this->items = $DB->get_records_select(
            'local_datacurso_ratings_feedback',
            'type = :type AND tenant_id IN (:tenantid, :notenant)',
            [
                'type' => $this->type,
                'tenantid' => $tenantid,
                'notenant' => \local_datacurso_ratings\local\tenancy::NO_TENANT,
            ],
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
            // Export the raw phrase: Mustache escapes it on output, and the widget posts
            // the value back verbatim so it must match the stored phrase exactly.
            $items[] = [
                'id' => $rec->id,
                'feedbacktext' => $rec->feedbacktext,
            ];
        }

        return [
            'items' => $items,
            'type' => $this->type,
        ];
    }
}
