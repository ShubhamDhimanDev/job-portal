const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/** Mirrors JobApplication::PHONE_PATTERN on the server. */
export const PHONE_PATTERN_SOURCE = String.raw`\+?(?:[\s().\-]*\d){7,15}[\s().\-]*`;

const PHONE_PATTERN = new RegExp(`^${PHONE_PATTERN_SOURCE}$`);

export const PHONE_ERROR =
    'Enter a valid phone number (7-15 digits, optional + prefix).';

export function validateCandidateEmail(email: string): string | null {
    const value = email.trim();

    if (value === '') {
        return 'The email field is required.';
    }

    return EMAIL_PATTERN.test(value) ? null : 'Enter a valid email address.';
}

export function validateCandidatePhone(phone: string): string | null {
    const value = phone.trim();

    if (value === '') {
        return 'The phone field is required.';
    }

    return PHONE_PATTERN.test(value) ? null : PHONE_ERROR;
}

/**
 * Returns only the errors that apply, ready for useForm's setError.
 */
export function validateCandidateContact(values: {
    email: string;
    phone: string;
}): Partial<Record<'email' | 'phone', string>> {
    const errors: Partial<Record<'email' | 'phone', string>> = {};
    const email = validateCandidateEmail(values.email);
    const phone = validateCandidatePhone(values.phone);

    if (email) {
        errors.email = email;
    }

    if (phone) {
        errors.phone = phone;
    }

    return errors;
}
