#!/usr/bin/env bash
#
# Notarize the vendored macOS dylib with Apple's notary service so it loads from
# a quarantined (downloaded) copy without "library load disallowed by system
# policy" (php-gui issue #3).
#
# A standalone .dylib CANNOT be stapled (only .app/.dmg/.pkg can carry a stapled
# ticket), so notarization registers the ticket with Apple and Gatekeeper checks
# it ONLINE on first load. That is enough to clear the policy block; users are
# online on first run in practice. (Offline-only users can still clear quarantine
# manually: `xattr -dr com.apple.quarantine lib/darwin/libui.dylib`.)
#
# Prerequisites (one-time):
#   1. A "Developer ID Application" certificate in your login keychain
#      (Apple Developer Program). Check: security find-identity -v -p codesigning
#   2. A notarytool credential profile. Create it once with an app-specific
#      password from https://account.apple.com (Sign-In and Security ->
#      App-Specific Passwords):
#
#        xcrun notarytool store-credentials php-gui-notary \
#          --apple-id "you@example.com" \
#          --team-id 9Z2L5FBZS3 \
#          --password "abcd-efgh-ijkl-mnop"
#
# Then run:  scripts/notarize-macos.sh
#
# Env overrides:
#   NOTARY_PROFILE       notarytool keychain profile name (default: php-gui-notary)
#   MACOS_SIGN_IDENTITY  Developer ID identity (default: first one found)
#   DYLIB                path to sign+notarize (default: lib/darwin/libui.dylib)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DYLIB="${DYLIB:-$ROOT/lib/darwin/libui.dylib}"
NOTARY_PROFILE="${NOTARY_PROFILE:-php-gui-notary}"

if [ "$(uname -s)" != "Darwin" ]; then
  echo "!! Notarization is macOS-only." >&2
  exit 1
fi
if [ ! -f "$DYLIB" ]; then
  echo "!! Not found: $DYLIB (build it first: composer build-lib)" >&2
  exit 1
fi

# Resolve a Developer ID Application signing identity.
SIGN_ID="${MACOS_SIGN_IDENTITY:-}"
if [ -z "$SIGN_ID" ]; then
  SIGN_ID="$(security find-identity -v -p codesigning 2>/dev/null \
    | grep -m1 'Developer ID Application' | sed -E 's/.*"(.*)"/\1/')" || true
fi
if [ -z "$SIGN_ID" ]; then
  echo "!! No 'Developer ID Application' identity found in the keychain." >&2
  echo "   Enroll in the Apple Developer Program and install the certificate," >&2
  echo "   then re-run. (security find-identity -v -p codesigning)" >&2
  exit 1
fi

echo "==> Signing with: $SIGN_ID"
codesign --force --timestamp --options runtime --sign "$SIGN_ID" "$DYLIB"
codesign --verify --strict --verbose=2 "$DYLIB"

# notarytool requires a container (zip/dmg/pkg); zip the dylib for submission.
TMPDIR_NOTARY="$(mktemp -d -t libui-notarize)"
TMPZIP="$TMPDIR_NOTARY/libui.zip"
trap 'rm -rf "$TMPDIR_NOTARY"' EXIT
/usr/bin/ditto -c -k --keepParent "$DYLIB" "$TMPZIP"

echo "==> Submitting to notary service (profile: $NOTARY_PROFILE)…"
if ! xcrun notarytool submit "$TMPZIP" --keychain-profile "$NOTARY_PROFILE" --wait; then
  echo >&2
  echo "!! Notarization failed. If the profile is missing, create it once with:" >&2
  echo "   xcrun notarytool store-credentials $NOTARY_PROFILE \\" >&2
  echo "     --apple-id 'you@example.com' --team-id 9Z2L5FBZS3 --password 'app-specific-pw'" >&2
  echo "   For rejection details: xcrun notarytool log <submission-id> --keychain-profile $NOTARY_PROFILE" >&2
  exit 1
fi

echo
echo "==> Notarized. (A bare .dylib can't be stapled; Gatekeeper verifies online"
echo "    on first load.) Signature:"
codesign -dv --verbose=2 "$DYLIB" 2>&1 | grep -E 'Authority|TeamIdentifier|Signature|flags' || true
echo "==> Done."
