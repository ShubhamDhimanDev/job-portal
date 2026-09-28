import { motion } from 'framer-motion';
import type { ReactNode } from 'react';

interface RevealProps {
    children: ReactNode;
    className?: string;
    delay?: number;
    y?: number;
    x?: number;
}

export default function Reveal({
    children,
    className = '',
    delay = 0,
    y = 28,
    x = 0,
}: RevealProps) {
    return (
        <motion.div
            className={className}
            initial={{ opacity: 0, y, x }}
            whileInView={{ opacity: 1, y: 0, x: 0 }}
            viewport={{ once: true, margin: '0px 0px -80px 0px' }}
            transition={{
                duration: 0.7,
                delay: delay / 1000,
                ease: [0.16, 1, 0.3, 1],
            }}
        >
            {children}
        </motion.div>
    );
}
