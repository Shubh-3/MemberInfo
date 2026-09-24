/**
 * Member Information checkout step.
 *
 * Renders a Primary Member sub-form (always) plus Spouse/Child sub-forms for
 * each visible cart item, driven by the has_spouse/has_child flags computed
 * server-side (Vendor\MemberInfo\Model\MemberInfoConfigProvider) from the
 * quote item's actual selected options -- never from client state alone.
 *
 * Entered values live only in ko.observables tied to this component's
 * lifecycle; nothing here touches localStorage/sessionStorage, and nothing is
 * logged to the console (Req. 5).
 */
define([
    'jquery',
    'ko',
    'uiComponent',
    'underscore',
    'mage/translate',
    'mage/validation',
    'Magento_Checkout/js/model/step-navigator',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/resource-url-manager',
    'Magento_Checkout/js/model/url-builder',
    'Magento_Ui/js/model/messageList',
    'mage/storage'
], function (
    $,
    ko,
    Component,
    _,
    $t,
    validationModule,
    stepNavigator,
    quote,
    resourceUrlManager,
    urlBuilder,
    messageList,
    storage
) {
    'use strict';

    // mage/validation is required for its side effect of registering
    // $.validator; validationModule itself is not referenced directly.
    void validationModule;

    $.validator.addMethod(
        'validate-ssn',
        function (value) {
            return /^\d{3}-?\d{2}-?\d{4}$/.test(value);
        },
        $t('Please enter a valid SSN (e.g. 123-45-6789).')
    );

    $.validator.addMethod(
        'validate-not-future-date',
        function (value) {
            if (!value) {
                return true;
            }
            return new Date(value) <= new Date();
        },
        $t('Date of birth cannot be in the future.')
    );

    /**
     * @return {Object} A fresh set of observable fields for one member.
     */
    function createMemberFields() {
        return {
            firstName: ko.observable(''),
            lastName: ko.observable(''),
            dob: ko.observable(''),
            ssn: ko.observable('')
        };
    }

    return Component.extend({
        defaults: {
            template: 'Vendor_MemberInfo/checkout/member-information'
        },

        isVisible: ko.observable(true),
        itemForms: ko.observableArray([]),
        isSaving: ko.observable(false),

        /** @inheritdoc */
        initialize: function () {
            this._super();

            stepNavigator.registerStep(
                'member-information',
                null,
                $t('Member Information'),
                this.isVisible,
                _.bind(this.navigate, this),
                1.5
            );

            this.buildItemForms();

            return this;
        },

        /**
         * Builds one form entry per cart item that carries member-info flags,
         * seeded fresh (never pre-filled from the server) each time the
         * checkout page loads.
         */
        buildItemForms: function () {
            var items = window.checkoutConfig.memberInfoItems || {},
                forms = [];

            _.each(items, function (flags, itemId) {
                var itemForm = {
                    itemId: parseInt(itemId, 10),
                    productName: flags.product_name,
                    hasSpouse: ko.observable(!!flags.has_spouse),
                    hasChild: ko.observable(!!flags.has_child),
                    primary: createMemberFields(),
                    spouse: createMemberFields(),
                    child: createMemberFields()
                };

                forms.push(itemForm);
            });

            this.itemForms(forms);
            this.bindDeselectClearing();
        },

        /**
         * Clears the observables for a member type. Called whenever a
         * sub-form's flag goes false, so half-entered data for a
         * deselected member is never held in memory to be saved later.
         *
         * @param {Object} itemForm
         * @param {String} memberType - 'spouse' | 'child'
         */
        clearMemberFields: function (itemForm, memberType) {
            var fields = itemForm[memberType];
            fields.firstName('');
            fields.lastName('');
            fields.dob('');
            fields.ssn('');
        },

        /**
         * Subscribes each item's hasSpouse/hasChild observables so that the
         * moment either flips to false (e.g. re-derived after a cart change),
         * any data already typed into that sub-form is wiped immediately --
         * it can never be picked up by submitStep()/saveItem() afterwards.
         */
        bindDeselectClearing: function () {
            var self = this;

            _.each(this.itemForms(), function (itemForm) {
                itemForm.hasSpouse.subscribe(function (enabled) {
                    if (!enabled) {
                        self.clearMemberFields(itemForm, 'spouse');
                    }
                });

                itemForm.hasChild.subscribe(function (enabled) {
                    if (!enabled) {
                        self.clearMemberFields(itemForm, 'child');
                    }
                });
            });
        },

        /**
         * Navigation handler required by stepNavigator.
         */
        navigate: function () {
            this.isVisible(true);
        },

        /**
         * Validates all visible sub-forms on the page, then POSTs each
         * item's member data to the Web API before advancing to the next
         * step. The server independently re-validates and re-checks
         * has_spouse/has_child before persisting anything (defense in
         * depth -- see MemberInfoManagement::saveForCartItem).
         */
        submitStep: function () {
            var self = this,
                form = $('#member-information-form');

            if (!form.validation() || !form.validation('isValid')) {
                return false;
            }

            self.isSaving(true);

            return $.when.apply($, self.itemForms().map(function (itemForm) {
                return self.saveItem(itemForm);
            })).done(function () {
                self.isSaving(false);
                stepNavigator.next();
            }).fail(function () {
                self.isSaving(false);
            });
        },

        /**
         * @param {Object} itemForm
         * @return {*} jQuery promise
         */
        saveItem: function (itemForm) {
            var members = [this.toPayload('primary', itemForm.primary)];

            if (itemForm.hasSpouse()) {
                members.push(this.toPayload('spouse', itemForm.spouse));
            }

            if (itemForm.hasChild()) {
                members.push(this.toPayload('child', itemForm.child));
            }

            return storage.post(
                this.getSaveUrl(itemForm.itemId),
                JSON.stringify({ members: members }),
                false
            ).fail(function (response) {
                var error = JSON.parse(response.responseText || '{}');
                messageList.addErrorMessage({
                    message: error.message || $t('Unable to save member information. Please try again.')
                });
            });
        },

        /**
         * Builds the correct save endpoint for the current checkout method
         * -- masked cart ID against /guest-carts/:cartId/... for guests,
         * /carts/mine/... for logged-in customers -- mirroring
         * Magento_Checkout/js/model/resource-url-manager's own pattern.
         *
         * @param {Number} itemId
         * @return {String}
         */
        getSaveUrl: function (itemId) {
            var isGuest = resourceUrlManager.getCheckoutMethod() === 'guest',
                urls = {
                    guest: '/guest-carts/:cartId/member-information/' + itemId,
                    customer: '/carts/mine/member-information/' + itemId
                },
                params = isGuest ? { cartId: quote.getQuoteId() } : {};

            return urlBuilder.createUrl(urls[isGuest ? 'guest' : 'customer'], params);
        },

        /**
         * @param {String} memberType
         * @param {Object} fields
         * @return {Object}
         */
        toPayload: function (memberType, fields) {
            return {
                member_type: memberType,
                first_name: fields.firstName(),
                last_name: fields.lastName(),
                dob: fields.dob(),
                ssn: fields.ssn()
            };
        }
    });
});
