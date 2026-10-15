import { Form, Head, Link } from '@inertiajs/react';
import StorefrontLayout from '../../../Layouts/StorefrontLayout';

export default function Register() {
    return <StorefrontLayout><Head title="Create account"/><main className="mx-auto grid max-w-[1200px] gap-12 px-5 py-16 md:px-10 lg:grid-cols-[.85fr_1.15fr] lg:py-24">
        <header><p className="text-[10px] uppercase tracking-[.22em] text-raoza-secondary">RAOZA / Membership</p><h1 className="mt-4 font-display text-6xl leading-none text-raoza-primary md:text-8xl">Your pieces,<br/><i>in one place.</i></h1><p className="mt-6 max-w-md text-sm leading-6 text-raoza-black/60">An account is optional. It gives you a private order history after your email address is verified.</p></header>
        <section className="border border-raoza-primary/20 bg-white p-6 md:p-10"><h2 className="font-display text-3xl text-raoza-primary">Create your account</h2><Form action="/account/register" method="post" className="mt-8 space-y-5">{({errors,processing})=><>
            <label className="block text-sm font-medium">Name<input name="name" autoComplete="name" required autoFocus className="mt-2 min-h-12 w-full border border-raoza-primary/25 bg-raoza-cream px-4"/></label>{errors.name&&<p role="alert" className="text-sm text-red-800">{errors.name}</p>}
            <label className="block text-sm font-medium">Email<input name="email" type="email" autoComplete="email" required className="mt-2 min-h-12 w-full border border-raoza-primary/25 bg-raoza-cream px-4"/></label>{errors.email&&<p role="alert" className="text-sm text-red-800">{errors.email}</p>}
            <label className="block text-sm font-medium">Password<input name="password" type="password" autoComplete="new-password" required className="mt-2 min-h-12 w-full border border-raoza-primary/25 bg-raoza-cream px-4"/><span className="mt-2 block text-xs font-normal text-raoza-black/50">At least 10 characters with upper and lower case letters and a number.</span></label>{errors.password&&<p role="alert" className="text-sm text-red-800">{errors.password}</p>}
            <label className="block text-sm font-medium">Confirm password<input name="password_confirmation" type="password" autoComplete="new-password" required className="mt-2 min-h-12 w-full border border-raoza-primary/25 bg-raoza-cream px-4"/></label>
            <button disabled={processing} className="raoza-button w-full disabled:opacity-50">{processing?'Creating account…':'Create account'}</button>
        </>}</Form><p className="mt-6 text-sm">Already registered? <Link href="/account/login" className="font-semibold underline underline-offset-4">Sign in</Link></p></section>
    </main></StorefrontLayout>;
}
