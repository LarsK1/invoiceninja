import{i as l,w as c}from"./wait-8f4ae121.js";/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */function i(){var o;window.braintree.client.create({authorization:(o=document.querySelector('meta[name="client-token"]'))==null?void 0:o.content}).then(function(t){return braintree.usBankAccount.create({client:t})}).then(function(t){var a;(a=document.getElementById("authorize-bank-account"))==null||a.addEventListener("click",r=>{r.target.parentElement.disabled=!0,document.getElementById("errors").hidden=!0,document.getElementById("errors").textContent="";let n={accountNumber:document.getElementById("account-number").value,routingNumber:document.getElementById("routing-number").value,accountType:document.querySelector('input[name="account-type"]:checked').value,ownershipType:document.querySelector('input[name="ownership-type"]:checked').value,billingAddress:{streetAddress:document.getElementById("billing-street-address").value,extendedAddress:document.getElementById("billing-extended-address").value,locality:document.getElementById("billing-locality").value,region:document.getElementById("billing-region").value,postalCode:document.getElementById("billing-postal-code").value}};if(n.ownershipType==="personal"){let e=document.getElementById("account-holder-name").value.split(" ",2);n.firstName=e[0],n.lastName=e[1]}else n.businessName=document.getElementById("account-holder-name").value;t.tokenize({bankDetails:n,mandateText:'By clicking ["Checkout"], I authorize Braintree, a service of PayPal, on behalf of [your business name here] (i) to verify my bank account information using bank information and consumer reports and (ii) to debit my bank account.'}).then(function(e){document.querySelector("input[name=nonce]").value=e.nonce,document.getElementById("server_response").submit()}).catch(function(e){r.target.parentElement.disabled=!1,document.getElementById("errors").textContent=`${e.details.originalError.message} ${e.details.originalError.details.originalError[0].message}`,document.getElementById("errors").hidden=!1})})}).catch(function(t){document.getElementById("errors").textContent=t.message,document.getElementById("errors").hidden=!1})}l()?i():c("#braintree-ach-authorize").then(()=>i());
