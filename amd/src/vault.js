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

import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';
import Notification from 'core/notification';
import {get_string as getString, get_strings as getStrings} from 'core/str';
import Pending from 'core/pending';
import Fragment from 'core/fragment';

const SELECTORS = {
    START_BACKUP: 'form[data-action="startbackup"]',
    START_DRYRUN: 'form[data-action="startdryrun"]',
    START_RESTORE: 'form[data-action="startrestore"]',
};

const submitForm = (backupForm, modal) => {
    const popupBody = modal.getBody()[0];
    for (let i of ['passphrase', 'description']) {
        const el1 = popupBody.querySelector(`input[name="${i}"]`),
            el2 = backupForm.querySelector(`input[name="${i}"]`);
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
const loadFragmentWithPopup = async(title, tempBody, fragmentName, contextid, args = {}) => {
    let activeModal = null;
    let fragment = null;
    try {
        activeModal = await ModalFactory.create({
            type: ModalFactory.types.CANCEL,
            title,
            body: tempBody
        });
        activeModal.show();
        fragment = await Fragment.loadFragment('tool_vault', fragmentName, contextid, args);
        activeModal.destroy();
    } catch (e) {
        if (activeModal) {
            activeModal.setBody(e.message);
        } else {
            Notification.exception(e);
        }
    }
    return fragment;
};

/**
 * Register listener for "start backup" button
 */
export const initStartBackup = () => {
    const backupForm = document.querySelector(SELECTORS.START_BACKUP);
    if (!backupForm) {
        return;
    }
    const contextid = backupForm.getAttribute('data-contextid');
    backupForm.addEventListener('submit', async(event) => {
        event.preventDefault();
        const pendingPromise = new Pending('tool/vault:startBackupPopup');

        const [title, tempBody, saveButtonText] = await getStrings([
            {key: 'startbackup', component: 'tool_vault'},
            {key: 'pleasewait', component: 'tool_vault'},
            {key: 'startbackup', component: 'tool_vault'}
        ]);
        const fragment = await loadFragmentWithPopup(title, tempBody, 'start_backup', contextid);

        if (!fragment) {
            pendingPromise.resolve();
            return;
        }

        const modal = await ModalFactory.create({
            type: ModalFactory.types.SAVE_CANCEL,
            title,
            body: fragment,
            buttons: {save: saveButtonText},
            removeOnClose: true
        });
        modal.show();

        modal.getRoot().on(ModalEvents.save, () => submitForm(backupForm, modal));
        modal.getRoot().on(ModalEvents.cancel, () => modal.hide());
        pendingPromise.resolve();
    });
};

export const initStartDryRun = (backupkey) => {
    const dryrunForm = document.querySelector(SELECTORS.START_DRYRUN + `[data-backupkey="${backupkey}"]`);
    if (!dryrunForm) {
        return;
    }
    dryrunForm.addEventListener('submit', event => {
        event.preventDefault();
        ModalFactory.create({
            type: ModalFactory.types.SAVE_CANCEL,
            title: getString('startdryrun', 'tool_vault'),
            body: Templates.render('tool_vault/start_restore_popup',
                {dryrun: 1, encrypted: parseInt(dryrunForm.getAttribute('data-encrypted'))}),
            buttons: {save: getString('startdryrun', 'tool_vault')},
            removeOnClose: true
        })
            .then(function(modal) {
                modal.show();

                modal.getRoot().on(ModalEvents.save, () => submitForm(dryrunForm, modal));
                modal.getRoot().on(ModalEvents.cancel, () => modal.hide());

                return modal;
            })
            .catch(Notification.exception);
    });
};

export const initStartRestore = (backupkey) => {
    const restoreForm = document.querySelector(SELECTORS.START_RESTORE + `[data-backupkey="${backupkey}"]`);
    if (!restoreForm) {
        return;
    }
    restoreForm.addEventListener('submit', event => {
        event.preventDefault();
        ModalFactory.create({
            type: ModalFactory.types.SAVE_CANCEL,
            title: getString('startrestore', 'tool_vault'),
            body: Templates.render('tool_vault/start_restore_popup',
                {dryrun: 0, encrypted: parseInt(restoreForm.getAttribute('data-encrypted'))}),
            buttons: {save: getString('startrestore', 'tool_vault')},
            removeOnClose: true
        })
            .then(function(modal) {
                modal.show();

                modal.getRoot().on(ModalEvents.save, () => submitForm(restoreForm, modal));
                modal.getRoot().on(ModalEvents.cancel, () => modal.hide());

                return modal;
            })
            .catch(Notification.exception);
    });
};

export const initCollapseExpandBackupLogs = () => {
    const logslong = document.querySelector(`[data-vault-purpose="logslong"]`);
    const logsshort = document.querySelector(`[data-vault-purpose="logsshort"]`);
    if (logslong && logsshort) {
        logslong.querySelector(`[data-vault-purpose="togglelogs"]`).addEventListener('click', event => {
            event.preventDefault();
            logsshort.style.display = 'block';
            logslong.style.display = 'none';
        });
        logsshort.querySelector(`[data-vault-purpose="togglelogs"]`).addEventListener('click', event => {
            event.preventDefault();
            logsshort.style.display = 'none';
            logslong.style.display = 'block';
        });
    }
    return false;
};
