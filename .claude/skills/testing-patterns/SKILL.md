---
name: testing-patterns
description: Choose and perform focused validation for a Landing Page Manager Pro change.
---

# Testing Patterns

1. Locate the behavior's narrowest existing test or validation command, if one exists.
2. The repository currently has no configured test suite, PHPCS ruleset, or CI workflow; do not imply otherwise.
3. For PHP edits, run `php -l` on modified files when PHP CLI is available.
4. For behavior changes, verify the affected path in WordPress with the free plugin active and check dependency failure paths when relevant.
5. Report commands and outcomes accurately, including checks that could not run.