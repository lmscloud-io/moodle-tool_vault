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
const CLICOMMAND = '/usr/bin/php admin/tool/vault/cli/addon_plugins.php';

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
            el.addEventListener('change', () => {
                updateCliInstructions(pluginRegionNode, el);
                updateCliInstructionsBulk();
            });
        });
        const initialRadio = pluginRegionNode.querySelector(SELECTORS.ADDON_VERSION_RADIO + ':checked');
        updateCliInstructions(pluginRegionNode, initialRadio);

        const cliButton = pluginRegionNode.querySelector(SELECTORS.ADDON_CLI_BUTTON);
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

        cliButton?.addEventListener('click', e => {
            e.preventDefault();
            pluginRegionNode.dataset.cliexpanded = `${pluginRegionNode.dataset.cliexpanded}` === "1" ? "0" : "1";
        });
    });

    updateCliInstructionsBulk();
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
    let commandName = ' --name=' + pluginname;
    if (pluginRegionNode.dataset.versionlocal) {
        commandName += ' --overwrite';
    }
    if (source === '') {
        cli.innerHTML = '';
    } else if (source.match(/^backupkey\//)) {
        cli.innerHTML = '<pre>' + CLICOMMAND +
            ' --backupkey=' + source.substring(10) + commandName + '</pre>';
    } else {
        cli.innerHTML = '<pre>' + CLICOMMAND + commandName + '</pre>';
    }
};

const updateCliInstructionsBulk = () => {
    document.querySelectorAll(SELECTORS.ADDON_PLUGIN_REGION + '[data-isbulk="1"]').forEach(bulkRegion => {
        const cli = bulkRegion.querySelector(SELECTORS.ADDON_CLI_REGION);
        if (!cli) {
            return;
        }
        const commands = {};
        const pluginnames = bulkRegion.dataset.pluginnames.split(',');
        for (let pluginname of pluginnames) {
            const pluginRegion = getPluginRegion(pluginname);
            const el = pluginRegion.querySelector(SELECTORS.ADDON_VERSION_RADIO + ':checked');
            let k = 'moodleorg';
            let prefix = CLICOMMAND;
            if (!el || `${el?.value}` === '') {
                continue;
            } else if (el?.value.match(/^backupkey\//)) {
                k = el.value.substring(10);
                prefix += ' --backupkey=' + el.value.substring(10);
            }
            commands[k] = ((k in commands) ? `${commands[k]},` : `${prefix} --name=`) + pluginname + el.dataset.exactversion;
        }
        if (Object.keys(commands).length) {
            cli.innerHTML = '<pre>' + Object.values(commands).join("\n") + '</pre>';
        } else {
            cli.innerHTML = '';
        }
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
            title: getString('addoninstalldialoguetitle', 'tool_vault'),
        },
        formClass: '\\tool_vault\\form\\install_plugin_form',
        args: {plugins: JSON.stringify(args)},
        saveButtonText: getString('continue', 'moodle')
    });

    // Show a toast notification when the form is submitted.
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, event => {
        for (let pluginname of event.detail.installed) {
            const pluginRegionNode = getPluginRegion(pluginname);
            pluginRegionNode.querySelector(SELECTORS.ADDON_CLI_BUTTON)?.remove();
            pluginRegionNode.querySelector(SELECTORS.ADDON_INSTALL_BUTTON)?.remove();
            pluginRegionNode.querySelector(SELECTORS.ADDON_CLI_REGION)?.remove();
            pluginRegionNode.querySelectorAll(SELECTORS.ADDON_VERSION_RADIO).forEach(el => {
                if (el.value !== sources[pluginname]) {
                    el.closest('label')?.classList.add('dimmed_text');
                }
                el.remove();
            });
        }

        updateCliInstructionsBulk();

        return Notification.alert(
            getString('addoninstalldialoguetitle', 'tool_vault'),
            event.detail.output
        );
    });

    modalForm.show();
};
