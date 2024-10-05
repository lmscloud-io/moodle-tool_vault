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
 * Links to install add-on plugins
 *
 * @module     tool_vault/install_addon
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import {SELECTORS} from './selectors';
import {get_string as getString} from 'core/str';

let initialised = false;

/**
 * Initialise listeners on the page
 */
export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;
    document.addEventListener('click', e => {
        const target = e.target.closest(SELECTORS.INSTALL_ADDON_LINK);
        if (target) {
            e.preventDefault();
            openInstallAddonForm(target);
        }
    });
};

/**
 * Open form to enter API key
 *
 * @param {Node} el
 */
const openInstallAddonForm = (el) => {
    const args = el.dataset;

    const modalForm = new ModalForm({
        modalConfig: {
            title: 'Install add-on plugin',
        },
        formClass: '\\tool_vault\\form\\install_plugin_form',
        args,
        saveButtonText: getString('continue', 'moodle')
    });

    // Show a toast notification when the form is submitted.
    // modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, event => {
    //     if (event.detail.result) {
    //         getString('template_saved', 'feedback').then(addToast).catch();
    //     } else {
    //         getString('saving_failed', 'feedback').then(string => {
    //             return Notification.addNotification({
    //                 type: 'error',
    //                 message: string
    //             });
    //         }).catch();
    //     }
    // });

    // After submitting reresh the page.
    // installAddonForm.addEventListener(installAddonForm.events.FORM_SUBMITTED, () => location.reload());

    modalForm.show();
};
