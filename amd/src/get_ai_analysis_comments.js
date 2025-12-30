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
 * AI Analysis Comments Module.
 *
 * @module     local_datacurso_ratings/get_ai_analysis_comments
 * @copyright  2025 Industria Elearning <info@industriaelearning.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Templates from 'core/templates';

export const init = () => {
    document.addEventListener('click', async function (e) {
        if (e.target.classList.contains('btn-generate-ai-activitie') || e.target.closest('.btn-generate-ai-activitie')) {
            e.preventDefault();

            const button = e.target.closest('.btn-generate-ai-activitie');
            const cmid = button.getAttribute('data-cmid');
            const container = button.closest('.mb-3');
            const resultContainer = container.querySelector('.ai-analysis-result');

            if (!cmid || !resultContainer) {
                return;
            }

            const responseAi = {
                loading: true,
                success: false,
                message: '',
            };

            // Loading state
            button.disabled = true;
            const htmlResponse = await Templates.render('local_datacurso_ratings/ai_analysis_response', responseAi);
            resultContainer.innerHTML = htmlResponse;

            Ajax.call([{
                methodname: 'local_datacurso_ratings_get_ai_analysis_comments',
                args: {cmid: parseInt(cmid, 10)}
            }])[0]
                .then(async(data) => {
                    responseAi.loading = false;
                    responseAi.success = true;
                    responseAi.message = data.ai_analysis_comment;
                    const htmlResponse = await Templates.render('local_datacurso_ratings/ai_analysis_response', responseAi);
                    resultContainer.innerHTML = htmlResponse;
                    button.disabled = false;
                })
                .catch(async(e) => {
                    responseAi.loading = false;
                    responseAi.success = false;
                    responseAi.message = e.message;
                    const htmlResponse = await Templates.render('local_datacurso_ratings/ai_analysis_response', responseAi);
                    resultContainer.innerHTML = htmlResponse;
                    button.disabled = false;
                });
        }
    });
};

export default {init};
