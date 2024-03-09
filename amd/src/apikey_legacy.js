// This file is part of plugin tool_vault - https://lmsvault.io
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
 * Allows to enter API key (without using dynamic forms)
 *
 * @module     tool_vault/apikey_legacy
 * @copyright  2023 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Signon from './signon';
import {SELECTORS} from './selectors';

/**
 * Open form to enter API key
 *
 * @param {String} apikey
 * @param {Boolean} autoSubmit
 */
const openApikeyForm = (apikey = '', autoSubmit = false) => {
    Signon.closeLoginSignupModal();
    const formContainer = document.querySelector(SELECTORS.LEGACY_FORM_CONTAINER);
    formContainer.classList.remove('hidden');

    if (apikey && apikey !== '') {
        formContainer.querySelector('input[name="apikey"]').value = apikey;
        if (autoSubmit) {
            formContainer.querySelector('input[name="submitbutton"]').click();
        }
    }
};

/**
 * Initialise listeners on the page
 */
export const init = () => {
    Signon.init((event) => {
        if (event.data.action === 'apikey') {
            openApikeyForm(event.data.apikey, true);
        }
    });

    const enterApikeyButton = document.querySelector(SELECTORS.ENTER_KEY_BUTTON);
    enterApikeyButton.addEventListener('click', () => {
        openApikeyForm();
    });
};
