<?php
/**
 * Dashboard app shell.
 *
 * The interactive UI is rendered by admin/js/admin.js into #seo-boost-view.
 * This file only prints the static layout (sidebar, header, mount points).
 *
 * @package SEO_Boost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="seob-wrap" id="seo-boost-app">

	<!-- Sidebar -->
	<aside class="seob-sidebar">
		<div class="seob-brand">
			<span class="seob-brand__mark" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="26" height="26" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M3 17l5-5 4 4 8-8" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
					<path d="M15 8h5v5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</span>
			<div class="seob-brand__text">
				<strong>SEO Boost</strong>
				<small>v<?php echo esc_html( SEO_BOOST_VERSION ); ?></small>
			</div>
		</div>

		<nav class="seob-nav" role="navigation">
			<a href="#/" class="seob-nav__item" data-route="/">
				<span class="seob-ic dashicons dashicons-dashboard"></span>
				<?php esc_html_e( 'Dashboard', 'seo-boost' ); ?>
			</a>
			<a href="#/sitemap" class="seob-nav__item" data-route="/sitemap">
				<span class="seob-ic dashicons dashicons-networking"></span>
				<?php esc_html_e( 'XML Sitemap', 'seo-boost' ); ?>
			</a>
			<a href="#/indexnow" class="seob-nav__item" data-route="/indexnow">
				<span class="seob-ic dashicons dashicons-superhero-alt"></span>
				<?php esc_html_e( 'IndexNow', 'seo-boost' ); ?>
			</a>
			<a href="#/search-console" class="seob-nav__item" data-route="/search-console">
				<span class="seob-ic dashicons dashicons-google"></span>
				<?php esc_html_e( 'Search Console', 'seo-boost' ); ?>
			</a>
			<a href="#/links" class="seob-nav__item" data-route="/links">
				<span class="seob-ic dashicons dashicons-admin-links"></span>
				<?php esc_html_e( 'Broken Links', 'seo-boost' ); ?>
				<span class="seob-nav__badge" id="seob-badge-links" hidden></span>
			</a>
			<a href="#/settings" class="seob-nav__item" data-route="/settings">
				<span class="seob-ic dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'Settings', 'seo-boost' ); ?>
			</a>
		</nav>

		<div class="seob-sidebar__foot">
			<div class="seob-tip">
				<strong><?php esc_html_e( 'Pro tip', 'seo-boost' ); ?></strong>
				<p><?php esc_html_e( 'Submit your sitemap to Google Search Console for the best coverage.', 'seo-boost' ); ?></p>
			</div>
		</div>
	</aside>

	<!-- Main -->
	<div class="seob-main">
		<header class="seob-topbar">
			<div class="seob-topbar__title">
				<h1 id="seob-page-title"><?php esc_html_e( 'Dashboard', 'seo-boost' ); ?></h1>
				<p id="seob-page-sub" class="seob-topbar__sub"><?php esc_html_e( 'Your site\'s SEO health at a glance.', 'seo-boost' ); ?></p>
			</div>
			<div class="seob-topbar__actions" id="seob-topbar-actions"></div>
		</header>

		<div class="seob-toast" id="seob-toast" role="status" aria-live="polite" hidden></div>

		<main class="seob-view" id="seo-boost-view">
			<div class="seob-loading">
				<span class="seob-spinner" aria-hidden="true"></span>
				<?php esc_html_e( 'Loading dashboard…', 'seo-boost' ); ?>
			</div>
		</main>
	</div>
</div>
