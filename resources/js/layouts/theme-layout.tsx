import { Head, Link } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { ArrowUpRight, Mail, Menu, Phone, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';

interface ThemeLayoutProps {
    children: ReactNode;
    title?: string;
}

const navLinks = [
    { label: 'Home', href: '#top' },
    { label: 'Services', href: '#services' },
    { label: 'Approach', href: '#approach' },
    { label: 'Contact', href: '#contact' },
];

const mobileMenuVariants = {
    hidden: { opacity: 0 },
    visible: {
        opacity: 1,
        transition: { staggerChildren: 0.06, delayChildren: 0.08 },
    },
};

const mobileLinkVariants = {
    hidden: { opacity: 0, y: 16 },
    visible: { opacity: 1, y: 0 },
};

export default function ThemeLayout({
    children,
    title = 'Pradhi Associates',
}: ThemeLayoutProps) {
    const [mobileOpen, setMobileOpen] = useState(false);
    const [scrolled, setScrolled] = useState(false);

    useEffect(() => {
        document.documentElement.classList.remove('dark');
    }, []);

    useEffect(() => {
        const handleScroll = () => setScrolled(window.scrollY > 40);
        handleScroll();
        window.addEventListener('scroll', handleScroll, { passive: true });

        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    useEffect(() => {
        document.body.style.overflow = mobileOpen ? 'hidden' : '';

        return () => {
            document.body.style.overflow = '';
        };
    }, [mobileOpen]);

    const compact = scrolled || mobileOpen;

    return (
        <div className="bg-white">
            <Head title={title} />

            <header
                id="top"
                className="sticky top-0 z-50 border-b border-black/5 bg-white/95 shadow-sm backdrop-blur-md transition-all duration-300"
            >
                <div
                    className={`mx-auto flex max-w-7xl items-center justify-between px-6 transition-all duration-300 lg:px-8 ${compact ? 'py-3' : 'py-5'}`}
                >
                    <Link href="/" className="flex items-center gap-3.5">
                        <img
                            src="/images/logo_without_text.png"
                            alt="Pradhi Associates"
                            className="h-14 w-auto drop-shadow-sm transition-all duration-300 lg:h-16"
                        />
                        <span className="flex flex-col leading-none">
                            <span className="text-2xl font-extrabold text-brand-ink transition-colors lg:text-3xl">
                                Pradhi Associates
                            </span>
                            <span className="mt-1 text-xs font-semibold tracking-[0.2em] text-brand-ink/50 uppercase transition-colors">
                                Wise Choices Drive Excellence
                            </span>
                        </span>
                    </Link>

                    <nav className="hidden items-center gap-10 lg:flex">
                        {navLinks.map((link) => (
                            <a
                                key={link.label}
                                href={link.href}
                                className="group relative text-lg font-bold text-brand-ink/80 transition-colors hover:text-brand-ink"
                            >
                                {link.label}
                                <span className="absolute -bottom-1.5 left-0 h-0.5 w-0 rounded-full bg-brand-ink transition-all duration-300 group-hover:w-full" />
                            </a>
                        ))}
                        <Link
                            href="/jobs"
                            className="group relative text-lg font-bold text-brand-ink/80 transition-colors hover:text-brand-ink"
                        >
                            Careers
                            <span className="absolute -bottom-1.5 left-0 h-0.5 w-0 rounded-full bg-brand-ink transition-all duration-300 group-hover:w-full" />
                        </Link>
                    </nav>

                    <motion.a
                        href="#contact"
                        whileHover={{ scale: 1.04, y: -1 }}
                        whileTap={{ scale: 0.97 }}
                        className="hidden items-center gap-2 rounded-full bg-gradient-to-r from-brand-gold to-brand-amber px-6 py-3 text-base font-extrabold text-brand-ink shadow-lg shadow-brand-amber/40 lg:inline-flex"
                    >
                        Get in Touch
                        <ArrowUpRight className="h-4 w-4" />
                    </motion.a>

                    <button
                        onClick={() => setMobileOpen((open) => !open)}
                        aria-label="Toggle menu"
                        aria-expanded={mobileOpen}
                        className="z-10 grid h-11 w-11 place-items-center rounded-full text-brand-ink transition-colors lg:hidden"
                    >
                        {mobileOpen ? (
                            <X className="h-6 w-6" />
                        ) : (
                            <Menu className="h-6 w-6" />
                        )}
                    </button>
                </div>
            </header>

            <AnimatePresence>
                {mobileOpen && (
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        transition={{ duration: 0.25 }}
                        className="fixed inset-0 z-40 flex flex-col bg-white pt-28 lg:hidden"
                    >
                        <motion.nav
                            variants={mobileMenuVariants}
                            initial="hidden"
                            animate="visible"
                            className="flex flex-1 flex-col justify-center gap-2 px-8"
                        >
                            {navLinks.map((link) => (
                                <motion.a
                                    key={link.label}
                                    variants={mobileLinkVariants}
                                    href={link.href}
                                    onClick={() => setMobileOpen(false)}
                                    className="border-b border-brand-ink/10 py-4 text-3xl font-extrabold text-brand-ink"
                                >
                                    {link.label}
                                </motion.a>
                            ))}
                            <Link
                                href="/jobs"
                                onClick={() => setMobileOpen(false)}
                                className="border-b border-brand-ink/10 py-4 text-3xl font-extrabold text-brand-ink"
                            >
                                Careers
                            </Link>
                            <motion.a
                                variants={mobileLinkVariants}
                                href="#contact"
                                onClick={() => setMobileOpen(false)}
                                className="mt-8 inline-flex w-full items-center justify-center gap-2 rounded-full bg-gradient-to-r from-brand-gold to-brand-amber px-6 py-4 text-base font-extrabold text-brand-ink shadow-lg shadow-brand-amber/40"
                            >
                                Get in Touch
                                <ArrowUpRight className="h-4 w-4" />
                            </motion.a>
                        </motion.nav>
                    </motion.div>
                )}
            </AnimatePresence>

            <main>{children}</main>

            <footer className="relative overflow-hidden bg-gradient-to-br from-brand-gold to-brand-amber">
                <div className="mx-auto flex max-w-7xl flex-col gap-12 px-6 py-16 sm:flex-row sm:justify-between lg:px-8">
                    <div>
                        <h4 className="mb-5 text-base font-bold text-brand-ink after:mt-2 after:block after:h-0.5 after:w-10 after:bg-brand-ink/60">
                            Contact Info
                        </h4>
                        <ul className="space-y-3 text-brand-ink/75">
                            <li>
                                <a
                                    href="mailto:laksh_madhu@pradhiassociates.com"
                                    className="break-words hover:text-brand-ink"
                                >
                                    laksh_madhu@pradhiassociates.com
                                </a>
                            </li>
                            <li>
                                <a
                                    href="tel:+919910163337"
                                    className="hover:text-brand-ink"
                                >
                                    +91 9910163337
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <h4 className="mb-5 text-base font-bold text-brand-ink after:mt-2 after:block after:h-0.5 after:w-10 after:bg-brand-ink/60">
                            Quick Links
                        </h4>
                        <ul className="space-y-3 text-brand-ink/75">
                            {navLinks.map((link) => (
                                <li key={link.label}>
                                    <a
                                        href={link.href}
                                        className="inline-block transition-transform hover:translate-x-1 hover:text-brand-ink"
                                    >
                                        {link.label}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div>
                        <h4 className="mb-5 text-base font-bold text-brand-ink after:mt-2 after:block after:h-0.5 after:w-10 after:bg-brand-ink/60">
                            Get in Touch
                        </h4>
                        <a
                            href="mailto:laksh_madhu@pradhiassociates.com"
                            className="flex items-center gap-2.5 font-medium text-brand-ink/80 hover:text-brand-ink"
                        >
                            <Mail className="h-4 w-4 shrink-0" /> Email us
                        </a>
                        <a
                            href="tel:+919910163337"
                            className="mt-3 flex items-center gap-2.5 font-medium text-brand-ink/80 hover:text-brand-ink"
                        >
                            <Phone className="h-4 w-4 shrink-0" /> Call us
                        </a>
                    </div>
                </div>

                <div className="border-t border-black/10">
                    <div className="mx-auto max-w-7xl px-6 py-6 text-center text-sm text-brand-ink/70 lg:px-8">
                        &copy; {new Date().getFullYear()} Pradhi Associates. All
                        rights reserved.
                    </div>
                </div>
            </footer>
        </div>
    );
}
