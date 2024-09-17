import{i as s,w as c}from"./wait-8f4ae121.js";/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */function i(){const t=document.querySelector("meta[name=public_key]"),n=document.querySelector("meta[name=gateway_id]"),o=document.querySelector("meta[name=environment]"),e=new cba.HtmlWidget("#widget",t==null?void 0:t.content,n==null?void 0:n.content);e.setEnv(o==null?void 0:o.content),e.useAutoResize(),e.interceptSubmitForm("#stepone"),e.onFinishInsert('#server-response input[name="gateway_response"]',"payment_source"),e.setFormFields(["card_name*"]),e.reload();let r=document.getElementById("pay-now");return r.disabled=!1,r.querySelector("svg").classList.add("hidden"),r.querySelector("span").classList.remove("hidden"),document.querySelector('#server-response input[name="gateway_response"]').value="",e}function u(){var t,n,o;(t=document.querySelector("#widget"))==null||t.replaceChildren(),(n=document.querySelector("#widget"))==null||n.classList.remove("hidden"),(o=document.querySelector("#widget-3dsecure"))==null||o.replaceChildren()}function a(){u();const t=i();t.on("finish",()=>{document.getElementById("errors").hidden=!0,l()}),t.on("submit",function(e){document.getElementById("errors").hidden=!0});let n=document.getElementById("pay-now");n.addEventListener("click",()=>{const e=document.getElementById("widget");if(t.getValidationState(),!t.isValidForm()&&e.offsetParent!==null){n.disabled=!1,n.querySelector("svg").classList.add("hidden"),n.querySelector("span").classList.remove("hidden");return}n.disabled=!0,n.querySelector("svg").classList.remove("hidden"),n.querySelector("span").classList.add("hidden");let r=document.querySelector("input[name=token-billing-checkbox]:checked");r&&(document.getElementById("store_card").value=r.value),e.offsetParent!==null?document.getElementById("stepone_submit").click():document.getElementById("server-response").submit()}),document.getElementById("toggle-payment-with-credit-card").addEventListener("click",e=>{var d;document.getElementById("widget").classList.remove("hidden"),document.getElementById("save-card--container").style.display="grid",document.querySelector("input[name=token]").value="",(d=document.querySelector("#powerboard-payment-container"))==null||d.classList.remove("hidden")}),Array.from(document.getElementsByClassName("toggle-payment-with-token")).forEach(e=>e.addEventListener("click",r=>{var d;document.getElementById("widget").classList.add("hidden"),document.getElementById("save-card--container").style.display="none",document.querySelector("input[name=token]").value=r.target.dataset.token,(d=document.querySelector("#powerboard-payment-container"))==null||d.classList.add("hidden")}));const o=document.querySelector('input[name="payment-type"]');o&&o.click()}async function l(){try{const t=await m();if(t.status==="not_authenticated"||t==="not_authenticated")throw a(),new Error("There was an issue authenticating this payment method.");if(t.status==="authentication_not_supported"){document.querySelector('input[name="browser_details"]').value=null,document.querySelector('input[name="charge"]').value=JSON.stringify(t);let e=document.querySelector("input[name=token-billing-checkbox]:checked");return e&&(document.getElementById("store_card").value=e.value),document.getElementById("server-response").submit()}const n=new cba.Canvas3ds("#widget-3dsecure",t._3ds.token);n.load(),document.getElementById("widget").classList.add("hidden"),n.on("chargeAuthSuccess",function(e){document.querySelector('input[name="browser_details"]').value=null,document.querySelector('input[name="charge"]').value=JSON.stringify(e);let r=document.querySelector("input[name=token-billing-checkbox]:checked");r&&(document.getElementById("store_card").value=r.value),document.getElementById("server-response").submit()}),n.on("chargeAuthReject",function(e){document.getElementById("errors").textContent="Sorry, your transaction could not be processed...",document.getElementById("errors").hidden=!1,a()}),n.load()}catch(t){console.error("Error fetching 3DS Token:",t),document.getElementById("errors").textContent=`Sorry, your transaction could not be processed...

${t}`,document.getElementById("errors").hidden=!1,a()}}async function m(){const t={name:navigator.userAgent.substring(0,100),java_enabled:navigator.javaEnabled()?"true":"false",language:navigator.language||navigator.userLanguage,screen_height:window.screen.height.toString(),screen_width:window.screen.width.toString(),time_zone:(new Date().getTimezoneOffset()*-1).toString(),color_depth:window.screen.colorDepth.toString()};document.querySelector('input[name="browser_details"]').value=JSON.stringify(t);const n=JSON.stringify(Object.fromEntries(new FormData(document.getElementById("server-response")))),o=document.querySelector("meta[name=payments_route]");try{const e=await fetch(o.content,{method:"POST",headers:{"Content-Type":"application/json","X-Requested-With":"XMLHttpRequest",Accept:"application/json","X-CSRF-Token":document.querySelector('meta[name="csrf-token"]').content},body:n});return e.ok?await e.json():await e.json().then(r=>{throw new Error(r.message??"Unknown error.")})}catch(e){document.getElementById("errors").textContent=`Sorry, your transaction could not be processed...

${e.message}`,document.getElementById("errors").hidden=!1,console.error("Fetch error:",e),a()}}s()?a():c("#powerboard-credit-card-payment").then(()=>a());
