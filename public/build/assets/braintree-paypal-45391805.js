<<<<<<<< HEAD:public/build/assets/braintree-paypal-45391805.js
/**
========
import{i as s,w as u}from"./wait-8f4ae121.js";/**
>>>>>>>> new_payment_flow:public/build/assets/braintree-paypal-f78ad64b.js
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
<<<<<<<< HEAD:public/build/assets/braintree-paypal-45391805.js
 */class a{initBraintreeDataCollector(){window.braintree.client.create({authorization:document.querySelector("meta[name=client-token]").content},function(e,t){window.braintree.dataCollector.create({client:t,paypal:!0},function(n,o){n||(document.querySelector("input[name=client-data]").value=o.deviceData)})})}static getPaymentDetails(){return{flow:"vault"}}static handleErrorMessage(e){let t=document.getElementById("errors");t.innerText=e,t.hidden=!1}handlePaymentWithToken(){Array.from(document.getElementsByClassName("toggle-payment-with-token")).forEach(t=>t.addEventListener("click",n=>{document.getElementById("paypal-button").classList.add("hidden"),document.getElementById("save-card--container").style.display="none",document.querySelector("input[name=token]").value=n.target.dataset.token,document.getElementById("pay-now-with-token").classList.remove("hidden"),document.getElementById("pay-now").classList.add("hidden")}));let e=document.getElementById("pay-now-with-token");e.addEventListener("click",t=>{e.disabled=!0,e.querySelector("svg").classList.remove("hidden"),e.querySelector("span").classList.add("hidden"),document.getElementById("server-response").submit()})}handle(){this.initBraintreeDataCollector(),this.handlePaymentWithToken(),braintree.client.create({authorization:document.querySelector("meta[name=client-token]").content}).then(function(e){return braintree.paypalCheckout.create({client:e})}).then(function(e){return e.loadPayPalSDK({vault:!0}).then(function(t){return paypal.Buttons({fundingSource:paypal.FUNDING.PAYPAL,createBillingAgreement:function(){return t.createPayment(a.getPaymentDetails())},onApprove:function(n,o){return t.tokenizePayment(n).then(function(i){let r=document.querySelector('input[name="token-billing-checkbox"]:checked');r&&(document.querySelector('input[name="store_card"]').value=r.value),document.querySelector("input[name=gateway_response]").value=JSON.stringify(i),document.getElementById("server-response").submit()})},onCancel:function(n){},onError:function(n){console.log(n.message),a.handleErrorMessage(n.message)}}).render("#paypal-button")})}).catch(function(e){console.log(e.message),a.handleErrorMessage(e.message)})}}new a().handle();
========
 */class a{initBraintreeDataCollector(){window.braintree.client.create({authorization:document.querySelector("meta[name=client-token]").content},function(e,t){window.braintree.dataCollector.create({client:t,paypal:!0},function(n,o){n||(document.querySelector("input[name=client-data]").value=o.deviceData)})})}static getPaymentDetails(){return{flow:"vault"}}static handleErrorMessage(e){let t=document.getElementById("errors");t.innerText=e,t.hidden=!1}handlePaymentWithToken(){Array.from(document.getElementsByClassName("toggle-payment-with-token")).forEach(t=>t.addEventListener("click",n=>{document.getElementById("paypal-button").classList.add("hidden"),document.getElementById("save-card--container").style.display="none",document.querySelector("input[name=token]").value=n.target.dataset.token,document.getElementById("pay-now-with-token").classList.remove("hidden"),document.getElementById("pay-now").classList.add("hidden")}));let e=document.getElementById("pay-now-with-token");e.addEventListener("click",t=>{e.disabled=!0,e.querySelector("svg").classList.remove("hidden"),e.querySelector("span").classList.add("hidden"),document.getElementById("server-response").submit()})}handle(){this.initBraintreeDataCollector(),this.handlePaymentWithToken(),braintree.client.create({authorization:document.querySelector("meta[name=client-token]").content}).then(function(e){return braintree.paypalCheckout.create({client:e})}).then(function(e){return e.loadPayPalSDK({vault:!0}).then(function(t){return paypal.Buttons({fundingSource:paypal.FUNDING.PAYPAL,createBillingAgreement:function(){return t.createPayment(a.getPaymentDetails())},onApprove:function(n,o){return t.tokenizePayment(n).then(function(d){var i,c;(i=document.querySelector("#paypal-button"))==null||i.classList.add("hidden"),(c=document.querySelector("#paypal-spinner"))==null||c.classList.remove("hidden");let r=document.querySelector('input[name="token-billing-checkbox"]:checked');r&&(document.querySelector('input[name="store_card"]').value=r.value),document.querySelector("input[name=gateway_response]").value=JSON.stringify(d),document.getElementById("server-response").submit()})},onCancel:function(n){},onError:function(n){console.log(n.message),a.handleErrorMessage(n.message)}}).render("#paypal-button")})}).catch(function(e){console.log(e.message),a.handleErrorMessage(e.message)})}}function l(){new a().handle()}s()?l():u("#braintree-paypal-payment").then(()=>l());
>>>>>>>> new_payment_flow:public/build/assets/braintree-paypal-f78ad64b.js
