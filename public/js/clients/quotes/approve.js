/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!************************************************!*\
  !*** ./resources/js/clients/quotes/approve.js ***!
  \************************************************/
function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } }

function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); return Constructor; }

/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
var Approve = /*#__PURE__*/function () {
  function Approve(displaySignature, displayTerms) {
    _classCallCheck(this, Approve);

    this.shouldDisplaySignature = displaySignature;
    this.shouldDisplayTerms = displayTerms;
    this.termsAccepted = false;
  }

  _createClass(Approve, [{
    key: "submitForm",
    value: function submitForm() {
      document.getElementById('approve-form').submit();
    }
  }, {
    key: "displaySignature",
    value: function displaySignature() {
      var displaySignatureModal = document.getElementById('displaySignatureModal');
      displaySignatureModal.removeAttribute('style');
      var signaturePad = new SignaturePad(document.getElementById('signature-pad'), {
        penColor: 'rgb(0, 0, 0)'
      });
      this.signaturePad = signaturePad;
    }
  }, {
    key: "displayTerms",
    value: function displayTerms() {
      var displayTermsModal = document.getElementById("displayTermsModal");
      displayTermsModal.removeAttribute("style");
    }
  }, {
    key: "handle",
    value: function handle() {
      var _this = this;

      document.getElementById('approve-button').addEventListener('click', function () {
        if (_this.shouldDisplaySignature && _this.shouldDisplayTerms) {
          _this.displaySignature();

          document.getElementById('signature-next-step').addEventListener('click', function () {
            _this.displayTerms();

            document.getElementById('accept-terms-button').addEventListener('click', function () {
              document.querySelector('input[name="signature"').value = _this.signaturePad.toDataURL();
              _this.termsAccepted = true;

              _this.submitForm();
            });
          });
        }

        if (_this.shouldDisplaySignature && !_this.shouldDisplayTerms) {
          _this.displaySignature();

          document.getElementById('signature-next-step').addEventListener('click', function () {
            document.querySelector('input[name="signature"').value = _this.signaturePad.toDataURL();

            _this.submitForm();
          });
        }

        if (!_this.shouldDisplaySignature && _this.shouldDisplayTerms) {
          _this.displayTerms();

          document.getElementById('accept-terms-button').addEventListener('click', function () {
            _this.termsAccepted = true;

            _this.submitForm();
          });
        }

        if (!_this.shouldDisplaySignature && !_this.shouldDisplayTerms) {
          _this.submitForm();
        }
      });
    }
  }]);

  return Approve;
}();

var signature = document.querySelector('meta[name="require-quote-signature"]').content;
var terms = document.querySelector('meta[name="show-quote-terms"]').content;
new Approve(Boolean(+signature), Boolean(+terms)).handle();
/******/ })()
;