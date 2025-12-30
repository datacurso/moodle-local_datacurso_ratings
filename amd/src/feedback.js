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
 * Feedback module logic.
 *
 * @module     local_datacurso_ratings/feedback
 * @copyright  2025 Industria Elearning <info@industriaelearning.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Templates from 'core/templates';

/**
 * Send new feedback via AJAX.
 *
 * @param {string} feedbacktext Feedback text.
 * @param {string} type Feedback type (like/dislike).
 * @returns {Promise<object>} Server response.
 */
function addFeedback(feedbacktext, type) {
    const requests = Ajax.call([{
        methodname: 'local_datacurso_ratings_add_feedback',
        args: {feedbacktext, type}
    }]);

    return requests[0];
}

/**
 * Initialize feedback management.
 */
export function init() {
    const container = document.querySelector('.feedback-admin');
    if (!container) {
        return;
    }

    const form = container.querySelector('form');
    const list = container.querySelector('.feedback-list');
    const type = container.dataset ? container.dataset.type : null;

    if (!form || !list || !type) {
        return;
    }

    // Add new feedback.
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const input = form.querySelector("input[name='feedbacktext']");
        if (!input.value.trim()) {
            return;
        }

        addFeedback(input.value.trim(), type)
            .then((resp) => {
                Notification.addNotification({
                    message: resp.message,
                    type: 'success'
                });
                const item = {
                    id: resp.id,
                    feedbacktext: input.value,
                };
                Templates.renderForPromise('local_datacurso_ratings/feedback_item', item)
                    .then(({html, js}) => {
                        Templates.runTemplateJS(js);
                        const temp = document.createElement('div');
                        temp.innerHTML = html.trim();
                        const newItem = temp.firstChild;
                        list.prepend(newItem);
                    })
                    .catch((err) => Notification.exception(err));
                input.value = '';
    })
    .catch((err) => Notification.exception(err));

    });

    // Delete feedback.
    list.addEventListener('click', (e) => {
        if (e.target.classList.contains('btn-delete')) {
            const id = e.target.dataset.id;

            const requests = Ajax.call([{
                methodname: 'local_datacurso_ratings_delete_feedback',
                args: {id}
            }]);

            requests[0]
                .then((resp) => {
                    Notification.addNotification({
                        message: resp.message,
                        type: 'success'
                    });
                    e.target.closest('li').remove();
                })
                .catch((err) => Notification.exception(err));
        }
    });
}
