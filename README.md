# WPFH Image Memory Guard

[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://php.net/)
[![Release](https://img.shields.io/badge/Release-v1.1.0-purple.svg)](https://github.com/WpForgeHub/wpfh-image-memory-guard/releases/tag/v1.1.0)

**WPFH Image Memory Guard** eliminates PHP fatal memory exhaustion errors during WordPress media uploads and thumbnail generation.

---

## ⚡ The Problem

When WordPress uploads and resizes images, GD and ImageMagick decompress every pixel into raw uncompressed RAM. A typical 24-megapixel smartphone photo requires over 180MB of RAM to decompress and slice into intermediate thumbnails.

On servers with typical 128MB or 256MB PHP memory limits, this causes:
* `Fatal error: Allowed memory size exhausted`
* Media library upload stalls with cryptic "HTTP error"
* Orphaned attachment entries in the database

---

## 🛡️ The Solution

* **Pre-Flight Memory Calculation**: Inspects file headers via `getimagesize()` without memory allocation.
* **Safe Auto-Downscaling**: Scales master files down to safe dimensions before GD/Imagick triggers thumbnail generation.
* **Dynamic Memory Elevation**: Temporarily elevates memory limit during thumbnail generation.
* **Garbage Collection Hook**: Cleans up memory buffers after processing.
* **Interactive Budget Calculator**: Test any resolution against your host limits inside WP Admin.

---

## 🚀 Pro Features

* **Background Chunked Resizing**: Queue heavy resizing in background workers.
* **WebP & AVIF Auto-Conversion**: Generate next-gen images with CPU throttling.
* **WP-CLI Library Audit**: Inspect and optimize your existing media library from the terminal.
* **EXIF Privacy Sanitizer**: Strip GPS and device identifiers.

---

## 📄 License

GPL-2.0+ &copy; [WPForgeHub](https://wpforgehub.com).
