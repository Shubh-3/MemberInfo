/**
 * Actions column for "Reveal". Tries the Reveal endpoint directly first --
 * the server checks vendor_memberinfo_access for an unexpired grant on
 * (current admin, this row) and returns the data immediately if one exists.
 * Only prompts for the admin's password (server-verified via VerifyPassword)
 * when Reveal comes back with no active grant (expired or never granted).
 * There is no bearer token to hold onto client-side: the DB row is the
 * access boundary, so this works the same whether it's the first click,
 * the tenth click, or a click right after a page refresh, as long as the
 * 15-minute window server-side hasn't lapsed. Password is entered via a
 * masked (type="password") field in Magento's own prompt widget and is
 * never logged to the console.
 */
define([
    'jquery',
    'underscore',
    'mage/translate',
    'Magento_Ui/js/grid/columns/actions',
    'Magento_Ui/js/modal/prompt',
    'Magento_Ui/js/modal/alert'
], function ($, _, $t, Actions, prompt, alert) {
    'use strict';

    return Actions.extend({
        /**
         * @inheritdoc
         */
        defaultCallback: function (actionIndex, recordId, action) {
            if (actionIndex !== 'reveal') {
                this._super(actionIndex, recordId, action);
                return;
            }

            this.tryReveal(action);
        },

        /**
         * @param {Object} action
         */
        tryReveal: function (action) {
            var self = this;

            $.ajax({
                url: action.reveal_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    row_id: action.row_id,
                    form_key: window.FORM_KEY
                }
            }).done(function (response) {
                if (response.success) {
                    self.showMembers(response.members);
                    return;
                }
                self.promptForPassword(action);
            }).fail(function () {
                self.promptForPassword(action);
            });
        },

        /**
         * @param {Object} action
         */
        promptForPassword: function (action) {
            var self = this;

            prompt({
                title: $t('Verify Password'),
                content: $t('Re-enter your admin password to view sensitive member data.'),
                attributesField: {
                    type: 'password',
                    name: 'admin_password'
                },
                actions: {
                    /**
                     * @param {String} password
                     */
                    confirm: function (password) {
                        if (!password) {
                            return;
                        }
                        self.verifyPassword(action, password);
                    }
                }
            });
        },

        /**
         * @param {Object} action
         * @param {String} password
         */
        verifyPassword: function (action, password) {
            var self = this;

            $.ajax({
                url: action.verify_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    password: password,
                    row_id: action.row_id,
                    form_key: window.FORM_KEY
                }
            }).done(function (response) {
                if (!response.success) {
                    alert({ content: response.message || $t('Incorrect password.') });
                    return;
                }
                self.tryReveal(action);
            }).fail(function () {
                alert({ content: $t('Unable to verify password. Please try again.') });
            });
        },

        /**
         * @param {Object} members
         */
        showMembers: function (members) {
            var lines = [];

            _.each(members, function (member, type) {
                lines.push(
                    '<strong>' + _.escape(type.toUpperCase()) + '</strong>: ' +
                    _.escape(member.first_name) + ' ' + _.escape(member.last_name) +
                    ' | DOB: ' + _.escape(member.dob) + ' | SSN: ' + _.escape(member.ssn)
                );
            });

            alert({
                title: $t('Sensitive Member Data (expires access in 15 minutes)'),
                content: lines.join('<br/>') || $t('No data.')
            });
        }
    });
});
