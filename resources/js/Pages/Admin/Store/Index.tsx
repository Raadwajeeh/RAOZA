import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';

const field = 'mt-1 min-h-11 w-full border border-raoza-primary/20 bg-white px-3 py-2 text-sm';
const emptyShipping = { name: '', code: '', provider: 'manual', price: 0, currency: 'EUR', active: true, position: 0, description: '' };

export default function StoreIndex({ store, shippingMethods }: any) {
    const info = useForm({ brand_name: store.brand_name ?? 'RAOZA', company_name: store.company_name ?? '', contact_email: store.contact_email ?? '', support_email: store.support_email ?? '', contact_phone: store.contact_phone ?? '', street: store.street ?? '', house_number: store.house_number ?? '', addition: store.addition ?? '', postal_code: store.postal_code ?? '', city: store.city ?? '', country: store.country ?? 'Netherlands', country_code: store.country_code ?? 'NL', registration_number: store.registration_number ?? '', vat_number: store.vat_number ?? '', currency: store.currency ?? 'EUR', locale: store.locale ?? 'en-NL', shipping_origin: store.shipping_origin ?? 'Netherlands', customer_service: store.customer_service ?? '', instagram_url: store.instagram_url ?? '' });
    const [editing, setEditing] = useState<any>(null);
    const shipping = useForm(emptyShipping);
    const edit = (method: any) => {
        setEditing(method);
        shipping.setData({ name: method.name, code: method.code, provider: method.provider || 'manual', price: method.price, currency: method.currency, active: method.active, position: method.position, description: method.configuration?.description || '' });
    };
    const reset = () => { setEditing(null); shipping.setData(emptyShipping); shipping.clearErrors(); };

    return <AdminLayout><Head title="Store settings" />
        <div><p className="text-xs uppercase tracking-[.18em] text-raoza-secondary">Store</p><h1 className="mt-2 font-display text-4xl text-raoza-primary sm:text-5xl">Store configuration</h1><p className="mt-2 max-w-2xl text-sm opacity-60">Manage legitimate operational information and checkout shipping methods. Credentials and infrastructure settings stay outside the Admin.</p></div>
        <div className="mt-8 grid gap-8 xl:grid-cols-2">
            <form onSubmit={e => { e.preventDefault(); info.patch('/admin/store', { preserveScroll: true }); }} className="border bg-white p-5 sm:p-6">
                <h2 className="font-display text-2xl">Company & contact information</h2><p className="mt-1 text-xs opacity-55">Leave unknown values empty; nothing is fabricated or automatically published.</p>
                <div className="mt-5 grid gap-4 sm:grid-cols-2">
                    <label className="text-sm">Brand name<input required className={field} value={info.data.brand_name} onChange={e => info.setData('brand_name', e.target.value)} /></label>
                    <label className="text-sm">Company name<input required className={field} value={info.data.company_name} onChange={e => info.setData('company_name', e.target.value)} /></label>
                    <label className="text-sm">Contact email<input type="email" className={field} value={info.data.contact_email} onChange={e => info.setData('contact_email', e.target.value)} /></label>
                    <label className="text-sm">Support email<input type="email" className={field} value={info.data.support_email} onChange={e => info.setData('support_email', e.target.value)} /></label>
                    <label className="text-sm">Contact phone<input className={field} value={info.data.contact_phone} onChange={e => info.setData('contact_phone', e.target.value)} /></label>
                    <label className="text-sm">Street<input className={field} value={info.data.street} onChange={e => info.setData('street', e.target.value)} /></label>
                    <label className="text-sm">House number<input className={field} value={info.data.house_number} onChange={e => info.setData('house_number', e.target.value)} /></label>
                    <label className="text-sm">Addition<input className={field} value={info.data.addition} onChange={e => info.setData('addition', e.target.value)} /></label>
                    <label className="text-sm">Postal code<input className={field} value={info.data.postal_code} onChange={e => info.setData('postal_code', e.target.value)} /></label>
                    <label className="text-sm">City<input className={field} value={info.data.city} onChange={e => info.setData('city', e.target.value)} /></label>
                    <label className="text-sm">Country<input required className={field} value={info.data.country} onChange={e => info.setData('country', e.target.value)} /></label>
                    <label className="text-sm">Country code<input maxLength={2} className={field} value={info.data.country_code} onChange={e => info.setData('country_code', e.target.value.toUpperCase())} /></label>
                    <label className="text-sm">Registration number<input className={field} value={info.data.registration_number} onChange={e => info.setData('registration_number', e.target.value)} /></label>
                    <label className="text-sm">VAT number<input className={field} value={info.data.vat_number} onChange={e => info.setData('vat_number', e.target.value)} /></label>
                    <label className="text-sm">Currency<input maxLength={3} className={field} value={info.data.currency} onChange={e => info.setData('currency', e.target.value.toUpperCase())} /></label>
                    <label className="text-sm">Locale<input className={field} value={info.data.locale} onChange={e => info.setData('locale', e.target.value)} /></label>
                    <label className="text-sm sm:col-span-2">Shipping origin<input className={field} value={info.data.shipping_origin} onChange={e => info.setData('shipping_origin', e.target.value)} /></label>
                    <label className="text-sm sm:col-span-2">Customer-service message<textarea rows={3} className={field} value={info.data.customer_service} onChange={e => info.setData('customer_service', e.target.value)} /></label>
                    <label className="text-sm sm:col-span-2">Instagram URL<input type="url" className={field} value={info.data.instagram_url} onChange={e => info.setData('instagram_url', e.target.value)} /></label>
                </div>
                {Object.keys(info.errors).length > 0 && <p role="alert" className="mt-4 text-sm text-red-800">{Object.values(info.errors)[0]}</p>}
                <button disabled={info.processing} className="mt-5 min-h-12 bg-raoza-primary px-5 text-sm text-raoza-cream">Save store information</button>
            </form>
            <div>
                <section className="space-y-3">
                    <div><h2 className="font-display text-3xl text-raoza-primary">Shipping methods</h2><p className="mt-1 text-xs opacity-55">Active methods are offered by the authoritative checkout quote service.</p></div>
                    {shippingMethods.map((method: any) => <article className="border bg-white p-4" key={method.id}><div className="flex justify-between gap-3"><div><b>{method.name}</b><p className="text-xs opacity-55">{method.code} · {method.provider || 'manual'}</p></div><span className="text-sm">{(method.price / 100).toLocaleString('nl-NL', { style: 'currency', currency: method.currency })}</span></div><p className="mt-3 text-sm opacity-65">{method.configuration?.description || 'No customer-facing description.'}</p><div className="mt-3 flex justify-between text-xs"><span>{method.active ? 'Active at checkout' : 'Disabled'} · position {method.position}</span><button onClick={() => edit(method)} className="min-h-11 px-3 underline">Edit</button></div></article>)}
                </section>
                <form onSubmit={e => { e.preventDefault(); editing ? shipping.patch(`/admin/store/shipping-methods/${editing.id}`, { preserveScroll: true, onSuccess: reset }) : shipping.post('/admin/store/shipping-methods', { preserveScroll: true, onSuccess: reset }); }} className="mt-6 border bg-white p-5">
                    <div className="flex justify-between"><h3 className="font-display text-2xl">{editing ? 'Edit shipping method' : 'New shipping method'}</h3>{editing && <button type="button" onClick={reset} className="text-sm underline">Cancel</button>}</div>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <label className="text-sm">Name *<input required className={field} value={shipping.data.name} onChange={e => shipping.setData('name', e.target.value)} /></label>
                        <label className="text-sm">Code<input className={field} value={shipping.data.code} onChange={e => shipping.setData('code', e.target.value)} /></label>
                        <label className="text-sm">Checkout price (€)<input type="number" min="0" step="0.01" inputMode="decimal" className={field} value={(shipping.data.price / 100).toFixed(2)} onChange={e => shipping.setData('price', Math.round(Number(e.target.value) * 100))} /></label>
                        <label className="text-sm">Position<input type="number" min="0" className={field} value={shipping.data.position} onChange={e => shipping.setData('position', Number(e.target.value))} /></label>
                        <label className="text-sm">Provider<select className={field} value={shipping.data.provider} onChange={e => shipping.setData('provider', e.target.value)}><option value="manual">Manual</option><option value="demo">Local demo</option></select></label>
                        <label className="flex min-h-11 items-center gap-2 self-end text-sm"><input type="checkbox" checked={shipping.data.active} onChange={e => shipping.setData('active', e.target.checked)} />Active at checkout</label>
                        <label className="text-sm sm:col-span-2">Checkout description<textarea rows={3} className={field} value={shipping.data.description} onChange={e => shipping.setData('description', e.target.value)} /></label>
                    </div>
                    {Object.keys(shipping.errors).length > 0 && <p role="alert" className="mt-4 text-sm text-red-800">{Object.values(shipping.errors)[0]}</p>}
                    <button disabled={shipping.processing || !shipping.data.name} className="mt-5 min-h-12 bg-raoza-primary px-5 text-sm text-raoza-cream disabled:opacity-40">{editing ? 'Save shipping method' : 'Create shipping method'}</button>
                </form>
            </div>
        </div>
    </AdminLayout>;
}
