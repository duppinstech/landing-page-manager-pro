# Release Process

- Releases use GitHub Releases; the plugin's update checker reads GitHub release information.
- Before a release, verify the changelog, plugin header version, runtime version, supported requirements, and package contents agree.
- Build and install-test the ZIP, including the expected plugin directory and PHP entry point. Do not assume ignored ZIPs are safe to commit.
- Confirm the Git tag and release asset match the intended version and that the updater can consume the asset.
- Do not create tags, publish releases, or deploy to production without explicit authorization.
- After an authorized release, verify the published package can be installed or updated and record the result.