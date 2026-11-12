// SELL-02: turns the `subscription_banner` payload of /system/context into what the strip renders.
import { safeActionPath } from './readOnlyNotice.js';

const TONES = Object.freeze({ trial: 'info', expiring: 'warning', grace: 'warning', read_only: 'danger' });

function countdownKey(prefix, daysLeft) {
    if (daysLeft === 0) return `${prefix}.today`;
    if (daysLeft === 1) return `${prefix}.tomorrow`;
    return `${prefix}.days_left`;
}

/**
 * @param {object|null} banner  `data.subscription_banner` (the server already decided who sees it)
 * @returns {{state:string, tone:'info'|'warning'|'danger', textKey:string, params:object, actionPath:string|null}|null}
 */
export function describeSubscriptionBanner(banner) {
    const tone = TONES[banner?.state];
    if (!tone) return null;

    const days = Number.isFinite(Number(banner.days_left)) ? Math.max(0, Math.trunc(Number(banner.days_left))) : 0;
    let textKey;
    if (banner.state === 'read_only') {
        textKey = 'subscription.banner.read_only';
    } else if (banner.state === 'grace') {
        textKey = `subscription.banner.grace.${banner.kind === 'trial' ? 'trial' : 'subscription'}`;
    } else {
        textKey = countdownKey(`subscription.banner.${banner.state}`, days);
    }

    return {
        state: banner.state,
        tone,
        textKey,
        params: { days },
        actionPath: safeActionPath(banner.action_path),
    };
}
