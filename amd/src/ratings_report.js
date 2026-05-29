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
 * General ratings report JS.
 *
 * @module     local_datacurso_ratings/ratings_report
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';

const ALLOWED_PER_PAGE = [5, 10, 25, 50, 100];

let state = {
    page: 0,
    perpage: 5,
    searchactivity: '',
    searchcourse: '',
    categoryid: 0,
    datefrom: '',
    dateto: '',
};

/** @type {Array} */
let categories = [];

/** @type {Array} */
let cachedCourses = [];

/**
 * Initialize the general ratings report.
 */
export const init = async() => {
    const container = document.querySelector('[data-region="general-ratings-report-container"]');
    if (!container) {
        return;
    }

    try {
        categories = JSON.parse(container.dataset.categories || '[]');
    } catch (error) {
        Notification.alert('Error', `Error parsing categories data: ${error.message}`, 'OK');
        return;
    }

    await fetchAndRender();
};

/**
 * Display a loading spinner.
 *
 * @param {HTMLElement} container
 */
const showLoading = async(container) => {
    const {html, js} = await Templates.renderForPromise('local_datacurso_ratings/report_ratings_loading', {});
    Templates.replaceNodeContents(container, html, js);
};

/**
 * Fetch report data from webservice.
 *
 * @returns {Promise<Object>}
 */
const fetchReportData = async() => {
    const request = Ajax.call([{
        methodname: 'local_datacurso_ratings_get_ratings_report',
        args: {
            page: state.page,
            perpage: state.perpage,
            searchactivity: state.searchactivity,
            searchcourse: state.searchcourse,
            categoryid: state.categoryid,
            datefrom: state.datefrom,
            dateto: state.dateto,
        },
    }]);

    return request[0];
};

/**
 * Render report and bind interactive controls.
 */
