import { Form, Head, Link } from '@inertiajs/react';
import StorefrontLayout from '../../../Layouts/StorefrontLayout';

export default function Login() {
    return <StorefrontLayout><Head title="Sign in"/><main className="mx-auto grid min-h-[38rem] max-w-[1200px] gap-12 px-5 py-16 md:px-10 lg:grid-cols-[.85fr_1.15fr] lg:py-24">
        <header><p className="text-[10px] uppercase tracking-[.22em] text-raoza-secondary">RAOZA / Account</p><h1 className="mt-4 font-display text-6xl leading-none text-raoza-primary md:text-8xl">Welcome<br/><i>back.</i></h1><p className="mt-6 max-w-md text-sm leading-6 text-raoza-black/60">Sign in to see orders placed with your verified RAOZA account. Guest checkout remains available without an account.</p></header>
        <section className="border border-raoza-primary/20 bg-white p-6 md:p-10"><h2 className="font-display text-3xl text-raoza-primary">Customer sign in</h2><Form action="/account/login" method="post" className="mt-8 space-y-5">{({errors,processing})=><>
            <label className="block text-sm font-medium">Email<input name="email" type="email" autoComplete="email" required autoFocus className="mt-2 min-h-12 w-full border border-raoza-primary/25 bg-raoza-cream px-4 outline-none focus:border-raoza-primary"/></label>{errors.email&&<p role="alert" className="text-sm text-red-800">{errors.email}</p>}
            <label className="block text-sm font-medium">Password<input name="password" type="password" autoComplete="current-password" required className="mt-2 min-h-12 w-full border border-raoza-primary/25 bg-raoza-cream px-4 outline-none focus:border-raoza-primary"/></label>{errors.password&&<p role="alert" className="text-sm text-red-800">{errors.password}</p>}
            <div className="flex flex-wrap items-center justify-between gap-3 text-sm"><label className="flex items-center gap-2"><input type="checkbox" name="remember" value="1"/> Remember me</label><Link href="/account/forgot-password" className="underline underline-offset-4">Forgot password?</Link></div>
            <button disabled={processing} className="raoza-button w-full disabled:opacity-50">{processing?'Signing in…':'Sign in'}</button>
        </>}</Form><div className="mt-7 border-t border-raoza-primary/15 pt-6 text-sm"><p>New to RAOZA? <Link href="/account/register" className="font-semibold underline underline-offset-4">Create an account</Link></p><p className="mt-3">Placed a guest order? <Link href="/order-access" className="font-semibold underline underline-offset-4">Request a secure link</Link></p></div></section>
    </main></StorefrontLayout>;
}
