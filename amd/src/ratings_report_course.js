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
 * Displays and manages the AI-powered ratings report for a course.
 *
 * @module     local_datacurso_ratings/ratings_report_course
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';

/**
 * Initialize the ratings report for a specific course.
 *
 * @param {number} courseid
 */
export const init = (courseid) => {
    const container = document.querySelector('#ratings-report-container');
    if (!container) {
        return;
    }

    showLoading(container);

    Ajax.call([{
        methodname: 'local_datacurso_ratings_get_ratings_report_course',
        args: {courseid}
    }])[0]
        .then((data) => processReportData(data, courseid))
        .then((templateData) => Templates.render('local_datacurso_ratings/report_ratings_course', templateData))
        .then((html, js) => {
            container.innerHTML = html;
            Templates.runTemplateJS(js);
            initTableFeatures();
        })
        .catch((error) => {
            Notification.exception(error);
        });
};

/**
 * Process raw report data into a template-friendly format.
 *
 * @param {Array} data - Raw WS data.
 * @param {number} courseid - Course ID.
 * @returns {Object}
 */
function processReportData(data, courseid) {
    let totalLikes = 0;
    let totalDislikes = 0;
    let activitiesWithRatings = 0;

    const processedActivities = data.map((item) => {
        const likes = item.likes || 0;
        const dislikes = item.dislikes || 0;
        const totalRatings = likes + dislikes;

        totalLikes += likes;
        totalDislikes += dislikes;
        if (totalRatings > 0) {
            activitiesWithRatings++;
        }

        const porcentaje = item.approvalpercent || 0;
        const comentariosArray = Array.isArray(item.comments) ? item.comments : [];
        const comentarios = comentariosArray.join(' / ');

        return {
            curso: item.course || '',
            actividad: item.activity || '',
            modulo: item.modname || '',
            cmid: item.cmid || 0,
            url: item.url || '',
            likes,
            dislikes,
            porcentaje,
            comentarios,
            total_ratings: totalRatings,
            has_ratings: totalRatings > 0,
            has_comments: comentariosArray.length > 0,
            satisfaction_class: getSatisfactionClass(porcentaje),
            formatted_percentage: `${porcentaje.toFixed(1)}%`,
            activityurl: item.url || `${M.cfg.wwwroot}/mod/${item.modname}/view.php?id=${item.cmid}`
        };
    });

    const totalRatings = totalLikes + totalDislikes;
    const overallSatisfaction = totalRatings > 0 ? ((totalLikes / totalRatings) * 100) : 0;

    return {
        courseid,
        activities: processedActivities,
        has_data: processedActivities.length > 0,
        summary: {
            total_activities: processedActivities.length,
            activities_with_ratings: activitiesWithRatings,
            total_ratings: totalRatings,
            total_likes: totalLikes,
            total_dislikes: totalDislikes,
            overall_satisfaction: overallSatisfaction.toFixed(1),
            satisfaction_class: getSatisfactionClass(overallSatisfaction)
        }
    };
}

/**
 * Get Bootstrap color class based on satisfaction percentage.
 *
 * @param {number} percentage
 * @returns {string}
 */
function getSatisfactionClass(percentage) {
    if (percentage >= 80) {
        return 'success';
    }
    if (percentage >= 60) {
        return 'warning';
    }
    return 'danger';
}

/**
 * Display a loading spinner.
 *
 * @param {HTMLElement} container
 */
export async function showLoading(container) {
    const html = await Templates.render('local_datacurso_ratings/report_ratings_loading', {});
    container.innerHTML = html;
}


/**
 * Initialize interactive features in the rendered table.
 */
function initTableFeatures() {
    document.querySelectorAll('.expand-comments').forEach((button) => {
        button.addEventListener('click', (e) => {
            const targetSelector = e.currentTarget.getAttribute('data-target');
            const commentsDiv = document.querySelector(targetSelector);
            if (!commentsDiv) {
                return;
            }

            const isHidden = commentsDiv.style.display === 'none' || !commentsDiv.style.display;
            commentsDiv.style.display = isHidden ? 'block' : 'none';
            e.currentTarget.textContent = isHidden ? 'Ocultar comentarios' : 'Ver comentarios';
        });
    });

    initTableSorting();
}

/**
 * Initialize sorting on table headers.
 */
function initTableSorting() {
    const headers = document.querySelectorAll('th[data-sort]');
    headers.forEach((header) => {
        header.style.cursor = 'pointer';
        header.addEventListener('click', () => {
            const sortBy = header.getAttribute('data-sort');
            sortTable(sortBy, header);
        });
    });
}

/**
 * Basic table sorting handler.
 *
 * @param {string} column - The column key to sort by.
 * @param {HTMLElement} header - The clicked header element.
 */
function sortTable(column, header) {
    const table = header.closest('table');
    const tbody = table?.querySelector('tbody');
    if (!tbody) {
        return;
    }

    const rows = Array.from(tbody.querySelectorAll('tr'));
    const ascending = !header.classList.contains('sorted-asc');

    table.querySelectorAll('th[data-sort]').forEach((h) => h.classList.remove('sorted-asc', 'sorted-desc'));
    header.classList.add(ascending ? 'sorted-asc' : 'sorted-desc');

    rows.sort((a, b) => {
        const aValue = a.dataset[column] || '';
        const bValue = b.dataset[column] || '';
        return ascending
            ? aValue.localeCompare(bValue, undefined, {numeric: true})
            : bValue.localeCompare(aValue, undefined, {numeric: true});
    });

    rows.forEach((row) => tbody.appendChild(row));
}

export default {init};
