# Bug-Fix Workflow

1. Capture a reproducible symptom and expected behavior.
2. Trace the smallest relevant code path and identify the root cause.
3. Add a focused regression check when a test harness is available; otherwise define a repeatable manual check.
4. Make the smallest correction that fixes the cause without changing unrelated behavior.
5. Run the focused check, inspect the diff, and note unverified cases.
6. Update the changelog when the fix affects released user behavior.
7. Run the [department review](department-review.md) (`/department-review`) before reporting the fix as done.