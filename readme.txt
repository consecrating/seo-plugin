=== SEO Boost - Sitemap, IndexNow & Broken Links ===
Contributors: youragency
Tags: seo, sitemap, xml sitemap, indexnow, broken link checker, google
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An all-in-one SEO toolkit: automatic XML sitemaps, instant IndexNow submissions, and a broken link checker - wrapped in a modern dashboard.

== Description ==

SEO Boost bundles three high-impact SEO tools into one lightweight plugin with a clean, modern dashboard:

* **Automatic XML Sitemap** - A dynamically generated sitemap index (`/sitemap.xml`) covering your chosen post types and taxonomies, with optional image entries. It updates itself as you publish and is automatically added to `robots.txt`.
* **IndexNow instant indexing** - The moment you publish, update, or unpublish content, SEO Boost pings IndexNow-compatible search engines (Bing, Yandex, Seznam, Naver and partners) so your changes get discovered faster. Includes a manual submit tool and a "submit entire site" action.
* **Broken Link Checker** - Scans your content on a schedule, checks every outbound and internal link's HTTP status, and surfaces broken links in a filterable dashboard so you can fix them before they hurt your rankings.

= Why it helps you rank =

* Faster, more complete indexing via sitemaps + IndexNow.
* Better crawl efficiency and user experience by eliminating dead links.
* A single, modern control panel instead of three separate plugins.

== Installation ==

1. Upload the `seo-boost` folder to `/wp-content/plugins/`.
2. Activate the plugin through the *Plugins* menu in WordPress.
3. Open *SEO Boost* from the admin sidebar to configure your sitemap, IndexNow, and link scanning.

After activation the plugin flushes rewrite rules automatically. If your sitemap returns a 404, visit *Settings > Permalinks* and click *Save* once.

== Frequently Asked Questions ==

= Where is my sitemap? =
At `https://your-site.com/sitemap.xml`. Submit that URL to Google Search Console and Bing Webmaster Tools.

= Does IndexNow work with Google? =
Google does not currently consume IndexNow directly, but Bing, Yandex, Seznam and Naver do. Keep submitting your sitemap to Google Search Console for Google coverage.

= Will this conflict with Yoast or Rank Math? =
The sitemap module disables the WordPress core sitemap to avoid duplicates. If another SEO plugin also outputs a sitemap, disable one of them to avoid conflicts.

== Changelog ==

= 1.0.0 =
* Initial release: XML sitemap, IndexNow, broken link checker, and modern dashboard.
