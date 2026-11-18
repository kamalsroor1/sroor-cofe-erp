/**
 * Wrap a left-to-right token (email, URL, code) in Unicode isolates so it keeps its own order when it is
 * interpolated into right-to-left text: without it "l***@example.com" renders as "@example.com***l".
 */
export const ltrIsolate = (value) => (value ? `⁦${value}⁩` : '');
