'use strict';

// APP-7 / APP-4: installer signature policy of the desktop updater.
//
// Authenticode is NOT enforced until the Windows signing certificate exists (CTO Q11,
// [CTO-2026-10-08b]); integrity is guaranteed meanwhile by the SHA-256 + size published in
// app_versions and the https allow-list. When the certificate is bought:
//   1. set requireAuthenticode to true;
//   2. list the certificate thumbprint(s) (SHA-1 hex, as shown by Get-AuthenticodeSignature)
//      and/or the exact subject in trustedPublishers;
//   3. ship that build signed. Enforcing with an empty trustedPublishers list refuses every
//      installer on purpose (fails closed).
//
// Even with the policy off, an installer whose signature is present but broken (HashMismatch)
// is refused: that file was modified after signing.

module.exports = Object.freeze({
    requireAuthenticode: false,
    trustedPublishers: Object.freeze([]),
});
