/*! For license information please see payment.js.LICENSE.txt */
(()=>{function e(e,t){for(var n=0;n<t.length;n++){var i=t[n];i.enumerable=i.enumerable||!1,i.configurable=!0,"value"in i&&(i.writable=!0),Object.defineProperty(e,i.key,i)}}var t=function(){function t(e,n){!function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,t),this.shouldDisplayTerms=e,this.shouldDisplaySignature=n,this.termsAccepted=!1,this.submitting=!1}var n,i,a;return n=t,(i=[{key:"handleMethodSelect",value:function(e){var t=this;document.getElementById("signature-next-step").disabled=!0,document.getElementById("company_gateway_id").value=e.dataset.companyGatewayId,document.getElementById("payment_method_id").value=e.dataset.gatewayTypeId,this.shouldDisplaySignature&&!this.shouldDisplayTerms&&(this.displayTerms(),document.getElementById("accept-terms-button").addEventListener("click",(function(){t.termsAccepted=!0,t.submitForm()}))),!this.shouldDisplaySignature&&this.shouldDisplayTerms&&(this.displaySignature(),document.getElementById("signature-next-step").addEventListener("click",(function(){document.querySelector('input[name="signature"').value=t.signaturePad.toDataURL(),t.submitForm()}))),this.shouldDisplaySignature&&this.shouldDisplayTerms&&(this.displaySignature(),document.getElementById("signature-next-step").addEventListener("click",(function(){t.displayTerms(),document.getElementById("accept-terms-button").addEventListener("click",(function(){document.querySelector('input[name="signature"').value=t.signaturePad.toDataURL(),t.termsAccepted=!0,t.submitForm()}))}))),this.shouldDisplaySignature||this.shouldDisplayTerms||this.submitForm()}},{key:"submitForm",value:function(){this.submitting=!0,document.getElementById("payment-form").submit()}},{key:"displayTerms",value:function(){document.getElementById("displayTermsModal").removeAttribute("style")}},{key:"displaySignature",value:function(){document.getElementById("displaySignatureModal").removeAttribute("style");var e=new SignaturePad(document.getElementById("signature-pad"),{penColor:"rgb(0, 0, 0)"}).addEventListener("beginStroke",(function(){document.getElementById("signature-next-step").disabled=!1}),{once:!0});this.signaturePad=e}},{key:"handle",value:function(){var e=this;document.querySelectorAll(".dropdown-gateway-button").forEach((function(t){t.addEventListener("click",(function(){e.submitting||e.handleMethodSelect(t)}))}))}}])&&e(n.prototype,i),a&&e(n,a),t}(),n=document.querySelector('meta[name="require-invoice-signature"]').content,i=document.querySelector('meta[name="show-invoice-terms"]').content;new t(Boolean(+n),Boolean(+i)).handle()})();