import { Form, Head } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function Login() {
    return (
        <div className="flex min-h-screen items-center justify-center bg-brand-cream px-6">
            <Head title="Log in" />

            <div className="w-full max-w-md rounded-3xl bg-white p-10 shadow-xl shadow-black/5">
                <div className="mb-8 flex flex-col items-center text-center">
                    <img
                        src="/images/logo_without_text.png"
                        alt="Pradhi Associates"
                        className="h-14 w-auto"
                    />
                    <h1 className="mt-4 text-2xl font-extrabold text-brand-ink">
                        Admin Sign In
                    </h1>
                    <p className="mt-1 text-sm text-brand-ink/60">
                        Pradhi Associates staff portal
                    </p>
                </div>

                <Form
                    method="post"
                    action="/login"
                    resetOnSuccess={['password']}
                    className="space-y-5"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="space-y-2">
                                <Label
                                    htmlFor="email"
                                    className="text-brand-ink"
                                >
                                    Email
                                </Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    autoComplete="username"
                                    autoFocus
                                    required
                                    className="text-brand-ink placeholder:text-brand-ink/40"
                                />
                                {errors.email && (
                                    <p className="text-sm text-destructive">
                                        {errors.email}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label
                                    htmlFor="password"
                                    className="text-brand-ink"
                                >
                                    Password
                                </Label>
                                <Input
                                    id="password"
                                    name="password"
                                    type="password"
                                    autoComplete="current-password"
                                    required
                                    className="text-brand-ink placeholder:text-brand-ink/40"
                                />
                                {errors.password && (
                                    <p className="text-sm text-destructive">
                                        {errors.password}
                                    </p>
                                )}
                            </div>

                            <label className="flex items-center gap-2 text-sm text-brand-ink/70">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    value="1"
                                    className="rounded border-brand-ink/30"
                                />
                                Remember me
                            </label>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full rounded-full bg-gradient-to-r from-brand-gold to-brand-amber py-6 text-base font-extrabold text-brand-ink shadow-lg shadow-brand-amber/40 hover:opacity-90"
                            >
                                {processing && (
                                    <LoaderCircle className="h-4 w-4 animate-spin" />
                                )}
                                Log in
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </div>
    );
}
