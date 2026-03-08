# Security Policy

## Supported Versions

We release security updates for the current major version of **Landing Page Manager Pro**. Only the latest release in the current line is supported with security fixes.

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

If you discover a security vulnerability in Landing Page Manager Pro, please report it responsibly:

1. **Do not** open a public GitHub issue for security-sensitive bugs.
2. **Email or contact** the maintainer privately:
   - GitHub: [@oduppinsjr](https://github.com/oduppinsjr) (you can use GitHub’s private vulnerability reporting if enabled, or send a direct message).
3. Include:
   - A clear description of the issue
   - Steps to reproduce
   - Impact (e.g. what an attacker could do)
   - Any suggested fix, if you have one

We will acknowledge your report and work on a fix. We may coordinate disclosure after a patch is available. We appreciate your help in keeping the plugin and its users safe.

## Security Practices

- All AJAX and REST endpoints use capability checks and nonces where appropriate.
- User input is sanitized and validated; post IDs and taxonomies are checked before use.
- Analytics data is restricted to users with `manage_options`.

If you have suggestions to improve security, we welcome them via the same private channel above or a non-security issue on the repo.
