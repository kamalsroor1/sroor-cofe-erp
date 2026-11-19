import { formatDate } from './formatters.js';
import { buildWhatsAppUrl } from './whatsappLink.js';

/**
 * SELL-03: builds the ready-to-send welcome message shown once after approving a signup request.
 * Never persists the password; callers hold it in memory only while the approval modal is open.
 */
export function buildSignupWelcomeMessage({
    translate,
    businessName = '',
    loginUrl = '',
    login = '',
    password = '',
    trialDays = 0,
    trialEndsAt = '',
    supportPhone = '',
    helpUrl = '',
    locale = 'ar',
}) {
    if (typeof translate !== 'function') return '';

    const formattedDate = formatDate(trialEndsAt, { locale, dateStyle: 'medium' }) || String(trialEndsAt || '');
    const days = Number(trialDays) || 0;

    const lines = [
        translate('signup.console.welcome_message.greeting', { business: String(businessName || '') }),
        loginUrl ? translate('signup.console.welcome_message.login_url', { url: loginUrl }) : '',
        login ? translate('signup.console.welcome_message.login', { login }) : '',
        password ? translate('signup.console.welcome_message.password', { password }) : '',
        days > 0 && formattedDate
            ? translate('signup.console.welcome_message.trial', { days, date: formattedDate })
            : '',
        helpUrl ? translate('signup.console.welcome_message.help', { url: helpUrl }) : '',
        supportPhone ? translate('signup.console.welcome_message.support', { phone: supportPhone }) : '',
    ];

    return lines.filter(Boolean).join('\n');
}

export function buildSignupWelcomeWhatsAppUrl(phone, message) {
    return buildWhatsAppUrl(phone, message);
}
