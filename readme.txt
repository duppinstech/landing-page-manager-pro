=== Landing Page Manager Pro ===
Contributors: duppinstech
Requires at least: 5.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Page views, conversions, link clicks, engagement, heatmaps, rage clicks, outbound clicks, CTA views, and form tracking for Landing Page Manager, with bot and internal-traffic exclusion and privacy and consent controls.

== Description ==

Landing Page Manager Pro records how your landing pages perform. It counts page views, conversions (a click on a phone link or call button), link clicks, time on page, scroll depth, mouse movement as a heatmap, rage clicks, outbound clicks, call-to-action views, and form starts and submissions. It does not store what was typed into a form.

Landing Page Manager must be installed and active. With Landing Page Manager 1.0.13 or later, open **Landing Pages → Analytics** for the numbers and **Landing Pages → Tracking** for the controls. With an older Landing Page Manager, Pro adds those screens itself.

Pro is designed to store only aggregate counts per landing page. It sets no cookies and does not store IP addresses or user agents. Link addresses are stored without query strings or fragments. Suggested wording for your privacy policy is added under **Settings → Privacy**.

Updates are delivered through GitHub Releases of duppinstech/landing-page-manager-pro. To check for updates, the plugin contacts api.github.com, which sees the site's IP address; GitHub's terms and privacy statement apply. The Analytics screen loads Chart.js 4.4.1 from cdn.jsdelivr.net (checked against a fixed integrity hash), so jsDelivr sees the administrator's IP address and the site's address.

= What is recorded =

* Page views, including pages served from a cache
* Conversions, which stay on; excluded visits are not counted
* Link clicks, outbound clicks, and call-to-action views
* Time on page, scroll depth, heatmap, and rage clicks
* Form starts and submissions

= Who is left out =

By default, Pro does not count previews, logged-in users who can edit content (contributors, authors, editors, and administrators), known bots, or visitors whose browser sends Global Privacy Control or Do Not Track. You can also wait until the visitor consents to statistics. Change this under **Landing Pages → Tracking**.

Support: [support@duppinstech.com](mailto:support@duppinstech.com). Made by [Duppins Technology](https://duppinstech.com). The Landing Page Manager user guide is at [duppinstech.com/knowledge-base/landing-page-manager/](https://duppinstech.com/knowledge-base/landing-page-manager/).

== Installation ==

1. Install and activate **Landing Page Manager**.
2. Download `landing-page-manager-pro.zip` from the latest release.
3. In wp-admin, go to **Plugins → Add New → Upload Plugin**, choose the zip, and click **Install Now**.
4. Activate **Landing Page Manager Pro**. On first activation, the tracking options are turned on.
5. Open **Landing Pages → Tracking** and review what is recorded and who is left out.

= Updates =

New releases appear under **Dashboard → Updates**. **Check again** forces a fresh check.

== Frequently Asked Questions ==

= Does this work without Landing Page Manager? =

No. Pro does not load until Landing Page Manager is installed and active.

= Where are the settings? =

**Landing Pages → Tracking**. Administrators can open it. With Landing Page Manager before 1.0.13, Pro adds that screen itself. Choices saved in older versions carry over.

= Where are the numbers? =

**Landing Pages → Analytics**, for administrators. With Landing Page Manager before 1.0.13, the screen is named Pro Analytics. Old links to Pro Analytics and to the Conversions screen open Analytics.

= Will my counts change after an update? =

They can. From 1.0.3, previews, logged-in editors, bots, and visitors who send privacy signals are left out, and views on cached pages are counted. Take a database backup before updating to 1.0.5 or later: analytics move into two new tables, and existing data is copied on the first page load. Purge the page cache after updating so pages load the current tracking script.

= What is deleted when I delete the plugin? =

Deleting the plugin removes its update cache. Turn on **Delete data on uninstall** under **Landing Pages → Tracking** to also remove Pro's settings, including your tracking choices, and Pro's analytics tables. The per-page totals shown in Landing Page Manager are kept.

= Does Pro set cookies? =

No. It is designed to store only aggregate counts per landing page. Each page view gets a random identifier that stays in the browser's memory; it is not stored in cookies or local storage. Whether your site needs visitor consent depends on the laws that apply to it.

== Screenshots ==

1. Landing Pages, then Tracking: what is recorded, who is left out, and whether Pro's data is deleted when the plugin is deleted.
2. Landing Pages, then Analytics: the overview, with summary counts and charts. The other tabs list clients, pages, templates, links, engagement, the heatmap, and rage clicks.
3. On Plugins, Settings and Help sit beside Deactivate. Sponsor follows the author, then View details and Support.

== Changelog ==

= 1.0.6 =

* With Landing Page Manager 1.0.13 or later, analytics are under **Landing Pages → Analytics** and settings are under **Landing Pages → Tracking**. Older links still open the right screen.
* The Analytics dashboard escapes text placed in HTML attributes.
* Update Landing Page Manager to 1.0.13 as well. It includes a security fix for its Analytics screen.

= 1.0.5 =

* Counts are no longer lost when several visitors are tracked at the same moment.
* Analytics are stored in two database tables. Existing data is copied on the first page load after updating. Take a database backup before updating.
* Deleting the plugin removes the analytics tables only when **Delete data on uninstall** is on.

= 1.0.4 =

* Tracking options moved to **Landing Pages → Tracking**. Existing choices carry over.
* Conversions are part of Analytics.

= 1.0.3 =

* Views on cached pages are counted. Previews, logged-in editors, bots, and visitors who send privacy signals are left out by default.
* Outbound clicks, call-to-action views, and form starts and submissions are recorded.
* Updates come from GitHub Releases.

== Upgrade Notice ==

= 1.0.6 =
Security fix for the Analytics dashboard. Update Landing Page Manager to 1.0.13 as well: Pro's analytics then appear under Landing Pages > Analytics, and Pro Settings becomes Landing Pages > Tracking (your settings carry over).

= 1.0.5 =
Analytics move to two new database tables; existing data is copied automatically on the first page load after updating, and your numbers stay the same. Counts are no longer lost on busy pages. Take a database backup before updating.

= 1.0.4 =
Tracking options moved to Landing Pages > Pro Settings > What is tracked; your choices carry over. Conversions is now part of Landing Pages > Pro Analytics. Update Landing Page Manager to 1.0.7 or later to hide its old Conversion Tracking tab.

= 1.0.3 =
Counts may be lower after updating: previews, logged-in users who can edit content (contributors, authors, editors, administrators), bots, and visitors who send privacy signals are no longer counted, while views on cached pages now are. Review Landing Pages > Tracking, and purge the page cache after updating. Custom tracking code must now send a session token. Stored link clicks that differ only by query string are merged once.

== Uninstall ==

Deleting the plugin removes its update cache. Turn on Delete data on uninstall under Landing Pages > Tracking to also remove Pro's own settings, including your tracking choices. That also deletes Pro's analytics tables; the per-page totals shown in Landing Page Manager are always kept.
