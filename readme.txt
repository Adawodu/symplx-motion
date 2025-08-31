=== Symplx Motion ===
Contributors: symplx
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn static images of photo blog posts into motion-enhanced images using AI. Authors can enable motion for select images, or the entire post.

== Description ==

Features:

- Settings page under Settings → Symplx Motion
- Per-post toggle to enable motion for all images
- Shortcode `[symplx_motion id="123" effect="kenburns|parallax" prompt="..."]` for select images
- Media Library UI: "Generate Motion" action + Motion status column
- Background polling: Action Scheduler (if present) or WP‑Cron fallback
- REST routes to initiate generation (Mock and Replicate providers)
- Jobs dashboard: Settings → Symplx Motion Jobs lists all artifacts with status, progress, provider, and links
  - Filters by provider and status; bulk Refresh
  - Per-row actions: Refresh, Detail (logs/raw), Generate
  - Quick "Generate Motion" form at top

== Installation ==

1. Download or clone the folder `symplx-starter`.
2. Zip the folder and upload via Plugins → Add New → Upload Plugin, or place the folder into `wp-content/plugins/`.
3. Activate the plugin via the Plugins screen in WordPress.
4. Go to Settings → Symplx Motion to set defaults and provider keys.

== Usage ==

- Enable motion for an entire post using the sidebar meta box (Post edit screen) or set default mode to "All".
- Shortcode: Add `[symplx_motion id="ATTACHMENT_ID" effect="kenburns"]` to target a specific image.
- Media Library: In the list view, click "Generate Motion" on any image row.
- REST API:
  - POST `/wp-json/symplx/v1/motion/generate` with `attachment_id`, optional `prompt`, `effect`, `duration`, `fps`.
  - GET `/wp-json/symplx/v1/motion/status?attachment_id=123` to query status/URL.

== Changelog ==

= 0.1.0 =
* Motion-focused scaffold: settings, per-post toggle, shortcode, REST endpoints.
* Providers: Mock (CSS), Replicate (configurable model version).
