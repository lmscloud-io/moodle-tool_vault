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

import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import {get_string as getString, get_strings as getStrings} from 'core/str';
import Pending from 'core/pending';
import ModalForm from 'core_form/modalform';

const SELECTORS = {
    START_BACKUP: '[data-action="startbackup"]',
    START_DRYRUN: '[data-action="startdryrun"]',
    START_RESTORE: '[data-action="startrestore"]',
    RESUME_RESTORE: '[data-action="resumerestore"]',
};

/**
 * Register listener for "start backup" button
 */
export const initStartBackup = () => {
    const startBackupButton = document.querySelector(SELECTORS.START_BACKUP);
    if (!startBackupButton) {
        return;
    }
    startBackupButton.addEventListener('click', async(event) => {
        event.preventDefault();
        const pendingPromise = new Pending('tool/vault:startBackupPopup');
        const [title, tempBody, saveButtonText] = await getStrings([
            {key: 'startbackup', component: 'tool_vault'},
            {key: 'pleasewait', component: 'tool_vault'},
            {key: 'startbackup', component: 'tool_vault'}
        ]);

        const modalForm = new ModalForm({
            formClass: 'tool_vault\\form\\start_backup_form',
            modalConfig: {title, body: tempBody},
            saveButtonText: saveButtonText,
            args: {},
            returnFocus: startBackupButton,
        });
        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (c) => {
            window.location.href = c.detail;
        });
        modalForm.addEventListener(modalForm.events.LOADED, () => {
            const button = modalForm.modal.getFooter().find("[data-action='save']");
            if (button) {
                button.addClass('hidden');
                modalForm.modal.getRoot().on(ModalEvents.bodyRendered, () =>
                    setTimeout(() => button.removeClass('hidden'), 500));
            }
        });
        await modalForm.show();
        pendingPromise.resolve();
    });
};

const processRestoreButtonClick = (mainButton, title, isDryRun, isResume = false) => {
    const modalForm = new ModalForm({
        formClass: 'tool_vault\\form\\start_restore_form',
        modalConfig: {title},
        saveButtonText: title,
        args: {
            dryrun: isDryRun ? 1 : 0,
            resume: isResume ? 1 : 0,
            backupkey: mainButton.getAttribute('data-backupkey') ?? '',
            encrypted: parseInt(mainButton.getAttribute('data-encrypted')),
        },
        returnFocus: mainButton,
    });
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (c) => {
        window.location.href = c.detail;
    });
    modalForm.show().catch(Notification.exception);
};

export const initStartDryRun = (backupkey) => {
    const dryrunButton = document.querySelector(SELECTORS.START_DRYRUN + `[data-backupkey="${backupkey}"]`);
    dryrunButton?.addEventListener('click', (event) => {
        event.preventDefault();
        processRestoreButtonClick(dryrunButton, getString('startdryrun', 'tool_vault'), true);
    });
};

export const initStartRestore = (backupkey) => {
    const restoreButton = document.querySelector(SELECTORS.START_RESTORE + `[data-backupkey="${backupkey}"]`);
    restoreButton?.addEventListener('click', (event) => {
        event.preventDefault();
        processRestoreButtonClick(restoreButton, getString('startrestore', 'tool_vault'), false);
    });
};

export const initResumeRestore = (restoreid) => {
    const restoreForm = document.querySelector(SELECTORS.RESUME_RESTORE + `[data-restoreid="${restoreid}"]`);
    restoreForm?.addEventListener('click', event => {
        event.preventDefault();
        processRestoreButtonClick(restoreForm, getString('resumerestore', 'tool_vault'), false, true);
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
