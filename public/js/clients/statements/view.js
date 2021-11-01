/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!*************************************************!*\
  !*** ./resources/js/clients/statements/view.js ***!
  \*************************************************/
function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } }

function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); return Constructor; }

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */
var Statement = /*#__PURE__*/function () {
  function Statement() {
    _classCallCheck(this, Statement);

    this.url = new URL(document.querySelector('meta[name=pdf-url]').content);
    this.startDate = '';
    this.endDate = '';
    this.showPaymentsTable = false;
    this.showAgingTable = false;
  }

  _createClass(Statement, [{
    key: "bindEventListeners",
    value: function bindEventListeners() {
      var _this = this;

      ['#date-from', '#date-to', '#show-payments-table', '#show-aging-table'].forEach(function (selector) {
        document.querySelector(selector).addEventListener('change', function (event) {
          return _this.handleValueChange(event);
        });
      });
    }
  }, {
    key: "handleValueChange",
    value: function handleValueChange(event) {
      if (event.target.type === 'checkbox') {
        this[event.target.dataset.field] = event.target.checked;
      } else {
        this[event.target.dataset.field] = event.target.value;
      }

      this.updatePdf();
    }
  }, {
    key: "composedUrl",
    get: function get() {
      this.url.search = '';

      if (this.startDate.length > 0) {
        this.url.searchParams.append('start_date', this.startDate);
      }

      if (this.endDate.length > 0) {
        this.url.searchParams.append('end_date', this.endDate);
      }

      this.url.searchParams.append('show_payments_table', +this.showPaymentsTable);
      this.url.searchParams.append('show_aging_table', +this.showAgingTable);
      return this.url.href;
    }
  }, {
    key: "updatePdf",
    value: function updatePdf() {
      document.querySelector('meta[name=pdf-url]').content = this.composedUrl;
      var iframe = document.querySelector('#pdf-iframe');

      if (iframe) {
        iframe.src = this.composedUrl;
      }

      document.querySelector('meta[name=pdf-url]').dispatchEvent(new Event('change'));
    }
  }, {
    key: "handle",
    value: function handle() {
      var _this2 = this;

      this.bindEventListeners();
      document.querySelector('#pdf-download').addEventListener('click', function () {
        var url = new URL(_this2.composedUrl);
        url.searchParams.append('download', 1);
        window.location.href = url.href;
      });
    }
  }]);

  return Statement;
}();

new Statement().handle();
/******/ })()
;