/**
 * Custom status column component that renders Enabled/Disabled with coloured badges.
 *
 * Uses the core html.html body template and overrides getLabelUnsanitizedHtml()
 * to return a <span> badge with the appropriate CSS class.
 *
 * Green  — value 1 / "active" / "enabled"
 * Red    — value 0 / "disabled"
 * Orange — any other value (e.g. "expired", "cancelled")
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
         * Returns a CSS class string based on the raw field value.
         *
         * @param {Object} row
         * @return {string}
         */
        getStatusClass: function (row) {
            var value = String(row[this.index]).toLowerCase();

            if (value === '1' || value === 'active' || value === 'enabled') {
                return 'meetanshi-status meetanshi-status--enabled';
            }

            if (value === '0' || value === 'disabled') {
                return 'meetanshi-status meetanshi-status--disabled';
            }

            return 'meetanshi-status meetanshi-status--other';
        },

        /**
         * Returns HTML badge string. Called by the html.html body template.
         *
         * @param {Object} row
         * @return {string}
         */
        getLabelUnsanitizedHtml: function (row) {
            var label = this._super(row);
            var cssClass = this.getStatusClass(row);

            return '<span class="' + cssClass + '">' + label + '</span>';
        }
    });
});
