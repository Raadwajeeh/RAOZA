import { Form, Head, usePage } from '@inertiajs/react';
import StorefrontLayout from '../../../Layouts/StorefrontLayout';

export default function VerifyEmail({email}:{email:string}) {
    const success=(usePage().props.flash as any)?.success;
    return <StorefrontLayout><Head title="Verify email"/><main className="mx-auto max-w-3xl px-5 py-20 text-center md:px-10 md:py-28"><p className="text-[10px] uppercase tracking-[.22em] text-raoza-secondary">One secure step</p><h1 className="mt-4 font-display text-6xl text-raoza-primary md:text-7xl">Verify your email.</h1><p className="mx-auto mt-6 max-w-xl text-sm leading-6 text-raoza-black/60">We sent a verification link to <strong className="text-raoza-black">{email}</strong>. Order history is only linked after this address is verified.</p>{success&&<p role="status" className="mx-auto mt-6 max-w-xl border border-raoza-primary/20 bg-white p-4 text-sm">{success}</p>}<div className="mx-auto mt-8 flex max-w-xl flex-col gap-3 sm:flex-row sm:justify-center"><Form action="/account/email/verification-notification" method="post">{({processing})=><button disabled={processing} className="raoza-button disabled:opacity-50">{processing?'Sending…':'Send a new link'}</button>}</Form><Form action="/logout" method="post"><button className="raoza-button raoza-button-outline">Sign out</button></Form></div></main></StorefrontLayout>;
}
