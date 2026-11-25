#!/usr/bin/env bash
# Creates the Ibraa Android release keystore OUTSIDE the repo and the properties file
# app/build.gradle reads (~/.ibraa/keystore.properties). Run it yourself in a terminal:
# it asks for the password and never prints it. Back up ~/.ibraa afterwards: losing the
# keystore means no update can ever be installed over the apps signed with it.
set -euo pipefail

dir="${HOME}/.ibraa"
store="${dir}/ibraa-release.jks"
props="${dir}/keystore.properties"
alias_name="ibraa"

if [[ -e "$store" ]]; then
    echo "Keystore already exists at $store; refusing to overwrite it." >&2
    exit 1
fi

umask 077
mkdir -p "$dir"

read -r -s -p "Keystore password (6+ chars): " pass; echo
read -r -s -p "Repeat the password: " pass2; echo
[[ "$pass" == "$pass2" ]] || { echo "Passwords do not match." >&2; exit 1; }
[[ ${#pass} -ge 6 ]] || { echo "Password is shorter than 6 characters." >&2; exit 1; }

export IBRAA_KS_PASS="$pass"
keytool -genkeypair \
    -keystore "$store" -storetype PKCS12 \
    -alias "$alias_name" -keyalg RSA -keysize 4096 -validity 10000 \
    -dname "CN=Ibraa, O=Ibraa, C=EG" \
    -storepass:env IBRAA_KS_PASS -keypass:env IBRAA_KS_PASS \
    < /dev/null
unset IBRAA_KS_PASS

# PKCS12 uses one password for the store and the key.
{
    echo "storeFile=${store}"
    echo "storePassword=${pass}"
    echo "keyAlias=${alias_name}"
    echo "keyPassword=${pass}"
} > "$props"
chmod 600 "$store" "$props"
unset pass pass2

echo "Created $store and $props (mode 600)."
echo "Back up the whole $dir folder somewhere safe (password manager / encrypted drive)."
