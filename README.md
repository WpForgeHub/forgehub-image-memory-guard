# ForgeHub Image Memory Guard

<p align="center">
  <img src="assets/banner-1544x500.png" alt="Image Memory Guard banner" width="100%" />
</p>

[![License](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-v1.1.8-purple.svg)](https://github.com/WpForgeHub/forgehub-image-memory-guard/releases/tag/v1.1.8)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b.svg)](https://wordpress.org/)
[![Tested](https://img.shields.io/badge/Tested-WP%207.1-brightgreen.svg)](https://wordpress.org/)
[![wp.org](https://img.shields.io/badge/wp.org-pending%20review-orange.svg)](https://wordpress.org/plugins/)
[![Freemius](https://img.shields.io/badge/Freemius-Pro%20available-0d9488.svg)](https://wpforgehub.com/)
[![Architecture](https://img.shields.io/badge/Architecture-Zero%20Bloat-06b6d4.svg)](https://wpforgehub.com/)

<p align="center">
  <a href="https://github.com/WpForgeHub/forgehub-image-memory-guard/releases/latest/download/forgehub-image-memory-guard-free.zip">
    <img src="https://img.shields.io/badge/📥_Download_Free_ZIP-v1.1.8-2563eb?style=for-the-badge&logo=wordpress&logoColor=white" alt="Download Free ZIP" />
  </a>
  <a href="https://wpforgehub.com/product/image-memory-guard/">
    <img src="https://img.shields.io/badge/🌐_Plugin_Homepage-WPForgeHub-0f172a?style=for-the-badge" alt="Plugin Page" />
  </a>
</p>

> ⚡ **Proactive memory protection for WordPress media library. Pre-calculates image memory budgets, prevents PHP fatal memory exhaustion, and enables safe auto-scaling.**

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

## 📊 Free vs Pro Comparison

| Feature | Community Free | Pro Commercial |
| :--- | :---: | :---: |
| Pre-Upload Memory Budget Inspection | ✅ | ✅ |
| Automatic Master Downscaling | ✅ | ✅ |
| Live System Diagnostics & Memory Calculator | ✅ | ✅ |
| Safe Memory Garbage Collection Hook | ✅ | ✅ |
| Background Chunked Resizing (Action Scheduler) | ❌ | ✅ |
| WebP & AVIF Dual Engine Conversion | ❌ | ✅ |
| WP-CLI Media Library Bulk Audit | ❌ | ✅ |
| EXIF Privacy Sanitizer (GPS/Device Removal) | ❌ | ✅ |

---

## 🛠️ Installation

1. Upload the `forgehub-image-memory-guard` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **Settings > Image Memory Guard** to view server diagnostics.

---

## 📄 License

GPLv2 or later. See [license.txt](license.txt) for details.
