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
 * Manages login/register with lmsvault.io
 *
 * @module     tool_vault/signon
 * @copyright  2024 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {SELECTORS} from './selectors';

/**
 * Open iframe with the remote login/signup form
 *
 * @param {Event} e
 */
const openLoginSignupModal = (e) => {
    document.querySelector(SELECTORS.APIKEY_FORM_CONTAINER).innerHTML = '';
    document.querySelector(SELECTORS.LEGACY_FORM_CONTAINER)?.classList.add('hidden');

    const signInButton = e.target;
    const loginSignupIframe = document.querySelector(SELECTORS.APIKEY_IFRAME);
    const url = (signInButton && loginSignupIframe) ? signInButton.dataset.target : null;

    loginSignupIframe.src = url;
    loginSignupIframe.style.display = 'block';
};

/**
 * Close iframe with the remote login/signup form
 */
export const closeLoginSignupModal = () => {
    const loginSignupIframe = document.querySelector(SELECTORS.APIKEY_IFRAME);
    loginSignupIframe.style.display = 'none';
    loginSignupIframe.src = 'about:blank';
};

export const init = (onMessage) => {
    const signInButton = document.querySelector(SELECTORS.SIGNIN_BUTTON);
    const signUpButton = document.querySelector(SELECTORS.SIGNUP_BUTTON);
    const loginSignupIframe = document.querySelector(SELECTORS.APIKEY_IFRAME);
    const url = (signInButton && loginSignupIframe) ? signInButton.dataset.target : null;

    if (!url) {
        return;
    }
    const urlHost = url.match(/^(https?:\/\/[^/]+)(.*)$/)[1];

    signInButton.onclick = openLoginSignupModal;
    if (signUpButton) {
        signUpButton.onclick = openLoginSignupModal;
    }

    const enterApikeyButton = document.querySelector(SELECTORS.ENTER_KEY_BUTTON);
    enterApikeyButton.addEventListener('click', () => {
        closeLoginSignupModal();
    });

    window.addEventListener(
        "message",
        (event) => {
            if (event.origin !== urlHost || !event.data || !(typeof event.data === 'object')) {
                return;
            }
            if (event.data.action === 'close') {
                closeLoginSignupModal();
            } else {
                onMessage(event);
            }
        },
        false);
};
