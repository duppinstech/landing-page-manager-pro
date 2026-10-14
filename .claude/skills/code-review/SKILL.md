---
name: code-review
description: Review a Landing Page Manager Pro change for correctness, security, compatibility, and regression risk.
---

# Code Review

1. Read the diff and identify the behavior it changes.
2. Follow relevant call paths only as far as needed to verify the behavior.
3. Check WordPress permissions, nonce handling, sanitization, escaping, and PHP 7.4 compatibility where applicable.
4. Report concrete findings first, ordered by severity, with file references and impact.
5. State relevant test gaps. If there are no findings, say so clearly.

Do not modify files during a review.