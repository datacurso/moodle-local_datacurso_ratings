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
 * General ratings report JS
 *
 * @module     local_datacurso_ratings/ratings_report
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';

/**
 * Initialize the general ratings report
 */
export const init = () => {
    const container = document.querySelector('#general-ratings-report-container');
    if (!container) {
        return;
    }

    let categories = [];
    try {
        categories = JSON.parse(container.dataset.categories || '[]');
    } catch (e) {
        Notification.alert('Error', 'Error parsing categories data: ' + e.message, 'OK');
        return;
    }

    // Show loading state
    showLoading(container);

    // Call the web service
    Ajax.call([{
        methodname: 'local_datacurso_ratings_get_ratings_report',
        args: {}
    }])[0]
        .then((data) => processGeneralReportData(data, categories))
        .then((templateData) => Templates.render('local_datacurso_ratings/ratings_report_page', templateData))
        .then((html, js) => {
            // Replace loading with actual content
            container.innerHTML = html;
            Templates.runTemplateJS(js);

            // Initialize additional functionality
            initGeneralTableFeatures();
        })
        .catch((error) => {
            Notification.exception(error);
        });
};

/**
 * Process the raw data from web service
 * @param {Array} data Raw data from web service
 * @param {Array} categories List of categories from PHP
 * @returns {Object} Processed data for template
 */
function processGeneralReportData(data, categories) {
    // Group data by course
    const courseGroups = {};
    let totalLikes = 0;
    let totalDislikes = 0;
    let totalActivities = 0;

    data.forEach((activity) => {
        const courseName = activity.course || 'Unnamed course';

        if (!courseGroups[courseName]) {
            courseGroups[courseName] = {
                courseName: courseName,
                categoryid: activity.categoryid || '',
                activities: [],
                courseLikes: 0,
                courseDislikes: 0,
                courseActivities: 0
            };
        }

        // Defensive defaults
        const likes = Number(activity.likes || 0);
        const dislikes = Number(activity.dislikes || 0);
        const approvalpercent = Number(activity.approvalpercent || 0);
        const comments = Array.isArray(activity.comments) ? activity.comments : [];

        // Process individual activity
        const processedActivity = {
            ...activity,
            likes,
            dislikes,
            total_ratings: likes + dislikes,
            has_ratings: (likes + dislikes) > 0,
            has_comments: comments.length > 0,
            satisfaction_class: getSatisfactionClass(approvalpercent),
            formatted_percentage: `${approvalpercent.toFixed(1)}%`,
            comments_count: comments.length,
            comments_text: comments.join(' / ')
        };

        courseGroups[courseName].activities.push(processedActivity);
        courseGroups[courseName].courseLikes += likes;
        courseGroups[courseName].courseDislikes += dislikes;
        courseGroups[courseName].courseActivities += 1;

        // Global totals
        totalLikes += likes;
        totalDislikes += dislikes;
        totalActivities += 1;
    });

    // Calculate course-level statistics
    Object.keys(courseGroups).forEach((courseName) => {
        const course = courseGroups[courseName];
        const courseTotal = course.courseLikes + course.courseDislikes;
        course.courseSatisfaction = courseTotal > 0
            ? ((course.courseLikes / courseTotal) * 100).toFixed(1)
            : '0';
        course.courseSatisfactionClass = getSatisfactionClass(parseFloat(course.courseSatisfaction));
        course.courseTotal = courseTotal;

        // Sort activities by satisfaction percentage (highest first)
        course.activities.sort((a, b) => b.approvalpercent - a.approvalpercent);
    });

    // Convert to array and sort courses by total ratings
    const coursesArray = Object.values(courseGroups).sort((a, b) => b.courseTotal - a.courseTotal);

    const totalRatings = totalLikes + totalDislikes;
    const overallSatisfaction = totalRatings > 0 ? ((totalLikes / totalRatings) * 100) : 0;

    return {
        courses: coursesArray,
        has_data: coursesArray.length > 0,
        categories,
        summary: {
            total_courses: coursesArray.length,
            total_activities: totalActivities,
            total_ratings: totalRatings,
            total_likes: totalLikes,
            total_dislikes: totalDislikes,
            overall_satisfaction: overallSatisfaction.toFixed(1),
            satisfaction_class: getSatisfactionClass(overallSatisfaction),
            activities_with_ratings: data.filter((a) => (Number(a.likes || 0) + Number(a.dislikes || 0)) > 0).length
        }
    };
}

