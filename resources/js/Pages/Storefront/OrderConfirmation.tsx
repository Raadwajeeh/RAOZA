import { Form, Head, Link } from '@inertiajs/react';
import Money from '../../Components/Money';
import StorefrontLayout from '../../Layouts/StorefrontLayout';

type Address={first_name:string;last_name:string;company?:string|null;street:string;house_number:string;addition?:string|null;postal_code:string;city:string;country_code:string};
type Order={number:string;email:string;subtotal:number;discount:number;discountCode?:string|null;shipping:number;shippingMethod?:string|null;tax:number;taxRateBasisPoints:number;total:number;paymentStatus:string;orderStatus:string;fulfillmentStatus:string;canPay:boolean;latestPaymentStatus?:string|null;shippingAddress?:Address|null;items:{name:string;options:Record<string,string>;quantity:number;total:number}[]};

const human=(value?:string|null)=>(value||'pending').replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase());

export default function OrderConfirmation({order}:{order:Order}){
    const paid=['paid','partially_refunded','refunded'].includes(order.paymentStatus);
    const paymentProblem=['failed','cancelled','expired'].includes(order.latestPaymentStatus||'');
    const steps=paid
        ? [{n:'01',label:'Order',detail:'Confirmed',active:true},{n:'02',label:'Payment',detail:'Verified',active:true},{n:'03',label:'Production',detail:human(order.fulfillmentStatus),active:['printing','ready_to_ship','fulfilled'].includes(order.fulfillmentStatus)},{n:'04',label:'Delivery',detail:order.fulfillmentStatus==='fulfilled'?'Fulfilled':'Next',active:order.fulfillmentStatus==='fulfilled'}]
        : [{n:'01',label:'Order',detail:'Reserved',active:true},{n:'02',label:'Payment',detail:paymentProblem?'Action needed':'Pending',active:false},{n:'03',label:'Production',detail:'After payment',active:false},{n:'04',label:'Delivery',detail:'After production',active:false}];

    return <StorefrontLayout><Head title={`Order ${order.number} — RAOZA`}><meta name="robots" content="noindex,nofollow"/></Head><main className="mx-auto max-w-[1240px] px-5 py-12 md:px-10 md:py-20">
        <header className="grid gap-8 border-b border-raoza-primary/20 pb-10 lg:grid-cols-[1fr_auto] lg:items-end">
            <div><p className="text-[10px] uppercase tracking-[.24em] text-raoza-secondary">{paid?'Order confirmed':'Complete your order'}</p><h1 className="mt-3 max-w-4xl font-display text-5xl leading-[.92] text-raoza-primary sm:text-6xl md:text-8xl">{paid?'Thank you. Your RAOZA piece is in motion.':'One last step.'}</h1></div>
            <div className="lg:text-right"><p className="text-[10px] uppercase tracking-[.18em] text-raoza-black/45">Order reference</p><p className="mt-2 text-sm font-semibold tracking-[.08em]">{order.number}</p></div>
        </header>

        <section className="grid gap-0 border-b border-raoza-primary/20 py-10 sm:grid-cols-2 lg:grid-cols-4" aria-label="Order progress">
            {steps.map((step,index)=><div key={step.n} className={`relative flex items-center gap-4 py-3 ${index<steps.length-1?'lg:after:absolute lg:after:left-[3.8rem] lg:after:right-0 lg:after:top-8 lg:after:h-px lg:after:bg-raoza-primary/15':''}`}>
                <div className={`relative z-10 grid h-14 w-14 shrink-0 place-items-center rounded-full border text-[10px] font-bold tracking-[.14em] ${step.active?'border-raoza-primary bg-raoza-primary text-raoza-cream':'border-raoza-primary/25 bg-raoza-cream text-raoza-primary/45'}`}>{step.n}</div>
                <div className="relative z-10 bg-raoza-cream pr-4"><p className="text-xs font-semibold uppercase tracking-[.12em]">{step.label}</p><p className="mt-1 text-xs text-raoza-black/50">{step.detail}</p></div>
            </div>)}
        </section>

        <div className="grid gap-12 py-12 lg:grid-cols-[1.25fr_.75fr] lg:gap-20">
            <section><div className="flex items-end justify-between gap-5"><div><p className="text-[10px] uppercase tracking-[.2em] text-raoza-secondary">Your selection</p><h2 className="mt-2 font-display text-4xl text-raoza-primary">Order details.</h2></div><div className="grid h-16 w-16 place-items-center rounded-full border border-raoza-gold text-xs font-semibold text-raoza-primary">{order.items.reduce((sum,i)=>sum+i.quantity,0)}</div></div>
                <div className="mt-7 border-y border-raoza-primary/20">{order.items.map((i,n)=><div key={n} className="grid grid-cols-[3rem_1fr_auto] items-center gap-4 border-b border-raoza-primary/10 py-5 last:border-b-0"><div className="grid h-12 w-12 place-items-center rounded-full border border-raoza-primary/20 font-display text-lg text-raoza-primary">{String(n+1).padStart(2,'0')}</div><div><p className="text-sm font-semibold">{i.name} <span className="font-normal text-raoza-black/45">× {i.quantity}</span></p><p className="mt-1 text-xs text-raoza-black/50">{Object.values(i.options||{}).join(' · ')||'Standard edition'}</p></div><div className="text-sm font-semibold"><Money amount={i.total}/></div></div>)}</div>
                <p className="mt-6 max-w-xl text-sm leading-6 text-raoza-black/60">{paid?<>A confirmation has been registered for <strong className="text-raoza-black">{order.email}</strong>. We only show progress that is supported by the current order state.</>:<>Your order is registered for <strong className="text-raoza-black">{order.email}</strong>. Stock remains reserved while the active payment flow is being completed.</>}</p>
            </section>

            <aside className="lg:border-l lg:border-raoza-primary/20 lg:pl-10"><p className="text-[10px] uppercase tracking-[.2em] text-raoza-secondary">Summary</p><div className="mt-5 space-y-3 text-sm"><div className="flex justify-between"><span>Subtotal</span><Money amount={order.subtotal}/></div>{order.discount>0&&<div className="flex justify-between"><span>Discount {order.discountCode?`(${order.discountCode})`:''}</span><span>− <Money amount={order.discount}/></span></div>}<div className="flex justify-between"><span>{order.shippingMethod||'Shipping'}</span><Money amount={order.shipping}/></div><div className="flex justify-between text-xs text-raoza-black/50"><span>VAT included ({(order.taxRateBasisPoints/100).toFixed(0)}%)</span><Money amount={order.tax}/></div><div className="mt-5 flex items-end justify-between border-t border-raoza-primary/20 pt-5"><span className="text-xs uppercase tracking-[.14em]">Total</span><span className="font-display text-3xl text-raoza-primary"><Money amount={order.total}/></span></div></div>
                <div className="mt-7 grid gap-2 border-y border-raoza-primary/15 py-5 text-[10px] uppercase tracking-[.13em]"><div className="flex justify-between"><span className="text-raoza-black/45">Payment</span><span>{human(order.paymentStatus)}</span></div><div className="flex justify-between"><span className="text-raoza-black/45">Order</span><span>{human(order.orderStatus)}</span></div><div className="flex justify-between"><span className="text-raoza-black/45">Fulfillment</span><span>{human(order.fulfillmentStatus)}</span></div></div>
                {paymentProblem&&!paid&&<p role="alert" className="mt-5 border border-raoza-secondary/35 p-4 text-xs leading-5 text-raoza-secondary">The previous payment attempt was {order.latestPaymentStatus}. Your order can be retried while payment remains available.</p>}
                {order.shippingAddress&&<div className="mt-6 border-t border-raoza-primary/15 pt-5 text-sm leading-6"><p className="text-[10px] uppercase tracking-[.15em] text-raoza-black/45">Shipping to</p><p className="mt-2 font-semibold">{order.shippingAddress.first_name} {order.shippingAddress.last_name}</p>{order.shippingAddress.company&&<p>{order.shippingAddress.company}</p>}<p>{order.shippingAddress.street} {order.shippingAddress.house_number}{order.shippingAddress.addition?` ${order.shippingAddress.addition}`:''}</p><p>{order.shippingAddress.postal_code} {order.shippingAddress.city} · {order.shippingAddress.country_code}</p></div>}
                {order.canPay&&<Form action={`/orders/${order.number}/pay`} method="post" className="mt-6" disableWhileProcessing>{({processing,errors})=><><button disabled={processing} className="raoza-button w-full disabled:opacity-45">{processing?'Opening payment…':order.latestPaymentStatus?'Retry payment':'Continue to secure payment'}</button>{errors.payment&&<p role="alert" className="mt-3 text-xs text-raoza-secondary">{errors.payment}</p>}</>}</Form>}
                <Link href="/shop" className="raoza-button raoza-button-outline mt-3 w-full text-center">Continue shopping</Link>
                <p className="mt-5 text-xs leading-5 text-raoza-black/55">Need help with this order? <Link href="/pages/contact" className="font-semibold underline underline-offset-2">Contact customer service</Link> and include your order reference.</p>
            </aside>
        </div>
    </main></StorefrontLayout>;
}
