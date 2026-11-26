/**
 * #230: the server decides (GET /billing/subscription -> subscription.can_request_renewal): false during a
 * trial and on a permanent free plan, where a renewal invoice is refused. An older payload without the flag
 * keeps the button.
 * @param {{subscription?: {can_request_renewal?: boolean}}|null} data
 */
export const canRequestRenewal = (data) => data?.subscription?.can_request_renewal !== false;
