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
import Notification from 'core/notification';

let initialised = false;

/**
 * Initialise listeners on the page
 */
export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;

    document.querySelectorAll(SELECTORS.ADDON_PLUGIN_REGION).forEach(pluginRegionNode => {

        const installButton = pluginRegionNode.dataset.writable ?
            pluginRegionNode.querySelector(SELECTORS.ADDON_INSTALL_BUTTON) : null;

        installButton?.addEventListener('click', e => {
            e.preventDefault();
            if (pluginRegionNode.dataset.isbulk) {
                openInstallAddonForm(pluginRegionNode.dataset.pluginnames.split(','));
            } else {
                openInstallAddonForm([pluginRegionNode.dataset.pluginname]);
            }
        });

    });

};

const getPluginRegion = (pluginname) =>
    document.querySelector(SELECTORS.ADDON_PLUGIN_REGION + `[data-pluginname="${pluginname}"]`);

/**
 * Open form to enter API key
 *
 * @param {Array} pluginnames
 */
const openInstallAddonForm = (pluginnames) => {

    const args = [];
    const sources = {};
    for (let pluginname of pluginnames) {
        const pluginRegion = getPluginRegion(pluginname);
        if (pluginRegion?.dataset.writable) {
            const source = pluginRegion.querySelector(SELECTORS.ADDON_VERSION_RADIO + ':checked')?.value;
            if (`${source}` !== '') {
                args.push({pluginname, source});
                sources[pluginname] = source;
            }
        }
    }

    if (!args.length) {
        return;
    }

    const modalForm = new ModalForm({
        modalConfig: {
            title: getString('addonplugins_installdialoguetitle', 'tool_vault'),
        },
        formClass: '\\tool_vault\\form\\install_plugin_form',
        args: {plugins: JSON.stringify(args)},
        saveButtonText: getString('continue', 'moodle')
    });

    // Show a toast notification when the form is submitted.
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, event => {
        for (let pluginname of event.detail.installed) {
            const pluginRegionNode = getPluginRegion(pluginname);
            pluginRegionNode.querySelector(SELECTORS.ADDON_INSTALL_BUTTON)?.remove();
            pluginRegionNode.querySelectorAll(SELECTORS.ADDON_VERSION_RADIO).forEach(el => {
                if (el.value !== sources[pluginname]) {
                    el.closest('label')?.classList.add('dimmed_text');
                }
                el.remove();
            });
        }

        return Notification.alert(
            getString('addonplugins_installdialoguetitle', 'tool_vault'),
            event.detail.output
        );
    });

    modalForm.show();
};
