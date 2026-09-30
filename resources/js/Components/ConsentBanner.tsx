import {router,usePage} from '@inertiajs/react';
import {useEffect,useState} from 'react';
export default function ConsentBanner(){
 const p:any=usePage(); const current=p.props.consent??{};
 const [visible,setVisible]=useState(!current.decided); const [details,setDetails]=useState(false); const [analytics,setAnalytics]=useState(!!current.analytics); const [marketing,setMarketing]=useState(!!current.marketing);
 useEffect(()=>{const open=()=>{setAnalytics(!!current.analytics);setMarketing(!!current.marketing);setDetails(true);setVisible(true)};window.addEventListener('raoza:cookie-preferences',open);return()=>window.removeEventListener('raoza:cookie-preferences',open)},[current.analytics,current.marketing]);
 if(!visible)return null;
 const save=(a:boolean,m:boolean)=>router.post('/consent',{analytics:a,marketing:m},{preserveScroll:true,onSuccess:()=>setVisible(false)});
 return <div className="raoza-consent" role="dialog" aria-labelledby="consent-title" aria-describedby="consent-description">
  <div className="raoza-consent-summary"><div><p id="consent-title">Your privacy choices.</p><p id="consent-description">Necessary cookies keep the store working. Analytics and marketing stay off unless you choose them. <a href="/pages/cookies">Read the cookie policy</a>.</p></div><div className="raoza-consent-actions"><button type="button" onClick={()=>save(false,false)}>Necessary only</button><button type="button" onClick={()=>setDetails(!details)} aria-expanded={details}>Choose</button><button type="button" onClick={()=>save(true,true)} className="is-primary">Accept all</button></div></div>
  {details&&<div className="raoza-consent-details"><label><span><b>Necessary</b><small>Required for cart, checkout and security.</small></span><input type="checkbox" checked disabled aria-label="Necessary cookies enabled"/></label><label><span><b>Analytics</b><small>Helps measure store usage when an analytics provider is configured.</small></span><input type="checkbox" checked={analytics} onChange={e=>setAnalytics(e.target.checked)}/></label><label><span><b>Marketing</b><small>Allows marketing measurement when a marketing provider is configured.</small></span><input type="checkbox" checked={marketing} onChange={e=>setMarketing(e.target.checked)}/></label><div><button type="button" onClick={()=>save(analytics,marketing)} className="raoza-button">Save choices</button>{current.decided&&<button type="button" onClick={()=>setVisible(false)} className="raoza-button raoza-button-outline">Close</button>}</div></div>}
 </div>
}
