/* eslint-disable no-unused-vars */
/* eslint-disable capitalized-comments */
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

define([
    'core/notification',
    'core/str',
], function(Notification, Str) {

var SELECTORS = {
    APIKEY_FORM_CONTAINER: '#getapikey_formplaceholder',
    APIKEY_IFRAME: '#getapikey_iframe',
    SIGNIN_BUTTON: '#getapikey_signin',
    SIGNUP_BUTTON: '#getapikey_signup',
    ENTER_KEY_BUTTON: '#getapikey_enterapikey',
    LEGACY_FORM_CONTAINER: '#getapikey_legacyform'
};

var initialised = false;

/**
 * Initialise listeners on the page
 */
var init = function() {
    if (initialised) {
        return;
    }
    initialised = true;

    document.querySelectorAll(SELECTORS.ADDON_PLUGIN_REGION).forEach(function(pluginRegionNode) {

        var installButton = pluginRegionNode.dataset.writable ?
            pluginRegionNode.querySelector(SELECTORS.ADDON_INSTALL_BUTTON) : null;

        if (!installButton) {
            return;
        }

        installButton.addEventListener('click', function(e) {
            e.preventDefault();
            if (pluginRegionNode.dataset.isbulk) {
                openInstallAddonForm(pluginRegionNode.dataset.pluginnames.split(','));
            } else {
                openInstallAddonForm([pluginRegionNode.dataset.pluginname]);
            }
        });

    });

};

var getPluginRegion = function(pluginname) {
    return document.querySelector(SELECTORS.ADDON_PLUGIN_REGION + '[data-pluginname="' + pluginname + '"]');
};

/**
 * Open form to enter API key
 *
 * @param {Array} pluginnames
 */
var openInstallAddonForm = function(pluginnames) {
/*
    var args = [];
    var sources = {};
    for (let pluginname of pluginnames) {
        var pluginRegion = getPluginRegion(pluginname);
        if (pluginRegion?.dataset.writable) {
            var source = pluginRegion.querySelector(SELECTORS.ADDON_VERSION_RADIO + ':checked')?.value;
            if (`${source}` !== '') {
                args.push({pluginname, source});
                sources[pluginname] = source;
            }
        }
    }

    if (!args.length) {
        return;
    }

    var modalForm = new ModalForm({
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
            var pluginRegionNode = getPluginRegion(pluginname);
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
    */
};

return {
    'init': init,
};
});