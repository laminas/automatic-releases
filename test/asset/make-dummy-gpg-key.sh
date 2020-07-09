#!/usr/bin/env bash
# Creates a dummy passwordless GPG key that we can use to run tests around commit/tag signing. None of these
# keys should be relied upon: they are only to be used for testing, and shouldn't be trusted on a production
# nor development machine.

set -euo pipefail
IFS=$'\n\t'

# Skipped: the following manipulates a `.gnupg` directory, which isn't necessary for our dirty purposes
# set -x
# rm -rf .gnupg
# mkdir -m 0700 .gnupg
# touch .gnupg/gpg.conf
# chmod 600 .gnupg/gpg.conf
# tail -n +4 /usr/share/gnupg2/gpg-conf.skel > .gnupg/gpg.conf
#
# cd .gnupg

# I removed this line since these are created if a list key is done.
# touch .gnupg/{pub,sec}ring.gpg
gpg2 --list-keys


cat >keydetails <<EOF
    %echo Generating a basic OpenPGP key
    Key-Type: RSA
    Key-Length: 2048
    Subkey-Type: RSA
    Subkey-Length: 2048
    Name-Real: User 1
    Name-Comment: User 1
    Name-Email: user@1.com
    Expire-Date: 0
    %no-ask-passphrase
    %no-protection
    # %pubring pubring.kbx
    # %secring trustdb.gpg
    # Do a commit here, so that we can later print "done" :-)
    %commit
    %echo done
EOF

gpg2 --verbose --batch --gen-key keydetails

# Set trust to 5 for the key so we can encrypt without prompt.
echo -e "5\ny\n" |  gpg2 --command-fd 0 --expert --edit-key user@1.com trust;

# Test that the key was created and the permission the trust was set.
gpg2 --list-keys

# Test the key can encrypt and decrypt.
gpg2 -e -a -r user@1.com keydetails

# Delete the options and decrypt the original to stdout.
rm keydetails
gpg2 -d keydetails.asc
rm keydetails.asc
