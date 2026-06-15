/**
 * Reward Points cart totals line-item component
 */
define([
    'Magento_Checkout/js/view/summary/abstract-total',
    'Magento_Checkout/js/model/totals'
], function (Component, totals) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Meetanshi_RewardPoints/cart/totals/reward-points'
        },

        /**
         * Get the reward_points total segment
         *
         * @return {Object|null}
         */
        getSegment: function () {
            return totals.getSegment('reward_points');
        },

        /**
         * Show the row only when a non-zero discount exists
         *
         * @return {Boolean}
         */
        isDisplayed: function () {
            var segment = this.getSegment();

            return segment !== null && parseFloat(segment.value) !== 0;
        },

        /**
         * Segment title (e.g. "Reward Points Discount")
         *
         * @return {String}
         */
        getTitle: function () {
            var segment = this.getSegment();

            return segment ? segment.title : '';
        },

        /**
         * Formatted negative discount value
         *
         * @return {String}
         */
        getValue: function () {
            var segment = this.getSegment();

            if (!segment) {
                return this.getFormattedPrice(0);
            }

            return this.getFormattedPrice(parseFloat(segment.value));
        }
    });
});
