=== WPFH Image Memory Guard ===
Contributors: wpforgehub
Tags: memory limit, image memory, upload error, media library, image compression
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Proactive memory protection for WordPress media library. Pre-calculates image memory budgets, prevents PHP fatal memory exhaustion, and enables safe auto-scaling.

== Description ==

One of the most frustrating errors in WordPress is uploading a high-resolution photo only to be hit with a generic "HTTP error" or a fatal crash:

`Fatal error: Allowed memory size of 134217728 bytes exhausted (tried to allocate 32000000 bytes)`

When an image is uploaded, WordPress and GD / Imagick must decompress the full uncompressed raster into RAM to generate intermediate thumbnails (thumbnail, medium, large, etc.). A 15 MB smartphone photo can easily require over 180 MB of RAM during decompression!

**WPFH Image Memory Guard** prevents this catastrophic crash before it happens:

* **Pre-Flight Memory Budget Inspection**: Inspects the binary headers of uploaded images (`getimagesize`) without allocating full canvas memory. Calculates exact memory requirement before processing begins.
* **Smart Auto-Downscaling**: Gracefully resizes oversized images down to manageable master dimensions instead of crashing the PHP process.
* **Informative Human-Friendly Error Handling**: If downscaling is disabled and an image exceeds server memory, gives the user an actionable warning instead of breaking the Media Library.
* **Dynamic Memory Elevation**: Safely bumps `wp_raise_memory_limit('image')` during active image processing.
* **Interactive Admin Budget Calculator**: Test any image resolution against your server memory in real-time.

### Key Features (Free Version)
* **Pre-Upload Memory Verification**: Protects against 500/fatal errors.
* **Automatic Master Downscaling**: Keeps photos within server safe zones.
* **Live System Diagnostics**: Monitor PHP memory limits, peak usage, and max safe resolutions.
* **Memory De-allocation**: Calls garbage collection after thumbnail generation cycles.

### Pro Features
* **Background Chunked Resizing**: Queue heavy resizing tasks in the background via Action Scheduler.
* **WebP & AVIF Dual Engine**: Auto-convert uploaded images to next-gen formats with CPU throttling.
* **WP-CLI Library Audit**: Scan entire media library via command line for memory-heavy attachments.
* **EXIF Privacy Sanitizer**: Strip location and camera metadata before decompression.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/wpfh-image-memory-guard`.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Visit **Settings > Image Memory Guard** to view server diagnostics and test image dimensions.

== Changelog ==

= 1.0.0 =
* Initial public release.
* Pre-flight upload memory budget calculation.
* Dynamic memory limit boost for image processing.
* Interactive memory budget calculator in admin.
