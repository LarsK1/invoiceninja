import{i as l,w as m}from"./wait-8f4ae121.js";/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */let i=!1;function y(){const n=document.querySelector("meta[name=public_key]"),t=document.querySelector("meta[name=gateway_id]"),r=document.querySelector("meta[name=environment]"),e=new cba.HtmlWidget("#widget",n==null?void 0:n.content,t==null?void 0:t.content);e.setEnv(r==null?void 0:r.content),e.useAutoResize(),e.interceptSubmitForm("#stepone"),e.onFinishInsert('#server-response input[name="gateway_response"]',"payment_source"),e.setFormFields(["card_name*"]),e.reload();let o=document.getElementById("pay-now");return o.disabled=!1,o.querySelector("svg").classList.add("hidden"),o.querySelector("span").classList.remove("hidden"),document.querySelector('#server-response input[name="gateway_response"]').value="",e}function g(){var n,t,r;(n=document.querySelector("#widget"))==null||n.replaceChildren(),(t=document.querySelector("#widget"))==null||t.classList.remove("hidden"),(r=document.querySelector("#widget-3dsecure"))==null||r.replaceChildren()}function a(){var o,u;if(g(),!((o=document.querySelector("meta[name=gateway_id]"))==null?void 0:o.content)){let d=document.getElementById("pay-now");d.disabled=!0,d.querySelector("svg").classList.remove("hidden"),d.querySelector("span").classList.add("hidden"),document.getElementById("errors").textContent="Gateway not found or verified",document.getElementById("errors").hidden=!1}const t=y();t.on("finish",()=>{document.getElementById("errors").hidden=!0,p()}),t.on("submit",function(d){document.getElementById("errors").hidden=!0});let r=document.getElementById("pay-now");r.addEventListener("click",()=>{const d=document.getElementById("widget");if(t.getValidationState(),!t.isValidForm()&&d.offsetParent!==null){r.disabled=!1,r.querySelector("svg").classList.add("hidden"),r.querySelector("span").classList.remove("hidden");return}r.disabled=!0,r.querySelector("svg").classList.remove("hidden"),r.querySelector("span").classList.add("hidden");let s=document.querySelector("input[name=token-billing-checkbox]:checked");s&&(document.getElementById("store_card").value=s.value),d.offsetParent!==null?document.getElementById("stepone_submit").click():document.getElementById("server-response").submit()}),document.getElementById("toggle-payment-with-credit-card").addEventListener("click",d=>{var c;document.getElementById("widget").classList.remove("hidden"),document.getElementById("save-card--container").style.display="grid",document.querySelector("input[name=token]").value="",(c=document.querySelector("#powerboard-payment-container"))==null||c.classList.remove("hidden")}),Array.from(document.getElementsByClassName("toggle-payment-with-token")).forEach(d=>d.addEventListener("click",s=>{var c;document.getElementById("widget").classList.add("hidden"),document.getElementById("save-card--container").style.display="none",document.querySelector("input[name=token]").value=s.target.dataset.token,(c=document.querySelector("#powerboard-payment-container"))==null||c.classList.add("hidden")}));const e=document.querySelector('input[name="payment-type"]');e&&e.click(),i&&((u=document.getElementById("toggle-payment-with-credit-card"))==null||u.click())}async function p(){try{const n=await h();if(!n||!n.status||n.status==="not_authenticated"||n==="not_authenticated")throw i=!0,a(),new Error("There was an issue authenticating this payment method.");if(n.status==="authentication_not_supported"){document.querySelector('input[name="browser_details"]').value=null,document.querySelector('input[name="charge"]').value=JSON.stringify(n);let e=document.querySelector("input[name=token-billing-checkbox]:checked");return e&&(document.getElementById("store_card").value=e.value),document.getElementById("server-response").submit()}const t=new cba.Canvas3ds("#widget-3dsecure",n._3ds.token);t.load(),document.getElementById("widget").classList.add("hidden"),t.on("chargeAuthSuccess",function(e){document.querySelector('input[name="browser_details"]').value=null,document.querySelector('input[name="charge"]').value=JSON.stringify(e);let o=document.querySelector("input[name=token-billing-checkbox]:checked");o&&(document.getElementById("store_card").value=o.value),document.getElementById("server-response").submit()}),t.on("chargeAuthReject",function(e){document.getElementById("errors").textContent="Sorry, your transaction could not be processed...",document.getElementById("errors").hidden=!1,i=!0,a()}),t.load()}catch(n){const t=n.message??"Unknown error.";document.getElementById("errors").textContent=`Sorry, your transaction could not be processed...

${t}`,document.getElementById("errors").hidden=!1,i=!0,a()}}async function h(){const n={name:navigator.userAgent.substring(0,100),java_enabled:navigator.javaEnabled()?"true":"false",language:navigator.language||navigator.userLanguage,screen_height:window.screen.height.toString(),screen_width:window.screen.width.toString(),time_zone:(new Date().getTimezoneOffset()*-1).toString(),color_depth:window.screen.colorDepth.toString()};document.querySelector('input[name="browser_details"]').value=JSON.stringify(n);const t=JSON.stringify(Object.fromEntries(new FormData(document.getElementById("server-response")))),r=document.querySelector("meta[name=payments_route]");try{const e=await fetch(r.content,{method:"POST",headers:{"Content-Type":"application/json","X-Requested-With":"XMLHttpRequest",Accept:"application/json","X-CSRF-Token":document.querySelector('meta[name="csrf-token"]').content},body:t});return e.ok?await e.json():await e.json().then(o=>{throw new Error(o.message??"Unknown error.")})}catch(e){document.getElementById("errors").textContent=`Sorry, your transaction could not be processed...

${e.message}`,document.getElementById("errors").hidden=!1,i=!0,a()}}l()?a():m("#powerboard-credit-card-payment").then(()=>a());
