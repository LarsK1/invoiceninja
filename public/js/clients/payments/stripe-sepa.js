/*! For license information please see stripe-sofort.js.LICENSE.txt */
!function(e){var t={};function n(r){if(t[r])return t[r].exports;var o=t[r]={i:r,l:!1,exports:{}};return e[r].call(o.exports,o,o.exports,n),o.l=!0,o.exports}n.m=e,n.c=t,n.d=function(e,t,r){n.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:r})},n.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},n.t=function(e,t){if(1&t&&(e=n(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var r=Object.create(null);if(n.r(r),Object.defineProperty(r,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var o in e)n.d(r,o,function(t){return e[t]}.bind(null,o));return r},n.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return n.d(t,"a",t),t},n.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},n.p="/",n(n.s=6)}({6:function(e,t,n){e.exports=n("RFiP")},RFiP:function(e,t){var n,r,o,u;function i(e,t,n){return t in e?Object.defineProperty(e,t,{value:n,enumerable:!0,configurable:!0,writable:!0}):e[t]=n,e}var c=null!==(n=null===(r=document.querySelector('meta[name="stripe-publishable-key"]'))||void 0===r?void 0:r.content)&&void 0!==n?n:"",l=null!==(o=null===(u=document.querySelector('meta[name="stripe-account-id"]'))||void 0===u?void 0:u.content)&&void 0!==o?o:"";new function e(t,n){var r=this;!function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,e),i(this,"setupStripe",(function(){return r.stripe=Stripe(r.key),r.stripeConnect&&(r.stripe.stripeAccount=l),r})),i(this,"handle",(function(){document.getElementById("pay-now").addEventListener("click",(function(e){document.getElementById("pay-now").disabled=!0,document.querySelector("#pay-now > svg").classList.remove("hidden"),document.querySelector("#pay-now > span").classList.add("hidden"),r.stripe.confirmSepaDebitPayment(document.querySelector("meta[name=pi-client-secret").content,{payment_method:{sepa_debit:{country:document.querySelector('meta[name="country"]').content}},return_url:document.querySelector('meta[name="return-url"]').content})}))})),this.key=t,this.errors=document.getElementById("errors"),this.stripeConnect=n}(c,l).setupStripe().handle()}});
