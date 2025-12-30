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
 * Handles rating and feedback interactions for course modules.
 *
 * @module     local_datacurso_ratings/rate
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import { get_string as getString } from 'core/str';
import Notification from 'core/notification';
import Templates from 'core/templates';

/**
 * Initialise rating widget for the given cmid.
 *
 * @param {number} cmid - Course module ID.
 */
export const init = (cmid) => {
    const container = document.querySelector(`.local-dcr-rate[data-cmid="${cmid}"]`);
    if (!container) {
        return;
    }

    const fbBlock = container.querySelector('.local-dcr-feedback');
    const fbInput = container.querySelector('#local-dcr-feedback-input');
    const fbTextareaWrap = container.querySelector('#local-dcr-feedback-textarea');
    const sendBtn = container.querySelector('[data-action="send"]');
    const cancelBtn = container.querySelector('[data-action="cancel"]');
    const likeOptions = container.querySelector('.like-options');
    const dislikeOptions = container.querySelector('.dislike-options');
    const msgResponse = container.querySelector('.message-response-rate');

    /**
     * Show element.
     * @param {HTMLElement} el
     */
    const show = (el) => {
        if (el) {
            el.style.display = 'block';
            el.setAttribute('aria-hidden', 'false');
        }
    };

    /**
     * Hide element.
     * @param {HTMLElement} el
     */
    const hide = (el) => {
        if (el) {
            el.style.display = 'none';
            el.setAttribute('aria-hidden', 'true');
        }
    };

    /**
     * Display a feedback message inside the message-response-rate div.
     * @param {string} message The message text.
     * @param {string} [type='success'] Message type: success|error.
     */
    const showMessage = async (message, type) => {
        if (!msgResponse) {
            return;
        }
        const htmlMessage = await Templates.render('local_datacurso_ratings/rate_message_response', { message, type });
        msgResponse.innerHTML = htmlMessage;
    };

    // Handle like/dislike button click.
    container.querySelectorAll('[data-action="rate"]').forEach((btn) => {
        btn.addEventListener('click', () => {
            container.querySelectorAll('input[name="feedback_choice"]').forEach((r) => {
                r.checked = false;
            });
            hide(fbTextareaWrap);
            const rating = parseInt(btn.dataset.rating, 10);

            if (rating === 0) {
                show(fbBlock);
                show(dislikeOptions);
                hide(likeOptions);
                sendBtn?.setAttribute('data-rating', '0');
            } else {
                show(fbBlock);
                show(likeOptions);
                hide(dislikeOptions);
                sendBtn?.setAttribute('data-rating', '1');
            }
        });
    });

    // Show textarea if “Other” is selected.
    container.querySelectorAll('input[name="feedback_choice"]').forEach((radio) => {
        radio.addEventListener('change', () => {
            if (radio.value === 'other' && radio.checked) {
                show(fbTextareaWrap);
                fbInput?.focus();
            } else if (radio.checked) {
                hide(fbTextareaWrap);
            }
        });
    });

    // Cancel feedback.
    cancelBtn?.addEventListener('click', () => {
        hide(fbBlock);
        hide(fbTextareaWrap);
        if (fbInput) {
            fbInput.value = '';
            fbInput.disabled = false;
        }

        container.querySelectorAll('input[name="feedback_choice"]').forEach((r) => {
            r.checked = false;
            r.disabled = false;
        });

        sendBtn?.removeAttribute('data-rating');
        sendBtn.disabled = false;
        cancelBtn.disabled = false;
        msgResponse.innerHTML = '';
    });

    // Send feedback.
    sendBtn?.addEventListener('click', () => {
        const rating = parseInt(sendBtn.getAttribute('data-rating') || '0', 10);
        const selected = container.querySelector('input[name="feedback_choice"]:checked');

        let feedback = '';
        if (selected) {
            feedback = selected.value === 'other' ? (fbInput?.value || '').trim() : selected.value;
        }

        sendRating(cmid, rating, feedback);
    });

    /**
     * Send rating to server via Ajax.
     *
     * @param {number} cmid
     * @param {number} rating
     * @param {string} feedback
     */
    const sendRating = (cmid, rating, feedback) => {
        const requests = Ajax.call([{
            methodname: 'local_datacurso_ratings_save_rating',
            args: { cmid, rating, feedback }
        }]);

        requests[0]
            .then(async () => {
                const saved = await getString('ratingsaved', 'local_datacurso_ratings');
                showMessage(saved, 'success');

                // Disable inputs after sending.
                container.querySelectorAll('[data-action="rate"]').forEach((btn) => {
                    btn.disabled = true;
                });
                container.querySelectorAll('input[name="feedback_choice"]').forEach((r) => {
                    r.disabled = true;
                });

                if (sendBtn) {
                    sendBtn.disabled = true;
                }
                if (cancelBtn) {
                    cancelBtn.disabled = true;
                }
                if (fbInput) {
                    fbInput.disabled = true;
                }

                hide(fbBlock);
                hide(fbTextareaWrap);
            })
            .catch((e) => (Notification.exception(e)));
    };
};

export default { init };
