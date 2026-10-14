# Feature Workflow

1. Define the user outcome, scope, compatibility constraints, and acceptance checks.
2. Trace the relevant Pro-plugin behavior and identify any free-plugin contract.
3. Implement the smallest focused change; preserve public APIs and stored data where possible.
4. Add or identify regression coverage. Use manual WordPress checks where automation is unavailable.
5. Run the narrowest useful validation and inspect the final diff.
6. Update documentation and `CHANGELOG.md` when behavior visible to users changes.
7. Report implementation, checks, and remaining risks. Release only with explicit authorization.