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
import {add as addToast} from 'core/toast';
import Notification from 'core/notification';

let initialised = false;
const CLICOMMAND = 'php admin/tool/vault/cli/addon_plugins.php';

/**
 * Initialise listeners on the page
 */
export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;

    document.querySelectorAll(SELECTORS.ADDON_PLUGIN_REGION).forEach(pluginRegionNode => {
        pluginRegionNode.querySelectorAll(SELECTORS.ADDON_VERSION_RADIO).forEach(el => {
            el.addEventListener('change', () => updateCliInstructions(pluginRegionNode, el));
        });
        const initialRadio = pluginRegionNode.querySelector(SELECTORS.ADDON_VERSION_RADIO + ':checked');
        updateCliInstructions(pluginRegionNode, initialRadio);

        const cliButton = pluginRegionNode.querySelector(SELECTORS.ADDON_CLI_BUTTON);
        const installButton = pluginRegionNode.dataset.writable ?
            pluginRegionNode.querySelector(SELECTORS.ADDON_INSTALL_BUTTON) : null;
        const pluginname = pluginRegionNode.dataset.pluginname;

        installButton?.addEventListener('click', e => {
            e.preventDefault();
            window.console.log('Add-on install button pressed');
            const source = pluginRegionNode.querySelector(SELECTORS.ADDON_VERSION_RADIO + ':checked')?.value;
            openInstallAddonForm(pluginRegionNode, {pluginname, source});
        });

        cliButton?.addEventListener('click', e => {
            e.preventDefault();
            window.console.log('Add-on cli button pressed');
            pluginRegionNode.dataset.cliexpanded = `${pluginRegionNode.dataset.cliexpanded}` === "1" ? "0" : "1";
        });
    });
};

/**
 * Return value for the 'cli instructions'
 *
 * @param {Node} pluginRegionNode
 * @param {Node} el selected radio input
 */
const updateCliInstructions = (pluginRegionNode, el) => {
    const cli = pluginRegionNode.querySelector(SELECTORS.ADDON_CLI_REGION);
    if (!cli) {
        return;
    }
    if (!el) {
        cli.innerHTML = '';
        return;
    }
    const source = `${el.value}`;
    const pluginname = pluginRegionNode.dataset.pluginname + el.dataset.exactversion;
    if (source === '') {
        cli.innerHTML = '';
    } else if (source.match(/^backupkey\//)) {
        cli.innerHTML = '<pre>' + CLICOMMAND +
            ' --backupkey=' + source.substring(10) + ' --name=' + pluginname + '</pre>';
    } else {
        cli.innerHTML = '<pre>' + CLICOMMAND + ' --name=' + pluginname + '</pre>';
    }
};

/**
 * Open form to enter API key
 *
 * @param {Node} pluginRegionNode
 * @param {Object} args
 */
const openInstallAddonForm = (pluginRegionNode, args) => {

    const modalForm = new ModalForm({
        modalConfig: {
            title: 'Install add-on plugin',
        },
        formClass: '\\tool_vault\\form\\install_plugin_form',
        args,
        saveButtonText: getString('continue', 'moodle')
    });

    // Show a toast notification when the form is submitted.
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, event => {
        if (event.detail.success) {
            addToast(event.detail.output);
            pluginRegionNode.querySelector(SELECTORS.ADDON_CLI_BUTTON)?.remove();
            pluginRegionNode.querySelector(SELECTORS.ADDON_INSTALL_BUTTON)?.remove();
            pluginRegionNode.querySelector(SELECTORS.ADDON_CLI_REGION)?.remove();
            pluginRegionNode.querySelectorAll(SELECTORS.ADDON_VERSION_RADIO).forEach(el => {
                if (el.value !== args.source) {
                    el.closest('label')?.classList.add('dimmed_text');
                }
                el.remove();
            });
        } else {
            return Notification.alert(
                getString('error', 'moodle'),
                event.detail.output
            );
        }
        return event.detail.success;
    });

    modalForm.show();
};
