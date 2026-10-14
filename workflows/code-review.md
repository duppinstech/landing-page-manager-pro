# Code Review Workflow

1. Read the full diff and understand the intended behavior.
2. Inspect only directly related code and tests needed to confirm control flow and contracts.
3. Check regressions, compatibility, authorization, nonce handling, validation, escaping, and data integrity where applicable.
4. Report actionable findings first, ordered by severity, with file references and impact.
5. If there are no findings, state that clearly and mention relevant validation gaps.
6. Do not silently edit reviewed changes or claim checks that were not run.