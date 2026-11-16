=== Image Memory Guard ===
Contributors: wpforgehub
Tags: memory limit, image memory, upload error, media library, image compression
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Proactive memory protection for media library. Pre-calculates image memory budgets, prevents fatal memory exhaustion, and enables safe auto-scaling.

== Description ==

One of the most common and disruptive errors in WordPress is uploading a high-resolution photo only to encounter a generic "HTTP error" or fatal crash:

`Fatal error: Allowed memory size of 134217728 bytes exhausted (tried to allocate 32000000 bytes)`

When an image is uploaded, WordPress and GD / Imagick must decompress the full uncompressed raster into RAM to generate intermediate thumbnails (thumbnail, medium, large, etc.). A 15 MB photo can easily require over 180 MB of RAM during decompression!

**Image Memory Guard** prevents this catastrophic crash before it happens:

* **Pre-Flight Memory Budget Inspection**: Inspects the binary headers of uploaded images (`getimagesize`) without allocating full canvas memory. Calculates exact memory requirement before processing begins.
* **Smart Auto-Downscaling**: Gracefully resizes oversized images down to manageable master dimensions instead of crashing the PHP process.
* **Informative Human-Friendly Error Handling**: If downscaling is disabled and an image exceeds server memory, gives the user an actionable warning instead of breaking the Media Library.
* **Dynamic Memory Elevation**: Safely bumps `wp_raise_memory_limit('image')` during active image processing.
* **Interactive Admin Budget Calculator**: Test any image resolution against your server memory in real-time.

### Key Features (Free Version)
* **Pre-Upload Memory Verification**: Protects against 500 and fatal memory exhaustion errors.
* **Automatic Master Downscaling**: Keeps photos within server safe zones.
* **Live System Diagnostics**: Monitor PHP memory limits, peak usage, and max safe resolutions.
* **Memory De-allocation**: Calls garbage collection after thumbnail generation cycles.

### Pro Features
* **Background Chunked Resizing**: Queue heavy resizing tasks in the background via Action Scheduler.
* **WebP & AVIF Dual Engine**: Auto-convert uploaded images to next-gen formats with CPU throttling.
* **WP-CLI Library Audit**: Scan entire media library via command line for memory-heavy attachments.
* **EXIF Privacy Sanitizer**: Strip location and camera metadata before decompression.

== Installation ==

1. Upload the `forgehub-image-memory-guard` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **Settings > Image Memory Guard** to view server diagnostics and test image dimensions.

== Frequently Asked Questions ==

= How does Image Memory Guard protect against memory crashes? =
It intercepts the upload process before image decompression occurs, calculates the necessary RAM budget using binary header dimensions, and either elevates memory temporarily or downscales the master file to prevent PHP crashes.

= Does this plugin alter my original images? =
Only if the image exceeds your server's safe memory limit and you have enabled the Auto-Downscaling feature. Otherwise, it simply prevents the crash and displays an informative notification.

= Does this plugin require any external API or service? =
No. Image Memory Guard runs entirely locally on your WordPress server with zero external dependencies.

== Changelog ==

= 1.1.4 =
* Internal: option cleanup on uninstall also available through the Freemius after_uninstall hook (premium build).

= 1.1.3 =
* Fix: removed discouraged ini_set() call (Plugin Check warning); WordPress core applies the raised image memory limit.

= 1.1.2 =
* Renamed plugin and slug to meet WordPress.org naming rules (no restricted terms).

= 1.1.0 =
* Feature: Pre-flight upload memory budget calculation.
* Feature: Dynamic memory limit boost for image processing.
* Feature: Interactive memory budget calculator in WordPress admin.
* Enhancement: Safe memory de-allocation via garbage collection after processing.
* Initial stable directory release.