const fetchAndRender = async() => {
    const container = document.querySelector('[data-region="general-ratings-report-container"]');
    if (!container) {
        return;
    }

    await showLoading(container);

    try {
        const data = await fetchReportData();
        state.page = Number(data?.pagination?.page || 0);
        state.perpage = Number(data?.pagination?.perpage || state.perpage);
        cachedCourses = Array.isArray(data?.courses) ? data.courses : [];
        const templateData = processGeneralReportData(data);
        const {html, js} = await Templates.renderForPromise('local_datacurso_ratings/ratings_report_page', templateData);
        Templates.replaceNodeContents(container, html, js);
        bindControls();
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Process report data for template rendering.
 *
 * @param {Object} data Raw data from WS.
 * @returns {Object}
 */
const processGeneralReportData = (data) => {
    const pagination = data.pagination || {};

    return {
        courses: data.courses || [],
        has_data: !!data.has_data,
        categories,
        summary: data.summary || {},
        pagination: {
            ...pagination,
            perpage_options: ALLOWED_PER_PAGE.map((value) => ({
                value,
                selected: Number(pagination.perpage) === value,
            })),
        },
    };
};

/**
 * Resolve selected category ID from datalist input.
 *
 * @returns {number}
 */
const resolveSelectedCategoryId = () => {
    const categoryInput = document.querySelector('[data-action="global-report-category-filter"]');
    if (!categoryInput) {
        return 0;
    }

    const selectedName = categoryInput.value.trim();
    if (!selectedName) {
        return 0;
    }

    const datalist = document.querySelector('[data-region="global-report-categories"]');
    if (!datalist) {
        return 0;
    }

    const options = datalist.querySelectorAll('option');
    for (const option of options) {
        if (option.value === selectedName) {
            const id = Number(option.dataset.id || 0);
            return Number.isNaN(id) ? 0 : id;
        }
    }

    return 0;
};

/**
 * Normalize course filter value.
 *
 * @param {string} value Raw input value.
 * @param {HTMLInputElement} input Input element.
 * @returns {string}
 */
const normalizeCourseFilterValue = (value, input) => {
    const current = (value || '').trim();
    const allcourseslabel = (input?.dataset?.allcoursesLabel || '').trim();

    if (!current) {
        return '';
    }

    if (allcourseslabel && current.toLowerCase() === allcourseslabel.toLowerCase()) {
        return '';
    }

    return current;
};

/**
 * Normalize date value from date input.
 *
 * @param {string} value Raw date value.
 * @returns {string}
 */
const normalizeDateValue = (value) => {
    const current = (value || '').trim();
    if (!current) {
        return '';
    }

    return /^\d{4}-\d{2}-\d{2}$/.test(current) ? current : '';
};

/**
 * Update course datalist based on selected category.
 *
 * @param {number} categoryid
 */
const updateCoursesByCategory = async(categoryid) => {
    const coursesDatalist = document.querySelector('[data-region="global-report-courses"]');
    const courseInput = document.querySelector('[data-action="global-report-course-filter"]');
    if (!coursesDatalist || !courseInput) {
        return;
    }

    courseInput.value = '';

    coursesDatalist.innerHTML = '';

    const allcourseslabel = (courseInput.dataset.allcoursesLabel || '').trim();
    if (allcourseslabel) {
        const allOption = document.createElement('option');
        allOption.value = allcourseslabel;
        coursesDatalist.appendChild(allOption);
    }

    if (!categoryid) {
        return;
    }

    try {
        const request = Ajax.call([{
            methodname: 'local_datacurso_ratings_get_courses_by_category',
            args: {categoryid},
        }]);

        const courses = await request[0];
        if (!Array.isArray(courses)) {
            return;
        }

        courses.forEach((course) => {
            const option = document.createElement('option');
            option.value = course.fullname;
            coursesDatalist.appendChild(option);
        });
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Export currently loaded page rows to CSV.
 */
const exportToCSV = () => {
    if (!Array.isArray(cachedCourses) || cachedCourses.length === 0) {
        return;
    }

    const headers = [
        'Curso',
        'Actividad',
        'Total valoraciones',
        'Me gusta',
        'No me gusta',
        'Indice de satisfaccion',
        'Comentarios',
    ];

    const rows = [];

    cachedCourses.forEach((course) => {
        const coursename = course.courseName || '';
        const activities = Array.isArray(course.activities) ? course.activities : [];

        activities.forEach((activity) => {
            const comments = Array.isArray(activity.comments) ? activity.comments.join(' / ') : '';
            rows.push([
                coursename,
                activity.activity || '',
                activity.total_ratings || 0,
                activity.likes || 0,
                activity.dislikes || 0,
                activity.formatted_percentage || '',
                comments,
            ]);
        });
    });

    const csvcontent = [
        headers.join(','),
        ...rows.map((row) => row.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(',')),
    ].join('\n');

    const blob = new Blob(['\ufeff' + csvcontent], {type: 'text/csv;charset=utf-8;'});
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `reporte_valoraciones_${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
};

/**
 * Bind filters and pagination controls.
 */
const bindControls = () => {
    const previousButton = document.querySelector('[data-action="global-report-prev"]');
    const nextButton = document.querySelector('[data-action="global-report-next"]');
    const gotoInput = document.querySelector('[data-action="global-report-goto"]');
    const perPageSelect = document.querySelector('[data-action="global-report-perpage"]');
    const exportCsvButton = document.querySelector('[data-action="global-report-export-csv"]');
    const searchActivityInput = document.querySelector('[data-action="global-report-activity-search"]');
    const courseFilterInput = document.querySelector('[data-action="global-report-course-filter"]');
    const categoryFilterInput = document.querySelector('[data-action="global-report-category-filter"]');
    const dateFromInput = document.querySelector('[data-action="global-report-date-from"]');
    const dateToInput = document.querySelector('[data-action="global-report-date-to"]');

    if (dateFromInput) {
        dateFromInput.value = state.datefrom;
    }

    if (dateToInput) {
        dateToInput.value = state.dateto;
    }

    if (previousButton) {
        previousButton.addEventListener('click', async(event) => {
            event.preventDefault();
            const page = Number(previousButton.dataset.page);
            if (Number.isNaN(page) || page < 0) {
                return;
            }
            state.page = page;
            await fetchAndRender();
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', async(event) => {
            event.preventDefault();
            const page = Number(nextButton.dataset.page);
            if (Number.isNaN(page) || page < 0) {
                return;
            }
            state.page = page;
            await fetchAndRender();
        });
    }

    if (gotoInput) {
        const goToPage = async() => {
            const target = Number(gotoInput.value);
            if (Number.isNaN(target) || target < 1) {
                return;
            }
            state.page = target - 1;
            await fetchAndRender();
        };

        gotoInput.addEventListener('change', goToPage);
        gotoInput.addEventListener('keydown', async(event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                await goToPage();
            }
        });
    }

    if (perPageSelect) {
        perPageSelect.addEventListener('change', async() => {
            const value = Number(perPageSelect.value);
            if (!ALLOWED_PER_PAGE.includes(value)) {
                return;
            }
            state.perpage = value;
            state.page = 0;
            await fetchAndRender();
        });
    }

    if (exportCsvButton) {
        exportCsvButton.addEventListener('click', exportToCSV);
    }

    if (searchActivityInput) {
        searchActivityInput.addEventListener('change', async() => {
            state.searchactivity = searchActivityInput.value.trim();
            state.page = 0;
            await fetchAndRender();
        });
    }

    if (courseFilterInput) {
        courseFilterInput.addEventListener('change', async() => {
            state.searchcourse = normalizeCourseFilterValue(courseFilterInput.value, courseFilterInput);
            state.page = 0;
            await fetchAndRender();
        });
    }

    if (categoryFilterInput) {
        categoryFilterInput.addEventListener('change', async() => {
            const categoryid = resolveSelectedCategoryId();
            state.categoryid = categoryid;
            state.page = 0;
            await updateCoursesByCategory(categoryid);
            await fetchAndRender();
        });
    }

    if (dateFromInput) {
        dateFromInput.addEventListener('change', async() => {
            state.datefrom = normalizeDateValue(dateFromInput.value);
            state.page = 0;
            await fetchAndRender();
        });
    }

    if (dateToInput) {
        dateToInput.addEventListener('change', async() => {
            state.dateto = normalizeDateValue(dateToInput.value);
            state.page = 0;
            await fetchAndRender();
        });
    }
};

export default {init};
