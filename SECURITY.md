# Security Policy

## Reporting a Vulnerability

If you discover a security vulnerability in `darvis/nuki`, **please do not open a
public GitHub issue**. Report it privately instead:

- via [GitHub private vulnerability reporting](https://github.com/ArvidDeJong/nuki/security/advisories/new), or
- by e-mail to <info@darvis.nl>.

Include:

- A description of the vulnerability and its impact.
- Steps to reproduce (proof-of-concept welcome).
- The affected version(s) of the package.
- Any suggested fix, if you have one.

You will receive an acknowledgement within **3 working days**. A patched release
will follow as soon as a fix is verified, after which the issue will be
disclosed publicly in the [CHANGELOG](CHANGELOG.md).

## Supported versions

Only the latest minor release of 1.x receives security fixes. Upgrade before reporting.

## What counts

This package holds NUKI API tokens and, with `auth_users` enabled, its own user accounts, sessions and smartlock permissions. A way to read a token, to act on a smartlock without the permission for it, or to take over an account counts as a security issue. So does a webhook that is accepted without a valid signature.
