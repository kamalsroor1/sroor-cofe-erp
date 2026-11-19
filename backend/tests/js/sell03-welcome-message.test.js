import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import {
    buildSignupWelcomeMessage,
    buildSignupWelcomeWhatsAppUrl,
} from '../../resources/js/helpers/signupWelcomeMessage.js';
import { fillPlaceholders } from '../../resources/js/helpers/whatsappLink.js';

function makeTranslator(dict) {
    return (key, replace = {}) => {
        const value = key
            .split('.')
            .reduce((node, part) => (node && typeof node === 'object' ? node[part] : undefined), dict);
        return typeof value === 'string' ? fillPlaceholders(value, replace) : key;
    };
}

const AR_DICT = {
    signup: {
        console: {
            welcome_message: {
                greeting: 'أهلاً بك في نظام إبراء — حساب محل ":business" جاهز الآن.',
                login_url: 'رابط الدخول: :url',
                login: 'اسم المستخدم: :login',
                password: 'كلمة المرور المؤقتة (تُغيَّر عند أول دخول): :password',
                trial: 'مدة التجربة: :days يوم (تنتهي في :date)',
                help: 'مركز المساعدة: :url',
                support: 'رقم الدعم: :phone',
            },
        },
    },
};

const EN_DICT = {
    signup: {
        console: {
            welcome_message: {
                greeting: 'Welcome to Ibraa — the account for ":business" is now ready.',
                login_url: 'Sign-in link: :url',
                login: 'Username: :login',
                password: 'Temporary password (must be changed on first sign-in): :password',
                trial: 'Trial period: :days days (ends on :date)',
                help: 'Help center: :url',
                support: 'Support phone: :phone',
            },
        },
    },
};

describe('SELL-03 signup welcome message', () => {
    it('builds the full welcome message in Arabic with all required fields', () => {
        const message = buildSignupWelcomeMessage({
            translate: makeTranslator(AR_DICT),
            businessName: 'محمصة النيل',
            loginUrl: 'https://nile.baraa-solutions.com/login',
            login: 'owner@nile.test',
            password: 'TempPass123!',
            trialDays: 14,
            trialEndsAt: '2026-11-26T12:00:00Z',
            supportPhone: '+201000000001',
            helpUrl: 'https://nile.baraa-solutions.com/help',
            locale: 'ar',
        });

        assert.match(message, /محمصة النيل/);
        assert.match(message, /https:\/\/nile\.baraa-solutions\.com\/login/);
        assert.match(message, /owner@nile\.test/);
        assert.match(message, /TempPass123!/);
        assert.match(message, /14 يوم/);
        assert.match(message, /https:\/\/nile\.baraa-solutions\.com\/help/);
        assert.match(message, /\+201000000001/);
    });

    it('builds the full welcome message in English', () => {
        const message = buildSignupWelcomeMessage({
            translate: makeTranslator(EN_DICT),
            businessName: 'Nile Roastery',
            loginUrl: 'https://nile.baraa-solutions.com/login',
            login: 'owner@nile.test',
            password: 'TempPass123!',
            trialDays: 14,
            trialEndsAt: '2026-11-26T12:00:00Z',
            supportPhone: '+201000000001',
            helpUrl: 'https://nile.baraa-solutions.com/help',
            locale: 'en',
        });

        assert.match(message, /Welcome to Ibraa — the account for "Nile Roastery" is now ready\./);
        assert.match(message, /Sign-in link: https:\/\/nile\.baraa-solutions\.com\/login/);
        assert.match(message, /Trial period: 14 days/);
        assert.match(message, /Help center: https:\/\/nile\.baraa-solutions\.com\/help/);
        assert.match(message, /Support phone: \+201000000001/);
    });

    it('properly URL-encodes special characters in business names for WhatsApp', () => {
        const businessName = 'محمصة & كافيه #1? 100%';
        const message = buildSignupWelcomeMessage({
            translate: makeTranslator(AR_DICT),
            businessName,
            loginUrl: 'https://roast.baraa-solutions.com/login',
            login: 'owner@roast.test',
            password: 'Pass&Word?=#1',
            trialDays: 14,
            trialEndsAt: '2026-11-26',
            supportPhone: '01012345678',
            helpUrl: 'https://roast.baraa-solutions.com/help',
            locale: 'ar',
        });

        const url = buildSignupWelcomeWhatsAppUrl('01012345678', message);
        assert.ok(url.startsWith('https://wa.me/201012345678?text='));
        const decoded = decodeURIComponent(url.split('?text=')[1]);
        assert.equal(decoded, message);
        assert.ok(decoded.includes(businessName));
    });
});
