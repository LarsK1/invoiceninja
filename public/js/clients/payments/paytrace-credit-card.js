/*! For license information please see paytrace-credit-card.js.LICENSE.txt */
!function(e){var t={};function n(r){if(t[r])return t[r].exports;var o=t[r]={i:r,l:!1,exports:{}};return e[r].call(o.exports,o,o.exports,n),o.l=!0,o.exports}n.m=e,n.c=t,n.d=function(e,t,r){n.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:r})},n.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},n.t=function(e,t){if(1&t&&(e=n(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var r=Object.create(null);if(n.r(r),Object.defineProperty(r,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var o in e)n.d(r,o,function(t){return e[t]}.bind(null,o));return r},n.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return n.d(t,"a",t),t},n.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},n.p="/",n(n.s=21)}({"0Swb":function(e,t){function n(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,r.key,r)}}(new(function(){function e(){var t;!function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,e),this.clientKey=null===(t=document.querySelector("meta[name=paytrace-client-key]"))||void 0===t?void 0:t.content}var t,r,o;return t=e,(r=[{key:"creditCardStyles",get:function(){return{font_color:"#111827",border_color:"rgba(210,214,220,1)",label_color:"#111827",label_size:"12pt",background_color:"white",border_style:"solid",font_size:"15pt",height:"30px",width:"100%"}}},{key:"codeStyles",get:function(){return{font_color:"#111827",border_color:"rgba(210,214,220,1)",label_color:"#111827",label_size:"12pt",background_color:"white",border_style:"solid",font_size:"15pt",height:"30px",width:"300px"}}},{key:"expStyles",get:function(){return{font_color:"#111827",border_color:"rgba(210,214,220,1)",label_color:"#111827",label_size:"12pt",background_color:"white",border_style:"solid",font_size:"15pt",height:"30px",width:"85px",type:"dropdown"}}},{key:"updatePayTraceLabels",value:function(){window.PTPayment.getControl("securityCode").label.text(document.querySelector("meta[name=ctrans-cvv]").content),window.PTPayment.getControl("creditCard").label.text(document.querySelector("meta[name=ctrans-card_number]").content),window.PTPayment.getControl("expiration").label.text(document.querySelector("meta[name=ctrans-expires]").content)}},{key:"setupPayTrace",value:function(){return window.PTPayment.setup({styles:{code:this.codeStyles,cc:this.creditCardStyles,exp:this.expStyles},authorization:{clientKey:this.clientKey}})}},{key:"handlePaymentWithCreditCard",value:function(e){var t=this;e.target.parentElement.disabled=!0,document.getElementById("errors").hidden=!0,window.PTPayment.validate((function(n){if(n.length>=1){var r=document.getElementById("errors");return r.textContent=n[0].description,r.hidden=!1,e.target.parentElement.disabled=!1}t.ptInstance.process().then((function(e){document.getElementById("HPF_Token").value=e.message.hpf_token,document.getElementById("enc_key").value=e.message.enc_key;var t=document.querySelector('input[name="token-billing-checkbox"]:checked');t&&(document.querySelector('input[name="store_card"]').value=t.value),document.getElementById("server_response").submit()})).catch((function(e){document.getElementById("errors").textContent=JSON.stringify(e),document.getElementById("errors").hidden=!1,console.log(e)}))}))}},{key:"handlePaymentWithToken",value:function(e){e.target.parentElement.disabled=!0,document.getElementById("server_response").submit()}},{key:"handle",value:function(){var e,t=this;Array.from(document.getElementsByClassName("toggle-payment-with-token")).forEach((function(e){return e.addEventListener("click",(function(e){document.getElementById("paytrace--credit-card-container").classList.add("hidden"),document.getElementById("save-card--container").style.display="none",document.querySelector("input[name=token]").value=e.target.dataset.token}))})),null===(e=document.getElementById("toggle-payment-with-credit-card"))||void 0===e||e.addEventListener("click",(function(e){document.getElementById("paytrace--credit-card-container").classList.remove("hidden"),document.getElementById("save-card--container").style.display="grid",document.querySelector("input[name=token]").value="",t.setupPayTrace().then((function(e){t.ptInstance=e,t.updatePayTraceLabels()}))})),document.getElementById("pay-now").addEventListener("click",(function(e){return""===document.querySelector("input[name=token]").value?t.handlePaymentWithCreditCard(e):t.handlePaymentWithToken(e)}))}}])&&n(t.prototype,r),o&&n(t,o),e}())).handle()},21:function(e,t,n){e.exports=n("0Swb")}});