/**
 * Get CSS class based on satisfaction percentage
 * @param {Number} percentage
 * @returns {String}
 */
function getSatisfactionClass(percentage) {
    if (percentage >= 80) { return 'success'; }
    if (percentage >= 60) { return 'warning'; }
    if (percentage >= 40) { return 'info'; }
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
 * Initialize additional table features
 */
function initGeneralTableFeatures() {
    // Course collapse/expand functionality
    document.querySelectorAll('.course-toggle').forEach((button) => {
        button.addEventListener('click', () => {
            const target = button.getAttribute('data-target');
            const courseContent = document.querySelector(target);
            const icon = button.querySelector('i');

            if (!courseContent) { return; }

            if (courseContent.classList.contains('show')) {
                courseContent.classList.remove('show');
                if (icon) {
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-right');
                }
            } else {
                courseContent.classList.add('show');
                if (icon) {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-down');
                }
            }
        });
    });

    // Filter functionality
    initFilterFeatures();
}

/**
 * Initialize filter functionality
 */
function initFilterFeatures() {
    const searchInput = document.querySelector('#activity-search');
    const courseFilter = document.querySelector('#course-filter');
    const categoryFilter = document.querySelector('#category-filter');

    if (searchInput) {
        searchInput.addEventListener('input', filterActivities);
    }

    if (courseFilter) {
        courseFilter.addEventListener('input', filterActivities);
    }

    if (categoryFilter) {
        categoryFilter.addEventListener('input', () => {
            const selectedName = categoryFilter.value;
            let categoryid = '';

            const datalist = document.querySelector('#categories');
            if (datalist) {
                const options = datalist.querySelectorAll('option');
                options.forEach((option) => {
                    if (option.value === selectedName) {
                        categoryid = option.getAttribute('data-id') || '';
                    }
                });
            }

            // Store id in dataset (use consistent key)
            categoryFilter.dataset.categoryId = categoryid;

            filterActivities();
            updateCoursesByCategory(categoryid);
        });
    }
}

/**
 * Call WS to fetch courses of a category and update the course selector
 * @param {String|Number} categoryid
 */
function updateCoursesByCategory(categoryid) {
    const courseFilter = document.querySelector('#course-filter');
    const coursesDatalist = document.querySelector('#courses');
    if (!courseFilter || !coursesDatalist) {
        return;
    }

    courseFilter.value = '';

    if (!categoryid) {
        return;
    }

    Ajax.call([{
        methodname: 'local_datacurso_ratings_get_courses_by_category',
        args: { categoryid: parseInt(categoryid, 10) }
    }])[0]
        .then((courses) => {
            if (Array.isArray(courses) && courses.length) {
                courses.forEach((course) => {
                    const option = document.createElement('option');
                    option.value = course.fullname;
                    option.textContent = course.fullname;
                    coursesDatalist.appendChild(option);
                });
            }
        })
        .catch((err) => {
            Notification.exception(err);
        });
}

/**
 * Filter activities based on search and course selection
 */
function filterActivities() {
    const searchTerm = (document.querySelector('#activity-search')?.value || '').toLowerCase();
    const selectedCourse = document.querySelector('#course-filter')?.value || '';
    const categoryFilter = document.querySelector('#category-filter');
    const selectedCategory = categoryFilter?.dataset?.categoryId || '';

    document.querySelectorAll('.course-section').forEach((section) => {
        const courseId = section.getAttribute('data-course') || '';
        const categoryId = section.getAttribute('data-category') || '';
        let courseVisible = false;

        const matchesCategory = selectedCategory === '' || categoryId === selectedCategory;

        if (matchesCategory && (selectedCourse === '' || courseId === selectedCourse)) {
            section.querySelectorAll('.activity-row').forEach((row) => {
                const activityName = (row.getAttribute('data-activity') || '').toLowerCase();
                const matchesSearch = searchTerm === '' || activityName.includes(searchTerm);

                if (matchesSearch) {
                    row.style.display = '';
                    courseVisible = true;
                } else {
                    row.style.display = 'none';
                }
            });
        } else {
            courseVisible = false;
        }

        section.style.display = courseVisible ? '' : 'none';
    });
}

export default { init };
