=== SEO Boost - Sitemap, IndexNow & Broken Links ===
Contributors: youragency
Tags: seo, sitemap, xml sitemap, indexnow, broken link checker, schema, local seo, structured data, content freshness, google
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An all-in-one SEO toolkit: XML sitemaps, instant IndexNow indexing, broken link checking, Local SEO / Schema structured data, and a content freshness audit - wrapped in a modern dashboard.

== Description ==

SEO Boost bundles the highest-impact SEO tools into one lightweight plugin with a clean, modern dashboard:

* **Automatic XML Sitemap** - A dynamically generated sitemap index (`/sitemap.xml`) covering your chosen post types and taxonomies, with optional image entries. It updates itself as you publish and is automatically added to `robots.txt`.
* **IndexNow instant indexing** - The moment you publish, update, or unpublish content, SEO Boost pings IndexNow-compatible search engines (Bing, Yandex, Seznam, Naver and partners) so your changes get discovered faster. Includes a manual submit tool and a "submit entire site" action.
* **Broken Link Checker** - Scans your content on a schedule, checks every outbound and internal link's HTTP status, and surfaces broken links in a filterable dashboard so you can fix them before they hurt your rankings.
* **Google Search Console** - One-click HTML-tag site verification plus direct links to submit your sitemap.
* **Local SEO & Schema** - Publishes Organization / Local Business / Professional Service structured data (NAP, geo, hours, area served, social profiles), a WebSite sitelinks search box, Article schema with freshness dates, and breadcrumbs - ideal for a local agency chasing the local pack and rich results.
* **Content Freshness audit** - Scores every page by how recently it was updated, flags aging and stale content, and gives you a prioritised, one-click list to refresh so your content keeps ranking.
* **AI Content Kit** - Exports any page as a ready-to-paste brief (with your business context, current content, headings, keywords and freshness) plus a goal-based prompt, so Claude, ChatGPT or any AI can write fresh content or rewrite old pages to rank higher. Single or bulk "content pack" export in Markdown or JSON.

= Why it helps you rank =

* Faster, more complete indexing via sitemaps + IndexNow.
* Rich results and local-pack visibility via structured data.
* Sustained rankings by keeping content fresh and eliminating dead links.
* A single, modern control panel instead of half a dozen separate plugins.

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

= 1.3.0 =
* New AI Content Kit: export any page or post as a ready-to-paste brief for Claude, ChatGPT or any AI assistant to write fresh content or rewrite old pages to rank higher.
* Each brief bundles your business/local context, the page's current content, heading outline, word count, freshness status and suggested focus keywords.
* Four goal-based prompt templates (rewrite & optimise, write fresh, expand thin content, generate title/meta/FAQs), in Markdown or JSON.
* Single-page copy/download plus bulk "content pack" export (up to 30 pages), with a live preview.

= 1.2.0 =
* New Local SEO & Schema module: outputs Organization / Local Business / Professional Service structured data (name, address, phone, geo, hours, area served, social profiles), WebSite + sitelinks search box, Article schema with published/modified dates, and BreadcrumbList - all as a connected JSON-LD graph.
* New Content Freshness audit: scores every page by how recently it was updated, flags aging and stale content, and gives you a filterable list with one-click "Refresh" links so your content stays current and keeps ranking.
* Dashboard now shows a content freshness score plus Local SEO and freshness cards.

= 1.1.0 =
* IndexNow submission log now expands to show the exact URLs submitted in each request.
* Added an IndexNow "Verify key" tool that checks the key file is publicly reachable, with clearer guidance on the HTTP 202 "validation pending" status.
* Added a Google Search Console section: one-click HTML-tag site verification (meta tag) plus direct links to add your property and submit the sitemap.

= 1.0.0 =
* Initial release: XML sitemap, IndexNow, broken link checker, and modern dashboard.
