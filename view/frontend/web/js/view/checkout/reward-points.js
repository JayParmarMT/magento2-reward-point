/**
 * Reward Points Checkout Component (KnockoutJS / uiComponent)
 *
 * Registered via checkout_index_index.xml under billing-step > payment > beforeMethods.
 * Mirrors the pattern used by Magento_SalesRule/js/view/payment/discount.
 */
define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/totals',
    'Magento_Checkout/js/action/get-totals',
    'mage/translate'
], function ($, ko, Component, totals, getTotalsAction, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Meetanshi_RewardPoints/checkout/reward-points',
            applyUrl: '',
            removeUrl: '',
            balance: 0,
            minPoints: 0,
            maxPoints: 0,
            pointsApplied: 0,
            discountAmount: 0,
            useMaxDefault: false,
            enabled: false
        },

        /**
         * @inheritdoc
         */
        initialize: function () {
            this._super();

            // Pull server-side data from window.checkoutConfig.rewardPoints
            var cfg = window.checkoutConfig && window.checkoutConfig.rewardPoints
                ? window.checkoutConfig.rewardPoints
                : {};

            this.enabled        = cfg.enabled        !== undefined ? cfg.enabled        : false;
            this.applyUrl       = cfg.applyUrl        || '';
            this.removeUrl      = cfg.removeUrl       || '';
            this.balance        = cfg.balance         !== undefined ? parseInt(cfg.balance, 10)        : 0;
            this.minPoints      = cfg.minPoints       !== undefined ? parseInt(cfg.minPoints, 10)      : 0;
            this.maxPoints      = cfg.maxPoints       !== undefined ? parseInt(cfg.maxPoints, 10)      : 0;
            this.useMaxDefault  = cfg.useMaxDefault   !== undefined ? !!cfg.useMaxDefault              : false;
            this.pointsApplied  = cfg.pointsApplied   !== undefined ? parseInt(cfg.pointsApplied, 10)  : 0;
            this.discountAmount = cfg.discountAmount  !== undefined ? parseFloat(cfg.discountAmount)   : 0;

            var initialPoints = this.pointsApplied > 0
                ? this.pointsApplied
                : (this.useMaxDefault ? this.maxPoints : this.minPoints);

            this.pointsToUse           = ko.observable(initialPoints);
            this.isApplied             = ko.observable(this.pointsApplied > 0);
            this.isLoading             = ko.observable(false);
            this.message               = ko.observable('');
            this.messageType           = ko.observable('');
            this.currentDiscountAmount = ko.observable(this.discountAmount);

            return this;
        },

        /**
         * Whether the block should be visible at all.
         * Called from the outer <!-- ko if --> in the template.
         *
         * @return {boolean}
         */
        isVisible: function () {
            return this.enabled && this.balance > 0 && this.balance >= this.minPoints;
        },

        /**
         * Apply reward points to the quote via AJAX
         */
        applyPoints: function () {
            var self = this;
            var points = parseInt(self.pointsToUse(), 10);

            if (isNaN(points) || points < self.minPoints) {
                self._showMessage(
                    $t('Please enter at least %1 points.').replace('%1', self.minPoints),
                    'error'
                );

                return;
            }

            if (points > self.maxPoints) {
                points = self.maxPoints;
                self.pointsToUse(points);
            }

            self.isLoading(true);

            $.ajax({
                url: self.applyUrl,
                type: 'POST',
                data: {
                    points: points,
                    form_key: $.mage.cookies.get('form_key')
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        self.isApplied(true);
                        self.currentDiscountAmount(response.discount_amount);
                        self._showMessage(response.message, 'success');
                        self._refreshTotals();
                    } else {
                        self._showMessage(response.message, 'error');
                    }
                },
                error: function () {
                    self._showMessage($t('An error occurred. Please try again.'), 'error');
                },
                complete: function () {
                    self.isLoading(false);
                }
            });
        },

        /**
         * Remove reward points from the quote via AJAX
         */
        removePoints: function () {
            var self = this;

            self.isLoading(true);

            $.ajax({
                url: self.removeUrl,
                type: 'POST',
                data: {
                    form_key: $.mage.cookies.get('form_key')
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        self.isApplied(false);
                        self.currentDiscountAmount(0);
                        self.pointsToUse(self.minPoints);
                        self._showMessage(response.message, 'success');
                        self._refreshTotals();
                    } else {
                        self._showMessage(response.message, 'error');
                    }
                },
                error: function () {
                    self._showMessage($t('An error occurred. Please try again.'), 'error');
                },
                complete: function () {
                    self.isLoading(false);
                }
            });
        },

        /**
         * Refresh checkout totals sidebar without page reload
         */
        _refreshTotals: function () {
            var deferred = $.Deferred();

            getTotalsAction([], deferred);
        },

        /**
         * Set observable message state
         *
         * @param {string} msg
         * @param {string} type  'success' | 'error' | 'notice'
         */
        _showMessage: function (msg, type) {
            this.message(msg);
            this.messageType(type);
        }
    });
});
