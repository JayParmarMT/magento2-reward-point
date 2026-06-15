/**
 * Invitation status column component.
 *
 * Renders a colour-coded badge for each invitation status value:
 *   pending    → blue
 *   signed_up  → orange
 *   completed  → green
 *   cancelled  → red
 *
 * Extends the core select column so that option labels (supplied via
 * InvitationStatus::toOptionArray()) are resolved through the standard
 * _super() path.
 */
define([
    'Magento_Ui/js/grid/columns/select'
], function (Column) {
    'use strict';

    return Column.extend({
        defaults: {
            bodyTmpl: 'ui/grid/cells/html'
        },

        /**
         * Returns a CSS class string based on the raw status value.
         *
         * @param {Object} row
         * @return {string}
         */
        getStatusClass: function (row) {
            var value = String(row[this.index]).toLowerCase();

            switch (value) {
                case 'completed':
                    return 'meetanshi-status meetanshi-status--enabled';
                case 'cancelled':
                    return 'meetanshi-status meetanshi-status--disabled';
                case 'signed_up':
                    return 'meetanshi-status meetanshi-status--other';
                case 'pending':
                default:
                    return 'meetanshi-status meetanshi-status--pending';
            }
        },

        /**
         * Returns an HTML badge wrapping the resolved option label.
         * _super() resolves the raw value to its translated label via the
         * options array provided by InvitationStatus::toOptionArray().
         *
         * @param {Object} row
         * @return {string}
         */
        getLabelUnsanitizedHtml: function (row) {
            var label    = this._super(row);
            var cssClass = this.getStatusClass(row);

            return '<span class="' + cssClass + '">' + label + '</span>';
        }
    });
});
