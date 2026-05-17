=== Category Icon ===
Contributors: pixelgrade, vlad.olaru, babbardel
Tags: category, taxonomy, term, icon, image
Requires at least: 5.9.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.4
License: GPL-2.0-or-later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A WordPress plugin to attach an icon and/or image to taxonomy terms.

== Description ==

A WordPress plugin to easily attach an icon to a category, tag or any other taxonomy term.

** Now supports a category, tag or any other taxonomy image field, also.

Please note that this plugin will not automatically output the icon or the image on the frontend of our site.

It is up to you to query and output in your theme using the provided getter functions: `get_term_icon_id()`, `get_term_icon_url()`, `get_term_image_id()`, `get_term_image_url()`.

== Installation ==

1. Install Category Icon either via the WordPress.org plugin directory, or by uploading the files to your `/wp-content/plugins/` directory
2. After activating Category Icon go and edit any category or term to see the upload field.
3. Now you can add or edit category, tags or any other taxonomy terms icons.

== Changelog ==

= 1.0.4 =
* Updated WordPress compatibility metadata for WordPress 7.0.
* Fixed Plugin Check errors in the release package.
* Hardened taxonomy term meta saving with explicit nonce and capability checks.
* Escaped taxonomy admin output and sanitized settings values.
* Scoped admin assets and SVG upload handling to Category Icon taxonomy screens.
* Replaced the legacy SVG media modal output buffer with attachment response data.

= 1.0.3 =
* Security: sanitize and strictly validate term icon / image IDs on save and escape them on output to prevent XSS via the `term_icon_value` and `term_image_value` fields, even for Editor-level users.

= 1.0.2 =
* Security: patched blind XXE/SSRF in SVG upload flow – external entity loading and network access are now disabled; malformed SVGs are rejected (fail-closed).

= 1.0.1 =
* Added sanitization for SVG uploads to prevent Cross-Site Scripting (XSS) vulnerabilities.
* Implemented secure file naming for uploaded SVG files by appending a random suffix to filenames.
* Enhanced upload handling to ensure SVG files are sanitized in place without altering the original upload path.
* Improved compatibility with WordPress file upload checks.

= 1.0.0 =
* Ensure compatibility with WordPress 5.9
* Add getter functions for term icon and image.
* Update build process.
* Clarify description and instructions about what this plugin does and doesn't do.

= 0.7.1 =
* Improve compatibility with WordPress 5.7

= 0.7.0 =
* We did several compatibility checks with Gutenberg so everything will work just fine
* Solved an issue where Category-Icon was having a conflict with other plugins in the Dashboard

= 0.6.0 =
* Add a category image field to be used as featured category image

= 0.5.0 =
* Deploy on wordpress.org

= 0.0.1 =
* Init Plugin
