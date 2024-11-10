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
 * Javascript events for the `tool_vault` subsystem.
 *
 * @module tool_vault/vault
 * @copyright 2022 Marina Glancy
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'core/modal_factory',
    'core/modal_events',
    'core/templates',
    'core/notification',
    'core/str',
    'core/pending',
    'core/fragment'
], function(ModalFactory, ModalEvents, Templates, Notification, Str, Pending, Fragment) {

    var SELECTORS = {
        START_BACKUP: 'form[data-action="startbackup"]',
        START_DRYRUN: 'form[data-action="startdryrun"]',
        START_RESTORE: 'form[data-action="startrestore"]',
    };

    /**
     * Wrapper for ModalFactory.create that implements missing arguments
     *
     * @param {Object} modalConfig
     * @return {Object}
     */
    var createModalFactoryCompat = function(modalConfig) {
        return ModalFactory.create(modalConfig)
        .then(function(modal) {
            if (modalConfig.buttons) {
                Object.keys(modalConfig.buttons).forEach(function(key) {
                    var value = modalConfig.buttons[key];
                    var button = modal.getFooter().find("[data-action='" + key + "']");
                    modal.asyncSet(value, button.text.bind(button));
                });
            }
            return modal;
        });
    };

    var submitForm = function(backupForm, modal) {
        var popupBody = modal.getBody()[0];
        var fields = ['passphrase', 'description', 'bucket', 'expiredays', 'backupplugincode'];
        for (var idx in fields) {
            var i = fields[idx];
            var el1 = popupBody.querySelector('[name="' + i + '"]'),
                el2 = backupForm.querySelector('input[name="' + i + '"]');
            if (el1 && el2) {
                el2.value = el1.value;
            }
        }
        backupForm.setAttribute('action', backupForm.getAttribute('data-url'));
        backupForm.submit();
    };

    /**
     * Loads a fragment with a popup showing a spinner while the fragment is loading.
     * In case of an error, the error message is shown in the same popup.
     *
     * @param {String} title
     * @param {String} tempBody
     * @param {String} fragmentName
     * @param {Number} contextid
     * @param {Object} args
     * @returns {Promise}
     */
    var loadFragmentWithPopup = function(title, tempBody, fragmentName, contextid, args) {
        var activeModal = null;
        return createModalFactoryCompat({
            type: ModalFactory.types.CANCEL,
            title: title,
            body: tempBody
        })
        .then(function(res) {
            activeModal = res;
            activeModal.show();
            return Fragment.loadFragment('tool_vault', fragmentName, contextid, args);
        })
        .then(function(fragment) {
            activeModal.destroy();
            return fragment;
        })
        .catch(function(e) {
            if (activeModal) {
                activeModal.setBody(e.message);
            } else {
                Notification.exception(e);
            }
        });
    };

    var createModalForBackup = function(contextid) {
        var title, tempBody, saveButtonText;
        return Str.get_strings([
            {key: 'startbackup', component: 'tool_vault'},
            {key: 'pleasewait', component: 'tool_vault'},
            {key: 'startbackup', component: 'tool_vault'}
        ])
        .then(function(s) {
            title = s[0];
            tempBody = s[1];
            saveButtonText = s[2];
            return loadFragmentWithPopup(title, tempBody, 'start_backup', contextid);
        })
        .then(function(fragment) {
            if (!fragment) {
                return null;
            }
            return createModalFactoryCompat({
                type: ModalFactory.types.SAVE_CANCEL,
                title: title,
                body: fragment,
                buttons: {save: saveButtonText},
                removeOnClose: true
            });
        });
    };

    /**
     * Register listener for "start backup" button
     */
    var initStartBackup = function() {
        var backupForm = document.querySelector(SELECTORS.START_BACKUP);
        if (!backupForm) {
            return;
        }
        var contextid = backupForm.getAttribute('data-contextid');
        backupForm.addEventListener('submit', function(event) {
            event.preventDefault();
            var pendingPromise = new Pending('tool/vault:startBackupPopup');
            createModalForBackup(contextid)
            .then(function(modal) {
                if (modal) {
                    modal.show();

                    modal.getRoot().on(ModalEvents.save, function() {
                        submitForm(backupForm, modal);
                    });
                    modal.getRoot().on(ModalEvents.cancel, function() {
                        modal.hide();
                    });
                }
                pendingPromise.resolve();
                return null;
            })
            .catch(function(e) {
                Notification.exception(e);
                pendingPromise.resolve();
            });
        });
    };

    var initStartDryRun = function(backupkey) {
        var dryrunForm = document.querySelector(SELECTORS.START_DRYRUN + '[data-backupkey="' + backupkey + '"]');
        if (!dryrunForm) {
            return;
        }
        dryrunForm.addEventListener('submit', function(event) {
            event.preventDefault();
            createModalFactoryCompat({
                type: ModalFactory.types.SAVE_CANCEL,
                title: Str.get_string('startdryrun', 'tool_vault'),
                body: Templates.render('tool_vault/start_restore_popup',
                    {dryrun: 1, encrypted: parseInt(dryrunForm.getAttribute('data-encrypted'))}),
                buttons: {save: Str.get_string('startdryrun', 'tool_vault')},
                removeOnClose: true
            })
                .then(function(modal) {
                    modal.show();

                    modal.getRoot().on(ModalEvents.save, function() {
                        return submitForm(dryrunForm, modal);
                    });
                    modal.getRoot().on(ModalEvents.cancel, function() {
                        return modal.hide();
                    });

                    return modal;
                })
                .catch(Notification.exception);
        });
    };

    var initStartRestore = function(backupkey) {
        var restoreForm = document.querySelector(SELECTORS.START_RESTORE + '[data-backupkey="' + backupkey + '"]');
        if (!restoreForm) {
            return;
        }
        restoreForm.addEventListener('submit', function(event) {
            event.preventDefault();
            createModalFactoryCompat({
                type: ModalFactory.types.SAVE_CANCEL,
                title: Str.get_string('startrestore', 'tool_vault'),
                body: Templates.render('tool_vault/start_restore_popup',
                    {dryrun: 0, encrypted: parseInt(restoreForm.getAttribute('data-encrypted'))}),
                buttons: {save: Str.get_string('startrestore', 'tool_vault')},
                removeOnClose: true
            })
                .then(function(modal) {
                    modal.show();

                    modal.getRoot().on(ModalEvents.save, function() {
                        return submitForm(restoreForm, modal);
                    });
                    modal.getRoot().on(ModalEvents.cancel, function() {
                        return modal.hide();
                    });

                    return modal;
                })
                .catch(Notification.exception);
        });
    };

    var initCollapseExpandBackupLogs = function() {
        var logslong = document.querySelector('[data-vault-purpose="logslong"]');
        var logsshort = document.querySelector('[data-vault-purpose="logsshort"]');
        if (logslong && logsshort) {
            logslong.querySelector('[data-vault-purpose="togglelogs"]').addEventListener('click', function(event) {
                event.preventDefault();
                logsshort.style.display = 'block';
                logslong.style.display = 'none';
            });
            logsshort.querySelector('[data-vault-purpose="togglelogs"]').addEventListener('click', function(event) {
                event.preventDefault();
                logsshort.style.display = 'none';
                logslong.style.display = 'block';
            });
        }
        return false;
    };

    return {
        'initStartBackup': initStartBackup,
        'initStartDryRun': initStartDryRun,
        'initStartRestore': initStartRestore,
        'initCollapseExpandBackupLogs': initCollapseExpandBackupLogs
    };
});