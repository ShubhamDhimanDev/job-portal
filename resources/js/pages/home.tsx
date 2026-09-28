import { Link } from '@inertiajs/react';
import {
    AnimatePresence,
    motion,
    useMotionValueEvent,
    useScroll,
    useTransform,
} from 'framer-motion';
import {
    ArrowRight,
    BarChart3,
    Briefcase,
    ChevronDown,
    ClipboardCheck,
    Clock,
    Code2,
    Crown,
    Handshake,
    Lightbulb,
    Mail,
    MapPin,
    Megaphone,
    Percent,
    Phone,
    PieChart,
    Plug,
    Puzzle,
    ScanSearch,
    ShieldCheck,
    SlidersHorizontal,
    Sparkles,
    Users,
    Video,
    Wrench,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Reveal from '@/components/reveal';
import ThemeLayout from '@/layouts/theme-layout';

const problems = [
    {
        tab: 'Aging Positions',
        title: 'Aging positions of 60+ days despite multiple vendors',
        body: 'Diagnosing ageing reasons, job positioning, and partnering with your in-house hiring team to fix the challenges around the roles.',
        icon: Clock,
        // Backup: image: 'https://images.unsplash.com/photo-1508962914676-134849a727f0?w=900&q=80',
        image: '/images/aging-position.jpeg',
        imagePosition: 'right center',
    },
    {
        tab: 'Niche & Leadership',
        title: 'Difficulty hiring niche skills and leadership roles',
        body: 'Strategic mapping of markets and projects, competitive mapping of niche skills, and leadership searches from assessment to compensation closure.',
        icon: Crown,
        image: 'https://images.unsplash.com/photo-1517502884422-41eaead166d4?w=900&q=80',
    },
    {
        tab: 'Recruitment Tech',
        title: 'Inefficient usage of digital recruitment software',
        body: 'Over 20 years of expertise implementing recruitment solutions across Fortune 500 companies — we handhold in-house teams through transformation.',
        icon: Code2,
        image: 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=900&q=80',
    },
];

const howWeWork = [
    {
        num: '01',
        title: 'Tailored Strategic Acquisition Solutions',
        body: 'Custom solutions built around your ageing reasons and job positioning, complementing and supplementing your in-house hiring team.',
        icon: SlidersHorizontal,
        image: 'https://images.unsplash.com/photo-1600880292203-757bb62b4baf?w=800&q=80',
        alt: 'Team planning a recruitment strategy',
    },
    {
        num: '02',
        title: 'Talent Mapping and Management',
        body: 'Strategic identification and management of talent pipelines to enhance workforce efficiency and reduce time-to-hire for critical positions.',
        icon: MapPin,
        image: 'https://images.unsplash.com/photo-1587440871875-191322ee64b0?w=800&q=80',
        alt: 'Team mapping out a talent pipeline on a whiteboard',
    },
    {
        num: '03',
        title: 'Leveraging HR Technology',
        body: 'Advanced HR technology — ATS, AI screening, digital assessments — to streamline processes and dramatically improve hiring outcomes.',
        icon: Code2,
        image: 'https://images.unsplash.com/photo-1550439062-609e1531270e?w=800&q=80',
        alt: 'Recruiter working across multiple screens of hiring software',
    },
];

const competencies = [
    {
        title: 'Strategic Leadership',
        desc: 'Established new captives, optimized structures, and advanced talent management across global organisations.',
        icon: Crown,
        image: 'https://images.unsplash.com/photo-1552664730-d307ca884978?w=900&q=80',
        featured: true,
    },
    {
        title: 'Brand Storytelling',
        desc: 'Crafted engaging content for social media and developed internal and external EVP to attract top talent.',
        icon: Megaphone,
    },
    {
        title: 'Competitor Talent Mapping',
        desc: 'Diagnosing why acquisition teams face 60+ day ageing despite 200 empanelled vendors — and fixing it.',
        icon: Users,
    },
    {
        title: 'Operational Efficiency',
        desc: 'Utilized HR tools for enterprise-wide technology integration and process transformations at scale.',
        icon: BarChart3,
    },
];

const strategies = [
    {
        icon: Handshake,
        title: 'Strategic HR & Talent Management',
        desc: 'Enhancing workforce efficiency through strategic HR practices.',
        image: 'https://images.unsplash.com/photo-1531497865144-0464ef8fb9a9?w=800&q=80',
    },
    {
        icon: Plug,
        title: 'Technology Partner Collaboration',
        desc: 'Utilizing technology partners to streamline recruitment processes.',
        image: 'https://images.unsplash.com/photo-1571171637578-41bc2dd41cd2?w=800&q=80',
    },
    {
        icon: Handshake,
        title: 'Flexible Service Partnerships',
        desc: 'Offering flexible staffing solutions to meet evolving business needs.',
        image: 'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=800&q=80',
    },
    {
        icon: PieChart,
        title: 'Budget Management Improvement',
        desc: 'Optimizing budgets for recruitment and staffing expenses.',
        // Backup: image: 'https://images.unsplash.com/photo-1560472354-b33ff0c44a43?w=800&q=80',
        image: '/images/budget.jpeg',
    },
    {
        icon: ShieldCheck,
        title: 'Risk Mitigation Strategies',
        desc: 'Implementing strategies to reduce recruitment-related risks.',
        image: 'https://images.unsplash.com/photo-1521791055366-0d553872125f?w=800&q=80',
    },
    {
        icon: Lightbulb,
        title: 'Conflict Management Solutions',
        desc: 'Addressing and resolving conflicts in staffing effectively.',
        image: 'https://images.unsplash.com/photo-1552581234-26160f608093?w=800&q=80',
    },
];

const talentSolutions = [
    {
        title: 'HR Advisory Services',
        desc: 'Offering turn-key projects tailored to client needs.',
        image: 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?w=700&q=80',
    },
    {
        title: 'Master Recruitment Services',
        desc: 'Streamlined vendor management minimizes risks and conflicts.',
        image: 'https://images.unsplash.com/photo-1519389950473-47ba0277781c?w=700&q=80',
    },
    {
        title: 'Contract to Hire Strategy',
        desc: 'Campus and contract staff can transition post performance review.',
        image: 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=700&q=80',
    },
    {
        title: 'Pre Hire Training Programs',
        desc: 'Training campus candidates while still in college to expedite hiring.',
        image: 'https://images.unsplash.com/photo-1542744173-8e7e53415bb0?w=700&q=80',
    },
];

const proposals = [
    {
        title: 'Online Interview Platform Services',
        desc: 'Conducting interviews online through a dedicated platform.',
        icon: Video,
        image: 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?w=700&q=80',
    },
    {
        title: 'Integration of HR Tools',
        desc: 'Functional integration of plug-in HR tools with enterprise systems.',
        icon: Puzzle,
        image: 'https://images.unsplash.com/photo-1531482615713-2afd69097998?w=700&q=80',
    },
    {
        title: 'AI-Based Resume Screening',
        desc: 'AI technology to streamline and enhance resume screening.',
        icon: ScanSearch,
        image: 'https://images.unsplash.com/photo-1522252234503-e356532cafd5?w=700&q=80',
    },
    {
        title: 'Turnkey IT Projects Partnerships',
        desc: 'Partner for turnkey IT projects to deliver comprehensive solutions.',
        icon: Wrench,
        image: 'https://images.unsplash.com/photo-1556761175-5973dc0f32e7?w=700&q=80',
    },
    {
        title: 'Online Testing Solutions',
        desc: "Testing solutions online to assess candidates' skills effectively.",
        icon: ClipboardCheck,
        image: 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=700&q=80',
    },
];

function SectionHeading({
    eyebrow,
    title,
    sub,
    center = false,
}: {
    eyebrow: string;
    title: string;
    sub?: string;
    center?: boolean;
}) {
    return (
        <Reveal
            className={`mb-14 max-w-2xl ${center ? 'mx-auto text-center' : ''}`}
        >
            <div className={`flex items-center ${center ? 'justify-center' : ''} gap-3`}>
                                <span className="h-px w-8 bg-brand-gold"></span>

                                <span className="text-sm font-bold tracking-wide text-brand-gold sm:text-lg">
                                    {eyebrow}
                                </span>

                                <span className="h-px w-8 bg-brand-gold"></span>
                            </div>
            {/* <span className="text-base font-bold tracking-wide text-brand-gold">
                {eyebrow}
            </span> */}
            <h2 className="mt-2 text-4xl font-extrabold text-brand-ink sm:text-5xl">
                {title}
            </h2>
            {sub && <p className="mt-3 text-lg text-brand-ink/60">{sub}</p>}
        </Reveal>
    );
}

function Hero() {
    const heroRef = useRef<HTMLElement>(null);
    const { scrollYProgress } = useScroll({
        target: heroRef,
        offset: ['start start', 'end start'],
    });
    const imageY = useTransform(scrollYProgress, [0, 1], ['0%', '18%']);
    const contentOpacity = useTransform(scrollYProgress, [0, 0.8], [1, 0]);

    return (
        <section
            ref={heroRef}
            className="relative flex min-h-[94vh] items-center overflow-hidden"
        >
            <motion.img
                // Backup: src="https://images.unsplash.com/photo-1521737604893-d14cc237f11d?w=1920&q=80"
                src="/images/hero.png"
                alt="Pradhi Associates team at work"
                className="absolute inset-0 h-full w-full object-cover"
                style={{ y: imageY }}
                initial={{ scale: 1 }}
                animate={{ scale: 1.1 }}
                transition={{ duration: 22, ease: 'linear' }}
            />
            <div className="absolute inset-0 bg-gradient-to-r from-brand-ink/95 from-0% via-brand-ink/55 via-35% to-transparent to-65%" />

            <motion.div
                style={{ opacity: contentOpacity }}
                className="relative mx-auto w-full max-w-7xl px-6 py-32 lg:px-8"
            >
                <motion.div
                    initial="hidden"
                    animate="visible"
                    variants={{
                        hidden: {},
                        visible: {
                            transition: {
                                staggerChildren: 0.12,
                                delayChildren: 0.1,
                            },
                        },
                    }}
                    className="max-w-2xl text-left"
                >
                    <motion.h1
                        variants={{
                            hidden: { opacity: 0, y: 24 },
                            visible: { opacity: 1, y: 0 },
                        }}
                        transition={{ duration: 0.7, ease: [0.16, 1, 0.3, 1] }}
                        className="mt-6 text-5xl leading-[1.1] font-black text-white sm:text-7xl"
                    >
                        Wise Choices <br />
                        <span className="text-brand-gold">
                            Drive Excellence
                        </span>
                    </motion.h1>

                    <motion.p
                        variants={{
                            hidden: { opacity: 0, y: 24 },
                            visible: { opacity: 1, y: 0 },
                        }}
                        transition={{ duration: 0.7, ease: [0.16, 1, 0.3, 1] }}
                        className="mt-8 text-2xl leading-relaxed text-white/75 sm:text-3xl"
                    >
                        We partner with organisations to solve aging positions,
                        niche skill gaps, and leadership hiring challenges —
                        complementing your in-house team with deep Fortune 500
                        expertise.
                    </motion.p>

                    <motion.div
                        variants={{
                            hidden: { opacity: 0, y: 24 },
                            visible: { opacity: 1, y: 0 },
                        }}
                        transition={{ duration: 0.7, ease: [0.16, 1, 0.3, 1] }}
                        className="mt-10 flex flex-wrap justify-start gap-4"
                    >
                        <motion.div
                            whileHover={{ scale: 1.04, y: -2 }}
                            whileTap={{ scale: 0.97 }}
                        >
                            <Link
                                href="#services"
                                className="inline-flex items-center gap-2 rounded-md bg-brand-gold px-9 py-4 text-base font-extrabold text-brand-ink shadow-xl shadow-black/30"
                            >
                                Explore Our Services
                                <ArrowRight className="h-5 w-5" />
                            </Link>
                        </motion.div>
                        <motion.a
                            whileHover={{ scale: 1.04, y: -2 }}
                            whileTap={{ scale: 0.97 }}
                            href="#contact"
                            className="inline-flex items-center gap-2 rounded-md border-2 border-white/30 px-9 py-4 text-base font-extrabold text-white"
                        >
                            Get in Touch
                        </motion.a>
                    </motion.div>
                </motion.div>
            </motion.div>

            <motion.div
                animate={{ y: [0, 10, 0] }}
                transition={{
                    duration: 2,
                    repeat: Infinity,
                    ease: 'easeInOut',
                }}
                className="absolute bottom-8 left-1/2 -translate-x-1/2 text-white/60"
            >
                <ChevronDown className="h-6 w-6" />
            </motion.div>
        </section>
    );
}

// Scroll distance (px) dedicated to each category while the panel is
// pinned. Native `position: sticky` always "wastes" one panel-height's
// worth of scroll on the way in and out (unavoidable release mechanics),
// so the container height is measured + computed explicitly below rather
// than guessed with a flat `100vh * steps` — that flat approach leaves a
// large empty gap after the panel scrolls away at the very end.
const SCROLL_BUDGET_PER_STEP = 500;

function ProblemSolveSection() {
    const containerRef = useRef<HTMLDivElement>(null);
    const stickyRef = useRef<HTMLDivElement>(null);
    const [active, setActive] = useState(0);
    const [containerHeight, setContainerHeight] = useState<number | null>(null);
    // The pinned scroll-through effect is desktop-only — on mobile the
    // section just flows normally and tabs switch the card on tap, so
    // nothing can ever get stuck out of view behind a sticky panel.
    const [isDesktop, setIsDesktop] = useState(false);

    useEffect(() => {
        const mql = window.matchMedia('(min-width: 1024px)');
        const update = () => setIsDesktop(mql.matches);
        update();
        mql.addEventListener('change', update);

        return () => mql.removeEventListener('change', update);
    }, []);

    useEffect(() => {
        const measure = () => {
            if (!isDesktop) {
                setContainerHeight(null);
                return;
            }

            if (stickyRef.current) {
                setContainerHeight(
                    stickyRef.current.offsetHeight +
                    SCROLL_BUDGET_PER_STEP * problems.length,
                );
            }
        };

        measure();
        window.addEventListener('resize', measure);

        return () => window.removeEventListener('resize', measure);
    }, [isDesktop]);

    const { scrollYProgress } = useScroll({
        target: containerRef,
        offset: ['start start', 'end end'],
    });

    useMotionValueEvent(scrollYProgress, 'change', (latest) => {
        if (!isDesktop) {
            return;
        }

        const next = Math.min(
            problems.length - 1,
            Math.max(0, Math.floor(latest * problems.length)),
        );
        setActive((prev) => (prev === next ? prev : next));
    });

    const goTo = (index: number) => {
        const container = containerRef.current;

        if (!container) {
            return;
        }

        const stepHeight = container.offsetHeight / problems.length;
        const top = container.offsetTop + stepHeight * index + 2;
        window.scrollTo({ top, behavior: 'smooth' });
    };

    const current = problems[active];
    const Icon = current.icon;

    return (
        <section className="bg-white">
            <div
                ref={containerRef}
                className="relative"
                style={{
                    height: isDesktop
                        ? containerHeight
                            ? `${containerHeight}px`
                            : `${problems.length * 100}svh`
                        : 'auto',
                }}
            >
                {/* Sticky: stays on screen (title included) for the whole
                    scroll-through so nothing gets covered by the fixed header.
                    Desktop-only — mobile renders this in normal flow. */}
                <div
                    ref={stickyRef}
                    className="py-8 lg:sticky lg:top-24 lg:py-14"
                >
                    <div className="mx-auto w-full max-w-7xl px-6 lg:px-8">
                        <Reveal className="mx-auto mb-3 max-w-2xl text-center lg:mb-10">
                            <div className="flex items-center justify-center gap-3">
                                <span className="h-px w-8 bg-brand-gold"></span>

                                <span className="text-sm font-bold tracking-wide text-brand-gold sm:text-lg">
                                    WHAT WE ADDRESS
                                </span>

                                <span className="h-px w-8 bg-brand-gold"></span>
                            </div>
                            <h2 className="mt-1 text-2xl font-extrabold text-brand-ink sm:mt-2 sm:text-4xl">
                                Problem We Solve
                            </h2>
                        </Reveal>

                        <div className="grid gap-3">
                            {/* Mobile/tablet: every problem as its own stacked card */}
                            <div className="space-y-6 lg:hidden">
                                {problems.map((problem) => {
                                    const ProblemIcon = problem.icon;

                                    return (
                                        <div
                                            key={problem.tab}
                                            className="relative overflow-hidden rounded-3xl border border-brand-ink/10 bg-white shadow-xl shadow-black/5"
                                        >
                                            <span className="absolute top-4 right-4 z-10 rounded-full bg-white/90 px-3 py-1 text-xs font-bold tracking-wide text-brand-ink uppercase shadow-sm backdrop-blur-sm">
                                                {problem.tab}
                                            </span>

                                            <div className="relative h-48 bg-gradient-to-br from-brand-gold/25 to-brand-amber/10">
                                                <img
                                                    src={problem.image}
                                                    alt={problem.title}
                                                    onError={(e) => {
                                                        e.currentTarget.style.opacity =
                                                            '0';
                                                    }}
                                                    style={{
                                                        objectPosition:
                                                            problem.imagePosition ??
                                                            'center',
                                                    }}
                                                    className="h-full w-full object-cover transition-opacity duration-300"
                                                />
                                            </div>

                                            <div className="p-6">
                                                <div className="grid h-11 w-11 place-items-center rounded-xl bg-gradient-to-br from-brand-gold/20 to-brand-amber/15">
                                                    <ProblemIcon className="h-5 w-5 text-brand-ink" />
                                                </div>

                                                <span className="mt-4 block text-sm font-bold tracking-wide text-brand-gold uppercase">
                                                    The Challenge
                                                </span>
                                                <h3 className="mt-2 text-xl font-bold text-brand-ink">
                                                    {problem.title}
                                                </h3>

                                                <div className="mt-4 border-t border-brand-ink/10 pt-4">
                                                    <span className="block text-sm font-bold tracking-wide text-brand-gold uppercase">
                                                        How We Help
                                                    </span>
                                                    <p className="mt-2 text-base leading-relaxed text-brand-ink/60">
                                                        {problem.body}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>

                            {/* Desktop: single card driven by the pinned scroll progress,
                                with a dot timeline pinned to its right so switching problems
                                never shifts the card's own size or position. */}
                            <div className="hidden lg:flex lg:items-center lg:gap-6">
                                <div className="relative h-[440px] flex-1 overflow-hidden rounded-3xl border border-brand-ink/10 bg-white shadow-xl shadow-black/5">
                                    <span className="absolute top-6 right-6 z-20 rounded-full bg-gradient-to-r from-brand-gold to-brand-amber px-4 py-1.5 text-sm font-bold tracking-wide text-brand-ink uppercase shadow-sm backdrop-blur-sm">
                                        {current.tab}
                                    </span>

                                    <AnimatePresence mode="wait">
                                        <motion.div
                                            key={active}
                                            initial={{ opacity: 0, y: 16 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            exit={{ opacity: 0, y: -16 }}
                                            transition={{
                                                duration: 0.45,
                                                ease: [0.16, 1, 0.3, 1],
                                            }}
                                            className="grid h-full sm:grid-cols-2"
                                        >
                                            <div className="relative h-full bg-gradient-to-br from-brand-gold/25 to-brand-amber/10">
                                                <img
                                                    src={current.image}
                                                    alt={current.title}
                                                    onError={(e) => {
                                                        e.currentTarget.style.opacity =
                                                            '0';
                                                    }}
                                                    style={{
                                                        objectPosition:
                                                            current.imagePosition ??
                                                            'center',
                                                    }}
                                                    className="h-full w-full object-cover transition-opacity duration-300"
                                                />
                                            </div>

                                            <div className="flex h-full flex-col justify-center p-8">
                                                <div className="grid h-12 w-12 place-items-center rounded-xl bg-gradient-to-br from-brand-gold/20 to-brand-amber/15">
                                                    <Icon className="h-6 w-6 text-brand-ink" />
                                                </div>

                                                <span className="mt-5 block text-sm font-bold tracking-wide text-brand-gold uppercase">
                                                    The Challenge
                                                </span>
                                                <h3 className="mt-2 text-2xl font-bold text-brand-ink">
                                                    {current.title}
                                                </h3>

                                                <div className="mt-5 border-t border-brand-ink/10 pt-5">
                                                    <span className="block text-sm font-bold tracking-wide text-brand-gold uppercase">
                                                        How We Help
                                                    </span>
                                                    <p className="mt-2 text-lg leading-relaxed text-brand-ink/60">
                                                        {current.body}
                                                    </p>
                                                </div>
                                            </div>
                                        </motion.div>
                                    </AnimatePresence>
                                </div>

                                {/* Carousel-style dot timeline */}
                                <div className="flex shrink-0 flex-col items-center gap-3">
                                    {problems.map((problem, i) => (
                                        <button
                                            key={problem.tab}
                                            onClick={() => goTo(i)}
                                            aria-label={problem.tab}
                                            className={`h-2.5 w-2.5 rounded-full transition-all duration-300 ${active === i
                                                    ? 'scale-125 bg-brand-gold'
                                                    : 'bg-brand-ink/20 hover:bg-brand-ink/40'
                                                }`}
                                        />
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}

function HowWeWorkSection() {
    return (
        <section id="approach" className="scroll-mt-24 bg-white py-24">
            <div className="mx-auto max-w-7xl px-6 lg:px-8">
                <SectionHeading
                    eyebrow="HOW WE WORK"
                    title="Efficient Integration in Searches"
                />
            </div>

            <div className="mx-auto max-w-7xl space-y-20 px-6 lg:px-8">
                {howWeWork.map((step, i) => {
                    const reversed = i % 2 === 1;
                    const Icon = step.icon;

                    return (
                        <div
                            key={step.num}
                            className={`flex flex-col items-center gap-10 lg:gap-16 ${reversed ? 'lg:flex-row-reverse' : 'lg:flex-row'}`}
                        >
                            <Reveal
                                x={reversed ? 40 : -40}
                                y={0}
                                className="relative w-full lg:w-[40%]"
                            >
                                <span
                                    aria-hidden
                                    className="pointer-events-none absolute -top-14 -left-2 text-[140px] leading-none font-black text-brand-gold/10 select-none sm:text-[180px]"
                                >
                                    {step.num}
                                </span>
                                <div className="relative">
                                    <div className="mb-4 inline-flex items-center gap-2 text-brand-gold">
                                        <Icon className="h-6 w-6" />
                                        <span className="text-sm font-bold tracking-wide uppercase">
                                            Step {step.num}
                                        </span>
                                    </div>
                                    <h3 className="text-4xl font-extrabold text-brand-ink">
                                        {step.title}
                                    </h3>
                                    <p className="mt-4 text-xl leading-relaxed text-brand-ink/60">
                                        {step.body}
                                    </p>
                                </div>
                            </Reveal>

                            <Reveal
                                x={reversed ? -40 : 40}
                                y={0}
                                delay={100}
                                className="w-full lg:w-[60%]"
                            >
                                <img
                                    src={step.image}
                                    alt={step.alt}
                                    loading="lazy"
                                    className="h-[340px] w-full rounded-2xl object-cover shadow-lg sm:h-[400px]"
                                />
                            </Reveal>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}

function CompetenciesBento() {
    const featured = competencies.find((c) => c.featured)!;
    const rest = competencies.filter((c) => !c.featured);

    return (
        <div id="competencies" className="scroll-mt-24">
            <div className="mb-10">
                <span className="text-base font-bold tracking-wide text-brand-gold">
                    OUR STRENGTHS
                </span>
                <h3 className="mt-2 text-3xl font-extrabold text-brand-ink sm:text-4xl">
                    Key Core Competencies
                </h3>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <Reveal className="relative min-h-[320px] overflow-hidden rounded-3xl shadow-lg shadow-black/10 lg:col-span-2">
                    <img
                        src={featured.image}
                        alt=""
                        aria-hidden
                        className="absolute inset-0 h-full w-full object-cover"
                    />
                    <div className="absolute inset-0 bg-gradient-to-t from-brand-ink/90 from-0% via-brand-ink/35 via-30% to-transparent to-60%" />
                    <div className="absolute inset-0 flex flex-col justify-end p-8 sm:p-10">
                        <div className="grid h-14 w-14 place-items-center rounded-xl bg-brand-gold/90">
                            <featured.icon className="h-7 w-7 text-brand-ink" />
                        </div>
                        <h4 className="mt-5 text-3xl font-extrabold text-white drop-shadow-sm sm:text-4xl">
                            {featured.title}
                        </h4>
                        <p className="mt-3 max-w-md text-lg text-white/85 drop-shadow-sm">
                            {featured.desc}
                        </p>
                    </div>
                </Reveal>

                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-1">
                    {rest.map((item, i) => (
                        <Reveal
                            key={item.title}
                            delay={i * 100}
                            className="group h-full rounded-2xl border-b-4 border-brand-gold bg-white p-6 shadow-sm shadow-black/5 transition-transform hover:-translate-y-1"
                        >
                            <div className="grid h-12 w-12 place-items-center rounded-xl bg-brand-gold/15 transition-colors group-hover:bg-brand-gold/25">
                                <item.icon className="h-6 w-6 text-brand-ink" />
                            </div>
                            <h4 className="mt-4 text-lg font-bold text-brand-ink">
                                {item.title}
                            </h4>
                            <p className="mt-2 leading-relaxed text-brand-ink/60">
                                {item.desc}
                            </p>
                        </Reveal>
                    ))}
                </div>
            </div>
        </div>
    );
}

// Horizontal-carousel variants for the pillar panel: entering pillars slide
// in from the direction of travel, exiting ones slide out the other way.
const strategySlideVariants = {
    enter: (direction: number) => ({ x: direction >= 0 ? '100%' : '-100%' }),
    center: { x: 0 },
    exit: (direction: number) => ({ x: direction >= 0 ? '-100%' : '100%' }),
};

function StrategiesGrid() {
    const containerRef = useRef<HTMLDivElement>(null);
    const stickyRef = useRef<HTMLDivElement>(null);
    const prevActiveRef = useRef(0);
    const [active, setActive] = useState(0);
    const [direction, setDirection] = useState(1);
    const [containerHeight, setContainerHeight] = useState<number | null>(null);
    // The pinned scroll-through effect is desktop-only — on mobile the
    // section just flows normally and every pillar renders as its own card.
    const [isDesktop, setIsDesktop] = useState(false);

    useEffect(() => {
        const mql = window.matchMedia('(min-width: 1024px)');
        const update = () => setIsDesktop(mql.matches);
        update();
        mql.addEventListener('change', update);

        return () => mql.removeEventListener('change', update);
    }, []);

    useEffect(() => {
        const measure = () => {
            if (!isDesktop) {
                setContainerHeight(null);

                return;
            }

            if (stickyRef.current) {
                setContainerHeight(
                    stickyRef.current.offsetHeight +
                    SCROLL_BUDGET_PER_STEP * strategies.length,
                );
            }
        };

        measure();
        window.addEventListener('resize', measure);

        return () => window.removeEventListener('resize', measure);
    }, [isDesktop]);

    const { scrollYProgress } = useScroll({
        target: containerRef,
        offset: ['start start', 'end end'],
    });

    // Pops the panel up slightly right as it becomes pinned, holds it there
    // for the whole scroll-through, then eases back to its original size
    // right at the end, just before it releases.
    const panelScale = useTransform(
        scrollYProgress,
        [0, 0.08, 0.92, 1],
        [1, 1.2, 1.2, 1],
    );

    useMotionValueEvent(scrollYProgress, 'change', (latest) => {
        if (!isDesktop) {
            return;
        }

        const next = Math.min(
            strategies.length - 1,
            Math.max(0, Math.floor(latest * strategies.length)),
        );

        if (next !== prevActiveRef.current) {
            setDirection(next > prevActiveRef.current ? 1 : -1);
            prevActiveRef.current = next;
            setActive(next);
        }
    });

    const goTo = (index: number) => {
        const container = containerRef.current;

        if (!container) {
            return;
        }

        const stepHeight = container.offsetHeight / strategies.length;
        const top = container.offsetTop + stepHeight * index + 2;
        window.scrollTo({ top, behavior: 'smooth' });
    };

    const current = strategies[active];
    const Icon = current.icon;

    return (
        <div id="strategies" className="scroll-mt-24">
            {/* Heading scrolls into view once and moves on — only the panel
                below stays pinned while you scroll through the pillars. */}
            <div className="mx-auto w-full max-w-6xl px-6 lg:px-8">
                <Reveal className="mb-10 max-w-2xl">
                    <span className="text-base font-bold tracking-wide text-brand-gold">
                        OUR APPROACH
                    </span>
                    <h3 className="mt-2 text-3xl font-extrabold text-brand-ink sm:text-4xl">
                        Comprehensive Recruitment Strategies
                    </h3>
                    <p className="mt-3 max-w-2xl text-lg text-brand-ink/60">
                        Six pillars that keep every engagement structured,
                        accountable, and efficient.
                    </p>
                </Reveal>
            </div>

            <div
                ref={containerRef}
                className="relative"
                style={{
                    height: isDesktop
                        ? containerHeight
                            ? `${containerHeight}px`
                            : `${strategies.length * 100}svh`
                        : 'auto',
                }}
            >
                {/* Sticky: only the panel (and, on mobile, the plain card
                    list) stays on screen for the scroll-through — the
                    heading above has already scrolled past by this point. */}
                <div
                    ref={stickyRef}
                    className="py-8 lg:sticky lg:top-24 lg:py-14"
                >
                    <div className="mx-auto w-full max-w-6xl px-6 lg:px-8">
                        {/* Mobile/tablet: every pillar as its own card with a thumbnail */}
                        <div className="grid gap-4 lg:hidden">
                            {strategies.map((item, i) => (
                                <Reveal key={item.title} delay={i * 60}>
                                    <div className="flex items-center gap-4 rounded-2xl border border-brand-ink/10 bg-white p-4 shadow-sm shadow-black/5">
                                        <div className="relative h-20 w-20 shrink-0 overflow-hidden rounded-xl">
                                            <img
                                                src={item.image}
                                                alt=""
                                                className="h-full w-full object-cover"
                                            />
                                            <div className="absolute inset-0 bg-brand-ink/25" />
                                            <div className="absolute inset-0 grid place-items-center">
                                                <item.icon className="h-6 w-6 text-white drop-shadow" />
                                            </div>
                                        </div>
                                        <div className="min-w-0">
                                            <h4 className="text-xl font-bold text-brand-ink">
                                                {item.title}
                                            </h4>
                                            <p className="mt-1 text-lg leading-relaxed text-brand-ink/60">
                                                {item.desc}
                                            </p>
                                        </div>
                                    </div>
                                </Reveal>
                            ))}
                        </div>

                        {/* Desktop: a single image+text panel — no separate
                            list — that slides to the next pillar as you
                            scroll past it. */}
                        <div className="hidden lg:block">
                            <motion.div
                                style={{ scale: panelScale }}
                                className="relative h-[520px] overflow-hidden rounded-3xl shadow-xl shadow-black/10"
                            >
                                <AnimatePresence
                                    custom={direction}
                                    initial={false}
                                >
                                    <motion.div
                                        key={active}
                                        custom={direction}
                                        variants={strategySlideVariants}
                                        initial="enter"
                                        animate="center"
                                        exit="exit"
                                        transition={{
                                            duration: 0.6,
                                            ease: [0.16, 1, 0.3, 1],
                                        }}
                                        className="absolute inset-0"
                                    >
                                        <img
                                            src={current.image}
                                            alt={current.title}
                                            className="h-full w-full object-cover"
                                        />
                                        <div className="absolute inset-0 bg-gradient-to-t from-brand-ink/90 via-brand-ink/30 to-transparent" />
                                        <div className="absolute inset-0 flex flex-col justify-end p-10 sm:p-14">
                                            <div className="grid h-14 w-14 place-items-center rounded-xl bg-brand-gold/90">
                                                <Icon className="h-7 w-7 text-brand-ink" />
                                            </div>
                                            <span className="mt-5 text-lg font-bold tracking-wide text-brand-gold uppercase">
                                                Pillar 0{active + 1}
                                            </span>
                                            <h4 className="mt-2 text-4xl font-extrabold text-white drop-shadow-sm sm:text-5xl">
                                                {current.title}
                                            </h4>
                                            <p className="mt-3 max-w-xl text-xl text-white/80">
                                                {current.desc}
                                            </p>
                                        </div>
                                    </motion.div>
                                </AnimatePresence>
                            </motion.div>

                            {/* Carousel-style dot timeline */}
                            <div className="mt-8 flex items-center justify-center gap-3">
                                {strategies.map((item, i) => (
                                    <button
                                        key={item.title}
                                        onClick={() => goTo(i)}
                                        aria-label={item.title}
                                        className={`h-2.5 rounded-full transition-all duration-300 ${active === i
                                                ? 'w-8 bg-brand-gold'
                                                : 'w-2.5 bg-brand-ink/20 hover:bg-brand-ink/40'
                                            }`}
                                    />
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function TalentProcess() {
    return (
        <div id="talent" className="scroll-mt-24">
            <div className="mb-10">
                <span className="text-base font-bold tracking-wide text-brand-gold">
                    OUR SOLUTIONS
                </span>
                <h3 className="mt-2 text-3xl font-extrabold text-brand-ink sm:text-4xl">
                    Comprehensive Talent Management Solutions
                </h3>
                <p className="mt-3 max-w-2xl text-lg text-brand-ink/60">
                    Explore our tailored consulting solutions for talent
                    management
                </p>
            </div>

            <div className="grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
                {talentSolutions.map((item, i) => (
                    <Reveal
                        key={item.title}
                        delay={i * 90}
                        className="group h-full overflow-hidden rounded-2xl border border-brand-ink/10 bg-white shadow-sm shadow-black/5 transition-all hover:-translate-y-1 hover:shadow-lg hover:shadow-brand-gold/10"
                    >
                        <div className="relative h-32 overflow-hidden sm:h-40">
                            <img
                                src={item.image}
                                alt=""
                                className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                            />
                            <div className="absolute inset-0 bg-gradient-to-t from-brand-ink/50 to-transparent" />
                            <div className="absolute top-3 left-3 grid h-8 w-8 place-items-center rounded-full bg-brand-gold text-xs font-extrabold text-brand-ink shadow-sm">
                                {i + 1}
                            </div>
                        </div>
                        <div className="p-4 sm:p-5">
                            <h4 className="text-lg font-bold text-brand-ink">
                                {item.title}
                            </h4>
                            <p className="mt-1.5 text-base leading-relaxed text-brand-ink/60">
                                {item.desc}
                            </p>
                        </div>
                    </Reveal>
                ))}
            </div>
        </div>
    );
}

function PricingGrid() {
    return (
        <div id="pricing" className="scroll-mt-24">
            <div className="mb-10">
                <span className="text-base font-bold tracking-wide text-brand-gold">
                    PRICING & SERVICES
                </span>
                <h3 className="mt-2 text-3xl font-extrabold text-brand-ink sm:text-4xl">
                    Proposals and Commercials Overview
                </h3>
                <p className="mt-3 max-w-2xl text-lg text-brand-ink/60">
                    Exploring recruitment services and efficiency tools
                </p>
            </div>

            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                {proposals.map((item, i) => (
                    <Reveal
                        key={item.title}
                        delay={i * 60}
                        className="group relative h-60 overflow-hidden rounded-2xl shadow-lg shadow-black/10 transition-transform hover:-translate-y-1"
                    >
                        <img
                            src={item.image}
                            alt=""
                            className="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                        />
                        <div className="absolute inset-0 bg-gradient-to-t from-brand-ink/95 via-brand-ink/60 to-brand-ink/10" />
                        <div className="relative flex h-full flex-col justify-end p-6">
                            <div className="grid h-10 w-10 place-items-center rounded-lg bg-brand-gold/90 transition-transform duration-300 group-hover:-translate-y-1">
                                <item.icon className="h-5 w-5 text-brand-ink" />
                            </div>
                            <h4 className="mt-3 text-lg font-bold text-white">
                                {item.title}
                            </h4>
                            <p className="mt-1.5 text-base leading-relaxed text-white/75">
                                {item.desc}
                            </p>
                        </div>
                    </Reveal>
                ))}

                <Reveal delay={proposals.length * 60}>
                    <a
                        href="#contact"
                        className="group flex h-60 flex-col justify-between rounded-2xl bg-brand-ink p-6 transition-transform hover:-translate-y-1"
                    >
                        <div>
                            <div className="grid h-10 w-10 place-items-center rounded-lg bg-brand-gold/90">
                                <ArrowRight className="h-5 w-5 text-brand-ink" />
                            </div>
                            <h4 className="mt-4 text-lg font-bold text-white">
                                Need a custom quote?
                            </h4>
                            <p className="mt-2 text-base leading-relaxed text-white/60">
                                Tell us about your hiring needs and we&apos;ll
                                tailor a plan.
                            </p>
                        </div>
                        <span className="inline-flex items-center gap-1.5 text-base font-bold text-brand-gold">
                            Get in Touch
                            <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-1" />
                        </span>
                    </a>
                </Reveal>
            </div>
        </div>
    );
}

function WhatWeOfferSection() {
    return (
        <section id="services" className="scroll-mt-12 bg-white py-12">
            <div className="mx-auto max-w-7xl px-6 lg:px-8">
                <SectionHeading
                    eyebrow="WHAT WE OFFER"
                    title="Everything Behind Every Successful Search"
                    sub="From leadership search to technology integration — a complete talent partner, not just a vendor."
                    center
                />

                <div className="space-y-24">
                    <CompetenciesBento />
                    <StrategiesGrid />
                    <TalentProcess />
                    <PricingGrid />
                </div>
            </div>
        </section>
    );
}

function ContactCta() {
    return (
        <section
            id="contact"
            className="relative flex min-h-[70vh] scroll-mt-24 items-center overflow-hidden"
        >
            <img
                src="https://images.unsplash.com/photo-1521791136064-7986c2920216?w=1920&q=80"
                alt="Connect with Pradhi Associates"
                loading="lazy"
                className="absolute inset-0 h-full w-full object-cover"
            />
            <div className="absolute inset-0 bg-gradient-to-b from-brand-ink/80 via-brand-ink/70 to-brand-ink/90" />

            <Reveal className="relative mx-auto max-w-3xl px-6 py-24 text-center lg:px-8">
                <h2 className="text-4xl leading-tight font-black text-white sm:text-6xl">
                    Connect with{' '}
                    <span className="text-brand-gold">Pradhi Associates</span>{' '}
                    Today
                </h2>
                <p className="mx-auto mt-6 max-w-xl text-xl text-white/70">
                    Thank you for your attention. For enquiries, reach out — we
                    respond within 24 hours.
                </p>

                <div className="mt-8 flex flex-wrap items-center justify-center gap-x-8 gap-y-3">
                    <a
                        href="mailto:laksh_madhu@pradhiassociates.com"
                        className="flex items-center gap-2.5 text-lg font-semibold text-white/90 hover:text-brand-gold"
                    >
                        <Mail className="h-5 w-5" />
                        laksh_madhu@pradhiassociates.com
                    </a>
                    <a
                        href="tel:+919910163337"
                        className="flex items-center gap-2.5 text-lg font-semibold text-white/90 hover:text-brand-gold"
                    >
                        <Phone className="h-5 w-5" />
                        +91 9910163337
                    </a>
                </div>

                <motion.div
                    whileHover={{ scale: 1.04, y: -2 }}
                    whileTap={{ scale: 0.97 }}
                    className="mt-10 inline-block"
                >
                    <a
                        href="mailto:laksh_madhu@pradhiassociates.com"
                        className="inline-flex items-center gap-2 rounded-md bg-brand-gold px-9 py-4 text-base font-extrabold text-brand-ink shadow-xl shadow-black/20"
                    >
                        Get in Touch
                        <ArrowRight className="h-5 w-5" />
                    </a>
                </motion.div>
            </Reveal>
        </section>
    );
}

export default function Home() {
    return (
        <ThemeLayout title="Pradhi Associates – Wise Choices Drive Excellence">
            <Hero />
            <ProblemSolveSection />
            <HowWeWorkSection />
            <WhatWeOfferSection />
            <ContactCta />
        </ThemeLayout>
    );
}
