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
 * Allows to enter API key
 *
 * @module     tool_vault/apikey
 * @copyright  2023 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import DynamicForm from 'core_form/dynamicform';
import Pending from 'core/pending';
import Notification from 'core/notification';

/**
 * Open form to enter API key
 *
 * @param {String} apikey
 * @param {Boolean} autoSubmit
 */
const openApikeyForm = (apikey = '', autoSubmit = false) => {
    const pendingPromise = new Pending('tool_vault/apikeyform:open');
    closeLoginSignupModal();

    const formContainer = document.getElementById('getapikey_formplaceholder');
    const apikeyForm = new DynamicForm(formContainer, '\\tool_vault\\form\\apikey_form');

    // After submitting reresh the page.
    apikeyForm.addEventListener(apikeyForm.events.FORM_SUBMITTED, () => location.reload());

    apikeyForm.load({apikey})
        .then(() => {
            if (autoSubmit) {
                apikeyForm.submitFormAjax();
            }
            return pendingPromise.resolve();
        })
        .catch(Notification.exception);
};

const closeApikeyForm = () => {
    const formContainer = document.getElementById('getapikey_formplaceholder');
    formContainer.innerHTML = '';
};

const openLoginSignupModal = () => {
    closeApikeyForm();

    const loginSignupButton = document.getElementById('getapikey_loginsignup');
    const loginSignupIframe = document.getElementById('getapikey_iframe');
    const url = (loginSignupButton && loginSignupIframe) ? loginSignupButton.dataset.target : null;

    loginSignupIframe.src = url;
    loginSignupIframe.style.display = 'block';
};

const closeLoginSignupModal = () => {
    const loginSignupIframe = document.getElementById('getapikey_iframe');
    loginSignupIframe.style.display = 'none';
    loginSignupIframe.src = 'about:blank';
};

export const init = () => {
    const loginSignupButton = document.getElementById('getapikey_loginsignup');
    const enterApikeyButton = document.getElementById('getapikey_enterapikey');
    const loginSignupIframe = document.getElementById('getapikey_iframe');
    const url = (loginSignupButton && loginSignupIframe) ? loginSignupButton.dataset.target : null;

    if (!url) {
        return;
    }
    const urlHost = url.match(/^(https?:\/\/[^/]+)(.*)$/)[1];

    loginSignupButton.onclick = openLoginSignupModal;

    enterApikeyButton.onclick = () => openApikeyForm();

    window.addEventListener(
        "message",
        (event) => {
            if (event.origin !== urlHost || !event.data || !(typeof event.data === 'object')) {
                return;
            }
            if (event.data.action === 'apikey') {
                openApikeyForm(event.data.apikey, true);
            } else if (event.data.action === 'close') {
                closeLoginSignupModal();
            }
        },
        false);


};