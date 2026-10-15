import { Form, Head, Link, usePage } from '@inertiajs/react';
import Money from '../../Components/Money';
import AccountNav from '../../Components/AccountNav';
import StorefrontLayout from '../../Layouts/StorefrontLayout';

type Order={number:string;placedAt:string;total:number;currency:string;paymentStatus:string;orderStatus:string;fulfillmentStatus:string};
type Pagination={data:Order[];current_page:number;last_page:number;prev_page_url:string|null;next_page_url:string|null};
const label=(value:string)=>value.replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase());

export default function Account({orders}:{orders:Pagination}) {
    const page=usePage<{auth:{user:{name:string;email:string}};flash?:{success?:string}}>();
    const user=page.props.auth.user;
    return <StorefrontLayout><Head title="My account"><meta name="robots" content="noindex,nofollow"/></Head><main className="mx-auto max-w-[1200px] px-5 py-14 md:px-10 md:py-20">
        <header className="flex flex-wrap items-end justify-between gap-8 border-b border-raoza-primary/20 pb-10"><div><p className="text-[10px] uppercase tracking-[.22em] text-raoza-secondary">RAOZA / Private account</p><h1 className="mt-3 font-display text-6xl text-raoza-primary md:text-8xl">Your account.</h1><p className="mt-4 text-sm text-raoza-black/60">{user.name} · {user.email}</p></div><Form action="/logout" method="post"><button className="raoza-button raoza-button-outline">Sign out</button></Form></header>
        <AccountNav/>
        {page.props.flash?.success&&<p role="status" className="mt-6 border border-raoza-primary/20 bg-white p-4 text-sm">{page.props.flash.success}</p>}
        <section className="py-10"><div className="flex items-end justify-between"><div><p className="text-[10px] uppercase tracking-[.2em] text-raoza-secondary">Order archive</p><h2 className="mt-2 font-display text-4xl text-raoza-primary">Your orders.</h2></div><Link href="/order-access" className="text-xs font-semibold uppercase tracking-wider underline underline-offset-4">Find a guest order</Link></div>
            {orders.data.length===0?<div className="mt-8 border border-raoza-primary/20 bg-white p-8"><p className="font-display text-3xl text-raoza-primary">No linked orders yet.</p><p className="mt-3 max-w-xl text-sm leading-6 text-raoza-black/60">Orders using this email are linked only after email verification. You can continue shopping as a guest at any time.</p><Link href="/shop" className="raoza-button mt-6">Shop the collection</Link></div>:<div className="mt-8 border-y border-raoza-primary/20">{orders.data.map(order=><Link key={order.number} href={`/account/orders/${order.number}`} className="grid gap-3 border-b border-raoza-primary/15 py-5 last:border-0 md:grid-cols-[1.2fr_.8fr_.8fr_.8fr_auto] md:items-center"><div><p className="text-xs uppercase tracking-wider text-raoza-black/45">{new Date(order.placedAt).toLocaleDateString('nl-NL')}</p><p className="mt-1 font-semibold">{order.number}</p></div><span className="text-sm">{label(order.paymentStatus)}</span><span className="text-sm">{label(order.fulfillmentStatus)}</span><strong><Money amount={order.total}/></strong><span aria-hidden="true">→</span></Link>)}</div>}
            {(orders.prev_page_url||orders.next_page_url)&&<nav aria-label="Order pages" className="mt-8 flex justify-between">{orders.prev_page_url?<Link href={orders.prev_page_url} className="raoza-button raoza-button-outline">Previous</Link>:<span/>}{orders.next_page_url&&<Link href={orders.next_page_url} className="raoza-button">Next</Link>}</nav>}
        </section><section aria-labelledby="future-invoices" className="mb-10 border border-dashed border-raoza-primary/25 p-6"><p className="text-[10px] uppercase tracking-wider text-raoza-secondary">Future workspace</p><h2 id="future-invoices" className="mt-2 font-display text-3xl text-raoza-primary">Invoices.</h2><p className="mt-2 text-sm text-raoza-black/55">Invoice documents will appear here when the invoicing phase is implemented. No download action is available yet.</p></section>
    </main></StorefrontLayout>;
}
