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
 * AMD module for older versions of Moodle
 *
 * @module     tool_vault/apikey_amd
 * @copyright  2024 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    var SELECTORS = {
        APIKEY_FORM_CONTAINER: '#getapikey_formplaceholder',
        APIKEY_IFRAME: '#getapikey_iframe',
        SIGNIN_BUTTON: '#getapikey_signin',
        SIGNUP_BUTTON: '#getapikey_signup',
        ENTER_KEY_BUTTON: '#getapikey_enterapikey',
        LEGACY_FORM_CONTAINER: '#getapikey_legacyform'
    };

    /**
     * Open iframe with the remote login/signup form
     *
     * @param {Event} e
     */
    var openLoginSignupModal = function(e) {
        document.querySelector(SELECTORS.APIKEY_FORM_CONTAINER).innerHTML = '';
        var formContainer = document.querySelector(SELECTORS.LEGACY_FORM_CONTAINER);
        if (formContainer) {
            formContainer.classList.add('hidden');
        }

        var signInButton = e.target;
        var loginSignupIframe = document.querySelector(SELECTORS.APIKEY_IFRAME);
        var url = (signInButton && loginSignupIframe) ? signInButton.dataset.target : null;

        loginSignupIframe.src = url;
        loginSignupIframe.style.display = 'block';
    };

    /**
     * Close iframe with the remote login/signup form
     */
    var closeLoginSignupModal = function() {
        var loginSignupIframe = document.querySelector(SELECTORS.APIKEY_IFRAME);
        loginSignupIframe.style.display = 'none';
        loginSignupIframe.src = 'about:blank';
    };

    var init = function(onMessage) {
        var signInButton = document.querySelector(SELECTORS.SIGNIN_BUTTON);
        var signUpButton = document.querySelector(SELECTORS.SIGNUP_BUTTON);
        var loginSignupIframe = document.querySelector(SELECTORS.APIKEY_IFRAME);
        var url = (signInButton && loginSignupIframe) ? signInButton.dataset.target : null;

        if (!url) {
            return;
        }
        var urlHost = url.match(/^(https?:\/\/[^/]+)(.*)$/)[1];

        signInButton.onclick = openLoginSignupModal;
        if (signUpButton) {
            signUpButton.onclick = openLoginSignupModal;
        }

        var enterApikeyButton = document.querySelector(SELECTORS.ENTER_KEY_BUTTON);
        enterApikeyButton.addEventListener('click', function() {
            closeLoginSignupModal();
        });

        window.addEventListener(
            "message",
            function(event) {
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

    /**
     * Open form to enter API key
     *
     * @param {String} apikey
     * @param {Boolean} autoSubmit
     */
    var openApikeyForm = function(apikey, autoSubmit) {
        closeLoginSignupModal();
        var formContainer = document.querySelector(SELECTORS.LEGACY_FORM_CONTAINER);
        formContainer.classList.remove('hidden');

        if (apikey && apikey !== '') {
            formContainer.querySelector('input[name="apikey"]').value = apikey;
            if (autoSubmit) {
                formContainer.querySelector('input[name="submitbutton"]').click();
            }
        }
    };

    return {
        // Public variables and functions.

        /**
         * Initialise the module.
         *
         * @method init
         */
        'init': function() {
            init(function(event) {
                if (event.data.action === 'apikey') {
                    openApikeyForm(event.data.apikey, true);
                }
            });

            var enterApikeyButton = document.querySelector(SELECTORS.ENTER_KEY_BUTTON);
            enterApikeyButton.addEventListener('click', function() {
                openApikeyForm();
            });
        }
    };
});
