import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';

const eur = (n: number) => (n / 100).toLocaleString('nl-NL', { style: 'currency', currency: 'EUR' });

export default function CustomersIndex({ customers, filters }: any) {
    const [q, setQ] = useState(filters?.q ?? '');

    return <AdminLayout>
        <Head title="Customers" />
        <h1 className="font-display text-5xl text-raoza-primary">Customers</h1>
        <p className="mt-2 text-sm text-raoza-black/55">Operational customer history derived from orders. Guest checkout remains supported; this is not a marketing profile database.</p>
        <form className="mt-6 flex max-w-xl flex-col gap-2 sm:flex-row" onSubmit={event => {
            event.preventDefault();
            router.get('/admin/customers', { q }, { preserveState: true });
        }}>
            <label className="sr-only" htmlFor="customer-search">Search customer email</label>
            <input id="customer-search" className="min-w-0 w-full flex-1 border border-raoza-primary/20 bg-white p-3 text-sm" value={q} onChange={event => setQ(event.target.value)} placeholder="Search customer email" />
            <button className="min-h-11 bg-raoza-primary px-5 text-xs uppercase tracking-widest text-raoza-cream">Search</button>
        </form>
        <div className="mt-6 overflow-hidden border border-raoza-primary/15 bg-white">
            <div className="hidden grid-cols-[1.4fr_1.5fr_.7fr_.7fr_1fr_.4fr] bg-raoza-primary p-3 text-xs uppercase tracking-wider text-raoza-cream md:grid"><span>Customer</span><span>Email</span><span>Orders</span><span>Paid</span><span>Paid value</span><span></span></div>
            {customers.data.map((customer: any) => <div className="grid gap-2 border-t p-4 text-sm md:grid-cols-[1.4fr_1.5fr_.7fr_.7fr_1fr_.4fr] md:items-center" key={customer.customer_email}><div><b>{customer.customer_name || customer.account_name || 'Name unavailable'}</b><div className="mt-1 text-xs opacity-55">{customer.is_account ? 'Account customer' : 'Guest checkout'}</div></div><span className="break-all">{customer.customer_email}</span><span><span className="md:hidden">Orders: </span>{customer.orders_count}</span><span><span className="md:hidden">Paid: </span>{customer.paid_orders_count}</span><span>{eur(+customer.lifetime_value)}</span><Link href={`/admin/customers/${encodeURIComponent(customer.customer_email)}`} className="min-h-11 py-3 underline">View</Link></div>)}
            {customers.data.length === 0 && <div className="p-8 text-sm opacity-60">No customers match this search.</div>}
        </div>
    </AdminLayout>;
}
