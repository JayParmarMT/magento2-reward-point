/**
 * Reward Points Cart jQuery Widget
 *
 * Handles the slider/input sync, apply, and remove actions.
 * On success: invalidates cart sections so Magento re-fetches totals,
 * then does a targeted page reload so the totals table re-renders
 * with the persisted reward_points_discount value.
 */
define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'mage/translate'
], function ($, customerData, $t) {
    'use strict';

    $.widget('meetanshi.rewardPoints', {

        options: {
            applyUrl: '',
            removeUrl: '',
            balance: 0,
            minPoints: 0,
            maxPoints: 0,
            pointsApplied: 0,
            discountAmount: 0,
            useMaxDefault: false
        },

        /** @type {jQuery} */
        _slider: null,
        /** @type {jQuery} */
        _input: null,
        /** @type {jQuery} */
        _maxCheckbox: null,
        /** @type {jQuery} */
        _applyBtn: null,
        /** @type {jQuery} */
        _removeBtn: null,
        /** @type {jQuery} */
        _discountPreview: null,
        /** @type {jQuery} */
        _messages: null,

        /**
         * Widget constructor
         */
        _create: function () {
            this._slider          = this.element.find('#reward-points-slider');
            this._input           = this.element.find('#reward-points-amount');
            this._maxCheckbox     = this.element.find('#reward-points-use-max');
            this._applyBtn        = this.element.find('.reward-points-apply-btn');
            this._removeBtn       = this.element.find('.reward-points-remove-btn');
            this._discountPreview = this.element.find('.reward-points-discount-preview');
            this._messages        = this.element.find('.reward-points-messages');

            this._bind();

            if (this.options.useMaxDefault && !this.options.pointsApplied) {
                this._setPoints(this.options.maxPoints);
            }
        },

        /**
         * Bind all DOM events
         */
        _bind: function () {
            var self = this;

            this._slider.on('input change', function () {
                self._setPoints(parseInt($(this).val(), 10) || self.options.minPoints);
                self._maxCheckbox.prop('checked', parseInt($(this).val(), 10) >= self.options.maxPoints);
            });

            this._input.on('input change', function () {
                var val = Math.min(
                    Math.max(parseInt($(this).val(), 10) || self.options.minPoints, self.options.minPoints),
                    self.options.maxPoints
                );

                self._setPoints(val);
                self._maxCheckbox.prop('checked', val >= self.options.maxPoints);
            });

            this._maxCheckbox.on('change', function () {
                if ($(this).is(':checked')) {
                    self._setPoints(self.options.maxPoints);
                }
            });

            this._applyBtn.on('click', function () {
                self._applyPoints();
            });

            this._removeBtn.on('click', function () {
                self._removePoints();
            });
        },

        /**
         * Sync slider and input to a given points value
         *
         * @param {number} points
         */
        _setPoints: function (points) {
            this._slider.val(points);
            this._input.val(points);
        },

        /**
         * Get current points value from input
         *
         * @returns {number}
         */
        _getPoints: function () {
            return parseInt(this._input.val(), 10) || this.options.minPoints;
        },

        /**
         * Send apply request to server
         */
        _applyPoints: function () {
            var self = this;
            var points = self._getPoints();

            if (points < self.options.minPoints) {
                self._showMessage(
                    $t('Please enter at least %1 points.').replace('%1', self.options.minPoints),
                    'error'
                );

                return;
            }

            if (points > self.options.maxPoints) {
                points = self.options.maxPoints;
                self._setPoints(points);
            }

            self._setLoading(true);

            $.ajax({
                url: self.options.applyUrl,
                type: 'POST',
                data: {
                    points: points,
                    form_key: $.mage.cookies.get('form_key')
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        self._showMessage(response.message, 'success');
                        self._reloadPage();
                    } else {
                        self._showMessage(response.message, 'error');
                        self._setLoading(false);
                    }
                },
                error: function () {
                    self._showMessage($t('An error occurred. Please try again.'), 'error');
                    self._setLoading(false);
                }
            });
        },

        /**
         * Send remove request to server
         */
        _removePoints: function () {
            var self = this;

            self._setLoading(true);

            $.ajax({
                url: self.options.removeUrl,
                type: 'POST',
                data: {
                    form_key: $.mage.cookies.get('form_key')
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        self._showMessage(response.message, 'success');
                        self._reloadPage();
                    } else {
                        self._showMessage(response.message, 'error');
                        self._setLoading(false);
                    }
                },
                error: function () {
                    self._showMessage($t('An error occurred. Please try again.'), 'error');
                    self._setLoading(false);
                }
            });
        },

        /**
         * Invalidate customer sections and reload the page so the persisted
         * quote data (reward_points_used / reward_points_discount) drives
         * a fresh collectTotals() and the KO totals components re-render.
         */
        _reloadPage: function () {
            customerData.invalidate(['cart']);
            window.location.reload();
        },

        /**
         * Show / hide loading state on buttons
         *
         * @param {boolean} state
         */
        _setLoading: function (state) {
            this._applyBtn.prop('disabled', state);

            if (this._removeBtn.length) {
                this._removeBtn.prop('disabled', state);
            }
        },

        /**
         * Display a status message inside the widget
         *
         * @param {string} msg
         * @param {string} type  'success' | 'error' | 'notice'
         */
        _showMessage: function (msg, type) {
            var cssClass = 'message message-' + type + ' ' + type;

            this._messages.html(
                '<div class="' + cssClass + '"><div>' + $('<span>').text(msg).html() + '</div></div>'
            );
        }
    });

    return $.meetanshi.rewardPoints;
});
