/**
 * SEO Boost - dashboard front-end.
 *
 * A tiny dependency-free hash-router SPA. Each route renders into
 * #seo-boost-view and talks to the REST API defined in class-seo-boost-rest.php.
 */
( function () {
	'use strict';

	var cfg = window.SEOBoost || {};
	var view = document.getElementById( 'seo-boost-view' );
	var titleEl = document.getElementById( 'seob-page-title' );
	var subEl = document.getElementById( 'seob-page-sub' );
	var actionsEl = document.getElementById( 'seob-topbar-actions' );

	/* ---------------- API helpers ---------------- */

	function api( path, method, body ) {
		var opts = {
			method: method || 'GET',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce,
			},
			credentials: 'same-origin',
		};
		if ( body ) {
			opts.body = JSON.stringify( body );
		}
		return fetch( cfg.root + path, opts ).then( function ( r ) {
			return r.json().then( function ( data ) {
				if ( ! r.ok ) {
					throw data;
				}
				return data;
			} );
		} );
	}

	/* ---------------- Small utilities ---------------- */

	function h( html ) {
		var t = document.createElement( 'template' );
		t.innerHTML = html.trim();
		return t.content.firstChild;
	}

	function esc( s ) {
		var d = document.createElement( 'div' );
		d.textContent = s == null ? '' : String( s );
		return d.innerHTML;
	}

	function toast( message, type ) {
		var el = document.getElementById( 'seob-toast' );
		el.textContent = message;
		el.className = 'seob-toast' + ( type ? ' seob-toast--' + type : '' );
		el.hidden = false;
		clearTimeout( el._t );
		el._t = setTimeout( function () {
			el.hidden = true;
		}, 3200 );
	}

	function num( n ) {
		return ( n || 0 ).toLocaleString();
	}

	function setHeader( title, sub, actionsHTML ) {
		titleEl.textContent = title;
		subEl.textContent = sub || '';
		actionsEl.innerHTML = actionsHTML || '';
	}

	function loading() {
		view.innerHTML = '<div class="seob-loading"><span class="seob-spinner"></span> Loading…</div>';
	}

	function badgeForStatus( status, code ) {
		var map = {
			ok: 'ok',
			broken: 'broken',
			redirect: 'redirect',
			pending: 'pending',
			error: 'error',
		};
		var cls = map[ status ] || 'pending';
		var label = status.charAt( 0 ).toUpperCase() + status.slice( 1 );
		if ( code ) {
			label += ' · ' + code;
		}
		return '<span class="seob-badge seob-badge--' + cls + '">' + esc( label ) + '</span>';
	}

	/* ---------------- Views ---------------- */

	var Views = {};

	/* ===== Dashboard ===== */
	Views[ '/' ] = function () {
		loading();
		setHeader( 'Dashboard', "Your site's SEO health at a glance." );
		api( '/stats' ).then( function ( s ) {
			var brokenMeta =
				s.links.broken > 0
					? '<span style="color:var(--seob-red)">' + num( s.links.broken ) + ' need attention</span>'
					: '<span style="color:var(--seob-green)">All healthy</span>';

			view.innerHTML = '';
			var grid = h( '<div class="seob-grid seob-grid--stats"></div>' );

			grid.appendChild(
				statTile( 'brand', 'networking', num( s.sitemap.subsitemaps ), 'Sitemaps generated', s.sitemap.enabled ? 'Auto-updating' : 'Disabled' )
			);
			grid.appendChild(
				statTile( 'blue', 'superhero-alt', num( s.indexnow.submissions ), 'IndexNow submissions', s.indexnow.enabled ? 'Instant indexing on' : 'Disabled' )
			);
			grid.appendChild(
				statTile( 'green', 'yes-alt', num( s.links.ok ), 'Healthy links', num( s.links.total ) + ' total checked' )
			);
			grid.appendChild( statTile( s.links.broken > 0 ? 'red' : 'green', 'admin-links', num( s.links.broken ), 'Broken links', brokenMeta ) );

			var fr = s.freshness || { stale: 0, aging: 0, avg_score: 100, total: 0 };
			var freshColor = fr.stale > 0 ? 'amber' : 'green';
			var freshMeta =
				fr.stale > 0
					? '<span style="color:var(--seob-amber)">' + num( fr.stale ) + ' need refreshing</span>'
					: '<span style="color:var(--seob-green)">Content is current</span>';
			grid.appendChild( statTile( freshColor, 'backup', ( fr.avg_score != null ? fr.avg_score : 100 ) + '%', 'Content freshness', freshMeta ) );
			view.appendChild( grid );

			/* Feature rows */
			var row = h( '<div class="seob-grid seob-grid--2 seob-mt"></div>' );

			/* Sitemap card */
			var sm = h( '<div class="seob-card"></div>' );
			sm.innerHTML =
				'<div class="seob-card__head"><div><h2>XML Sitemap</h2><p>Search engines use this to discover your pages.</p></div>' +
				'<span class="seob-badge seob-badge--' + ( s.sitemap.enabled ? 'on">Active' : 'off">Off' ) + '</span></div>' +
				'<div class="seob-keybox"><code>' + esc( s.sitemap.url ) + '</code></div>' +
				'<div class="seob-mt"><a class="seob-btn seob-btn--ghost seob-btn--sm" target="_blank" rel="noopener" href="' +
				esc( s.sitemap.url ) + '"><span class="dashicons dashicons-external"></span> View sitemap</a> ' +
				'<a class="seob-btn seob-btn--ghost seob-btn--sm" href="#/sitemap"><span class="dashicons dashicons-admin-generic"></span> Configure</a></div>';
			row.appendChild( sm );

			/* IndexNow card */
			var inw = h( '<div class="seob-card"></div>' );
			var last = s.indexnow.last_submission;
			var lastHTML = last
				? '<div class="seob-list__row"><span>Last submission</span><small>' +
				  esc( last.time ) + ' · ' + num( last.count ) + ' URL(s) · HTTP ' + esc( last.code ) + '</small></div>'
				: '<p class="seob-muted">No submissions yet. Publish or update a post to trigger one automatically.</p>';
			inw.innerHTML =
				'<div class="seob-card__head"><div><h2>IndexNow</h2><p>Instantly ping Bing, Yandex &amp; more on every change.</p></div>' +
				'<span class="seob-badge seob-badge--' + ( s.indexnow.enabled ? 'on">Active' : 'off">Off' ) + '</span></div>' +
				lastHTML +
				'<div class="seob-mt"><button class="seob-btn seob-btn--primary seob-btn--sm" id="seob-quick-submitall"><span class="dashicons dashicons-update"></span> Submit all URLs</button> ' +
				'<a class="seob-btn seob-btn--ghost seob-btn--sm" href="#/indexnow"><span class="dashicons dashicons-admin-generic"></span> Manage</a></div>';
			row.appendChild( inw );

			view.appendChild( row );

			/* Second feature row: Local SEO + Content freshness */
			var row2 = h( '<div class="seob-grid seob-grid--2 seob-mt"></div>' );

			var sc = s.schema || { enabled: false, type: 'Organization', configured: false };
			var localCard = h( '<div class="seob-card"></div>' );
			localCard.innerHTML =
				'<div class="seob-card__head"><div><h2>Local SEO &amp; Schema</h2><p>Structured data helps you win rich results &amp; the local pack.</p></div>' +
				'<span class="seob-badge seob-badge--' + ( sc.enabled ? 'on">Active' : 'off">Off' ) + '</span></div>' +
				( sc.configured
					? '<p class="seob-muted">Publishing <strong>' + esc( sc.type ) + '</strong> schema with your business details.</p>'
					: '<div class="seob-note seob-note--warn"><span class="dashicons dashicons-info-outline"></span><div><p class="seob-hint" style="margin:0">Add your business name, address &amp; phone to unlock local search results.</p></div></div>' ) +
				'<div class="seob-mt"><a class="seob-btn seob-btn--primary seob-btn--sm" href="#/local-seo"><span class="dashicons dashicons-location-alt"></span> ' +
				( sc.configured ? 'Manage schema' : 'Set up Local SEO' ) + '</a></div>';
			row2.appendChild( localCard );

			var freshCard = h( '<div class="seob-card"></div>' );
			freshCard.innerHTML =
				'<div class="seob-card__head"><div><h2>Content Freshness</h2><p>Fresh, updated content ranks better over time.</p></div></div>' +
				'<div class="seob-list">' +
				'<div class="seob-list__row"><span>Fresh</span><strong style="color:var(--seob-green)">' + num( fr.fresh ) + '</strong></div>' +
				'<div class="seob-list__row"><span>Aging</span><strong style="color:var(--seob-amber)">' + num( fr.aging ) + '</strong></div>' +
				'<div class="seob-list__row"><span>Stale</span><strong style="color:var(--seob-red)">' + num( fr.stale ) + '</strong></div>' +
				'</div>' +
				'<div class="seob-mt"><a class="seob-btn seob-btn--primary seob-btn--sm" href="#/content"><span class="dashicons dashicons-backup"></span> Audit content</a></div>';
			row2.appendChild( freshCard );

			view.appendChild( row2 );

			/* Broken links call to action */
			if ( s.links.broken > 0 ) {
				var cta = h( '<div class="seob-card seob-mt"></div>' );
				cta.innerHTML =
					'<div class="seob-card__head"><div><h2>⚠️ ' + num( s.links.broken ) + ' broken link(s) found</h2>' +
					'<p>Broken links hurt user experience and crawlability. Review and fix them.</p></div>' +
					'<a class="seob-btn seob-btn--primary" href="#/links">Review links</a></div>';
				view.appendChild( cta );
			}

			/* Stale content call to action */
			if ( fr.stale > 0 ) {
				var staleCta = h( '<div class="seob-card seob-mt"></div>' );
				staleCta.innerHTML =
					'<div class="seob-card__head"><div><h2>🕒 ' + num( fr.stale ) + ' page(s) need refreshing</h2>' +
					'<p>Content not updated in over ' + num( fr.stale_months ) + ' months may be losing rankings. Refresh it to signal freshness.</p></div>' +
					'<a class="seob-btn seob-btn--primary" href="#/content">Review content</a></div>';
				view.appendChild( staleCta );
			}

			var qs = document.getElementById( 'seob-quick-submitall' );
			if ( qs ) {
				qs.addEventListener( 'click', function () {
					submitAll( qs );
				} );
			}

			updateBadge( s.links.broken );
		} ).catch( function () {
			view.innerHTML = errorState();
		} );
	};

	function statTile( color, icon, value, label, meta ) {
		return h(
			'<div class="seob-stat seob--' + color + '">' +
				'<div class="seob-stat__icon"><span class="dashicons dashicons-' + icon + '"></span></div>' +
				'<div class="seob-stat__value">' + value + '</div>' +
				'<div class="seob-stat__label">' + esc( label ) + '</div>' +
				'<div class="seob-stat__meta">' + meta + '</div>' +
			'</div>'
		);
	}

	/* ===== Sitemap ===== */
	Views[ '/sitemap' ] = function () {
		loading();
		setHeader( 'XML Sitemap', 'Automatically generated and kept up to date.' );
		api( '/settings' ).then( function ( st ) {
			view.innerHTML = '';
			var url = cfg.homeUrl + 'sitemap.xml';

			var card = h( '<div class="seob-card"></div>' );
			card.innerHTML =
				'<div class="seob-card__head"><div><h2>Sitemap configuration</h2><p>Choose what gets included in your sitemap index.</p></div></div>';

			card.appendChild(
				toggleField( 'sitemap_enabled', 'Enable XML sitemap', 'Serve the sitemap at ' + url, st.sitemap_enabled )
			);
			card.appendChild(
				toggleField( 'sitemap_include_images', 'Include images', 'Adds featured &amp; inline images to help image search.', st.sitemap_include_images )
			);
			card.appendChild( pillsField( 'sitemap_post_types', 'Post types', cfg.postTypes, st.sitemap_post_types ) );
			card.appendChild( pillsField( 'sitemap_taxonomies', 'Taxonomies', cfg.taxonomies, st.sitemap_taxonomies ) );
			card.appendChild(
				numberField( 'sitemap_per_page', 'URLs per sitemap file', 'Large sites are split into multiple files.', st.sitemap_per_page )
			);

			var keybox = h(
				'<div class="seob-keybox seob-mt"><code>' + esc( url ) + '</code>' +
				'<a class="seob-btn seob-btn--ghost seob-btn--sm" style="margin-left:auto" target="_blank" rel="noopener" href="' +
				esc( url ) + '"><span class="dashicons dashicons-external"></span> Open</a></div>'
			);
			card.appendChild( keybox );

			view.appendChild( card );
			setHeader( 'XML Sitemap', 'Automatically generated and kept up to date.', primarySaveBtn() );
			bindSave( collectSitemapSettings );
		} ).catch( function () {
			view.innerHTML = errorState();
		} );
	};

	function collectSitemapSettings() {
		return {
			sitemap_enabled: getToggle( 'sitemap_enabled' ),
			sitemap_include_images: getToggle( 'sitemap_include_images' ),
			sitemap_post_types: getPills( 'sitemap_post_types' ),
			sitemap_taxonomies: getPills( 'sitemap_taxonomies' ),
			sitemap_per_page: parseInt( document.querySelector( '[name="sitemap_per_page"]' ).value, 10 ) || 1000,
		};
	}

	/* ===== IndexNow ===== */
	Views[ '/indexnow' ] = function () {
		loading();
		setHeader( 'IndexNow', 'Instant search-engine notifications.' );
		Promise.all( [ api( '/settings' ), api( '/stats' ) ] ).then( function ( res ) {
			var st = res[ 0 ];
			var stats = res[ 1 ];
			view.innerHTML = '';

			/* Config card */
			var card = h( '<div class="seob-card"></div>' );
			card.innerHTML = '<div class="seob-card__head"><div><h2>IndexNow settings</h2><p>Notify search engines the moment your content changes.</p></div></div>';
			card.appendChild( toggleField( 'indexnow_enabled', 'Enable IndexNow', 'Master switch for instant indexing.', st.indexnow_enabled ) );
			card.appendChild( toggleField( 'indexnow_auto_submit', 'Auto-submit on publish/update', 'Automatically ping engines when posts change.', st.indexnow_auto_submit ) );

			var keyField = h( '<div class="seob-field"></div>' );
			keyField.innerHTML =
				'<label class="seob-label">Your API key</label>' +
				'<p class="seob-hint">Served automatically at <code>' + esc( stats.indexnow.key_file || ( cfg.homeUrl + stats.indexnow.key + '.txt' ) ) + '</code></p>' +
				'<div class="seob-keybox"><code id="seob-key">' + esc( stats.indexnow.key ) + '</code>' +
				'<span style="margin-left:auto;display:inline-flex;gap:8px">' +
				'<button class="seob-btn seob-btn--ghost seob-btn--sm" id="seob-verify-key"><span class="dashicons dashicons-yes"></span> Verify key</button>' +
				'<button class="seob-btn seob-btn--ghost seob-btn--sm" id="seob-regen"><span class="dashicons dashicons-update"></span> Regenerate</button>' +
				'</span></div>' +
				'<div id="seob-verify-result" class="seob-mt" hidden></div>';
			card.appendChild( keyField );

			var note = h( '<div class="seob-field"></div>' );
			note.innerHTML =
				'<div class="seob-note"><span class="dashicons dashicons-info-outline"></span>' +
				'<div><strong>About "key validation pending" (HTTP 202)</strong>' +
				'<p class="seob-hint" style="margin:4px 0 0">This is normal — the engine accepted your URLs and will validate your key file within minutes. ' +
				'It only becomes a problem if it never turns into HTTP 200. Click <em>Verify key</em> above to confirm your key file is publicly reachable.</p></div></div>';
			card.appendChild( note );
			view.appendChild( card );

			/* Manual submit card */
			var manual = h( '<div class="seob-card seob-mt"></div>' );
			manual.innerHTML =
				'<div class="seob-card__head"><div><h2>Manual submission</h2><p>Paste URLs (one per line) or resubmit your whole site.</p></div></div>' +
				'<textarea class="seob-input" id="seob-urls" placeholder="' + esc( cfg.homeUrl ) + 'my-page/&#10;' + esc( cfg.homeUrl ) + 'another/"></textarea>' +
				'<div class="seob-mt seob-inline">' +
				'<button class="seob-btn seob-btn--primary" id="seob-submit-urls"><span class="dashicons dashicons-upload"></span> Submit URLs</button>' +
				'<button class="seob-btn seob-btn--ghost" id="seob-submit-all"><span class="dashicons dashicons-admin-site-alt3"></span> Submit entire site</button>' +
				'</div>';
			view.appendChild( manual );

			/* Log card */
			var logCard = h( '<div class="seob-card seob-mt"></div>' );
			logCard.innerHTML = '<div class="seob-card__head"><div><h2>Recent submissions</h2><p>Last 50 requests.</p></div>' +
				'<button class="seob-btn seob-btn--ghost seob-btn--sm" id="seob-clear-log"><span class="dashicons dashicons-trash"></span> Clear</button></div>' +
				'<div id="seob-log"></div>';
			view.appendChild( logCard );
			renderLog( stats.indexnow.last_submission ? null : null );
			loadLog();

			setHeader( 'IndexNow', 'Instant search-engine notifications.', primarySaveBtn() );
			bindSave( function () {
				return {
					indexnow_enabled: getToggle( 'indexnow_enabled' ),
					indexnow_auto_submit: getToggle( 'indexnow_auto_submit' ),
				};
			} );

			document.getElementById( 'seob-regen' ).addEventListener( 'click', function () {
				if ( ! window.confirm( cfg.i18n.confirmKey ) ) {
					return;
				}
				api( '/indexnow/regenerate-key', 'POST' ).then( function ( r ) {
					document.getElementById( 'seob-key' ).textContent = r.key;
					toast( r.message, 'success' );
				} );
			} );

			document.getElementById( 'seob-verify-key' ).addEventListener( 'click', function ( e ) {
				var box = document.getElementById( 'seob-verify-result' );
				setBusy( e.target, true );
				api( '/indexnow/verify-key', 'POST' ).then( function ( r ) {
					setBusy( e.target, false );
					var ok = r.matches;
					box.hidden = false;
					box.innerHTML =
						'<div class="seob-note seob-note--' + ( ok ? 'ok' : 'warn' ) + '">' +
						'<span class="dashicons dashicons-' + ( ok ? 'yes-alt' : 'warning' ) + '"></span>' +
						'<div><strong>' + ( ok ? 'Key file verified' : 'Key file issue' ) + '</strong>' +
						'<p class="seob-hint" style="margin:4px 0 0">' + esc( r.message ) + '</p>' +
						'<p class="seob-hint" style="margin:4px 0 0"><a href="' + esc( r.url ) + '" target="_blank" rel="noopener">' + esc( r.url ) + '</a></p></div></div>';
					toast( ok ? 'Key verified.' : 'Key not verified — see details.', ok ? 'success' : 'error' );
				} ).catch( function () {
					setBusy( e.target, false );
					toast( cfg.i18n.error, 'error' );
				} );
			} );

			document.getElementById( 'seob-submit-urls' ).addEventListener( 'click', function ( e ) {
				var urls = document.getElementById( 'seob-urls' ).value;
				setBusy( e.target, true );
				api( '/indexnow/submit', 'POST', { urls: urls } ).then( function ( r ) {
					toast( r.success ? 'Submitted ' + r.count + ' URL(s). ' + r.message : r.message, r.success ? 'success' : 'error' );
					setBusy( e.target, false );
					loadLog();
				} ).catch( function () {
					setBusy( e.target, false );
					toast( cfg.i18n.error, 'error' );
				} );
			} );

			document.getElementById( 'seob-submit-all' ).addEventListener( 'click', function ( e ) {
				submitAll( e.target );
			} );

			document.getElementById( 'seob-clear-log' ).addEventListener( 'click', function () {
				api( '/indexnow/clear-log', 'POST' ).then( function () {
					loadLog();
					toast( 'Log cleared.', 'success' );
				} );
			} );
		} ).catch( function () {
			view.innerHTML = errorState();
		} );
	};

	function loadLog() {
		api( '/links?filter=all&per_page=1' ).then( function ( d ) {
			renderLog( d.log || [] );
		} );
	}

	function renderLog( log ) {
		var el = document.getElementById( 'seob-log' );
		if ( ! el ) {
			return;
		}
		if ( ! log || ! log.length ) {
			el.innerHTML = '<p class="seob-muted">No submissions logged yet.</p>';
			return;
		}
		var rows = log.map( function ( item ) {
			var badge = item.success
				? '<span class="seob-badge seob-badge--ok">HTTP ' + esc( item.code ) + '</span>'
				: '<span class="seob-badge seob-badge--broken">HTTP ' + esc( item.code ) + '</span>';

			var urls = Array.isArray( item.urls ) ? item.urls : [];
			var urlsHTML = '';
			if ( urls.length ) {
				var links = urls
					.map( function ( u ) {
						return '<li><a href="' + esc( u ) + '" target="_blank" rel="noopener" class="seob-url">' + esc( u ) + '</a></li>';
					} )
					.join( '' );
				urlsHTML =
					'<details class="seob-log__urls">' +
					'<summary>' + ( urls.length === 1 ? 'Show URL' : 'Show ' + num( urls.length ) + ' URLs' ) + '</summary>' +
					'<ul class="seob-log__list">' + links + '</ul>' +
					'</details>';
			}

			return (
				'<div class="seob-log__row">' +
				'<div class="seob-log__main">' +
				'<div class="seob-log__head"><strong>' + num( item.count ) + ' URL(s)</strong> ' + badge + '</div>' +
				'<small class="seob-muted">' + esc( item.time ) + ' — ' + esc( item.message ) + '</small>' +
				urlsHTML +
				'</div></div>'
			);
		} ).join( '' );
		el.innerHTML = '<div class="seob-list">' + rows + '</div>';
	}

	function submitAll( btn ) {
		setBusy( btn, true );
		api( '/indexnow/submit-all', 'POST' ).then( function ( r ) {
			toast(
				( r.success ? 'Submitted ' : 'Partially submitted ' ) + num( r.count ) + ' of ' + num( r.total ) + ' URLs. ' + r.message,
				r.success ? 'success' : 'error'
			);
			setBusy( btn, false );
			if ( document.getElementById( 'seob-log' ) ) {
				loadLog();
			}
		} ).catch( function () {
			setBusy( btn, false );
			toast( cfg.i18n.error, 'error' );
		} );
	}

	/* ===== Broken links ===== */
	var linkState = { filter: 'broken', page: 1, search: '' };

	Views[ '/links' ] = function () {
		setHeader(
			'Broken Links',
			'Find and fix links that lead nowhere.',
			'<button class="seob-btn seob-btn--primary" id="seob-scan"><span class="dashicons dashicons-search"></span> Scan now</button>'
		);
		linkState.page = 1;
		renderLinksShell();
		loadLinks();

		document.getElementById( 'seob-scan' ).addEventListener( 'click', runScan );
	};

	function renderLinksShell() {
		view.innerHTML = '';
		var card = h( '<div class="seob-card"></div>' );
		card.innerHTML =
			'<div class="seob-toolbar">' +
			'<div class="seob-tabs" id="seob-filters">' +
			tabBtn( 'broken', 'Broken' ) +
			tabBtn( 'redirect', 'Redirects' ) +
			tabBtn( 'ok', 'Healthy' ) +
			tabBtn( 'pending', 'Pending' ) +
			tabBtn( 'all', 'All' ) +
			'</div>' +
			'<div class="seob-search"><span class="dashicons dashicons-search"></span>' +
			'<input type="search" id="seob-link-search" placeholder="Search URL or anchor…"></div>' +
			'</div>' +
			'<div id="seob-progress-wrap" hidden><div class="seob-inline seob-muted" id="seob-progress-label"></div><div class="seob-progress"><div class="seob-progress__bar" id="seob-progress-bar"></div></div></div>' +
			'<div id="seob-links-body"></div>';
		view.appendChild( card );

		card.querySelectorAll( '#seob-filters button' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				linkState.filter = b.dataset.filter;
				linkState.page = 1;
				card.querySelectorAll( '#seob-filters button' ).forEach( function ( x ) {
					x.classList.toggle( 'is-active', x === b );
				} );
				loadLinks();
			} );
		} );

		var searchInput = document.getElementById( 'seob-link-search' );
		var timer;
		searchInput.addEventListener( 'input', function () {
			clearTimeout( timer );
			timer = setTimeout( function () {
				linkState.search = searchInput.value;
				linkState.page = 1;
				loadLinks();
			}, 350 );
		} );
	}

	function tabBtn( filter, label ) {
		var active = linkState.filter === filter ? ' is-active' : '';
		return '<button class="' + active.trim() + '" data-filter="' + filter + '">' + label + '</button>';
	}

	function loadLinks() {
		var body = document.getElementById( 'seob-links-body' );
		body.innerHTML = '<div class="seob-loading"><span class="seob-spinner"></span> Loading links…</div>';
		var q =
			'/links?filter=' + encodeURIComponent( linkState.filter ) +
			'&page=' + linkState.page +
			'&per_page=20&search=' + encodeURIComponent( linkState.search );
		api( q ).then( function ( d ) {
			renderLinksTable( d );
		} ).catch( function () {
			body.innerHTML = errorState();
		} );
	}

	function renderLinksTable( d ) {
		var body = document.getElementById( 'seob-links-body' );
		if ( ! d.items || ! d.items.length ) {
			body.innerHTML =
				'<div class="seob-empty"><span class="dashicons dashicons-yes-alt"></span>' +
				'<h3>Nothing here</h3><p>' +
				( linkState.filter === 'broken' ? 'No broken links found. Nice and tidy!' : 'No links match this view yet. Try running a scan.' ) +
				'</p></div>';
			return;
		}

		var rows = d.items.map( function ( it ) {
			var source = it.post_title
				? '<a href="' + esc( it.edit_link ) + '" target="_blank" rel="noopener">' + esc( it.post_title ) + '</a>'
				: '<span class="seob-muted">—</span>';
			return (
				'<tr>' +
				'<td><div class="seob-url">' + esc( it.url ) + '</div>' +
				( it.anchor_text ? '<div class="seob-anchor">“' + esc( it.anchor_text ) + '”</div>' : '' ) + '</td>' +
				'<td class="seob-src">' + source + '</td>' +
				'<td>' + badgeForStatus( it.status, it.status_code ) + '</td>' +
				'<td><small class="seob-muted">' + esc( it.last_checked || 'never' ) + '</small></td>' +
				'<td style="text-align:right;white-space:nowrap">' +
				'<button class="seob-btn seob-btn--ghost seob-btn--sm seob-recheck" data-id="' + it.id + '"><span class="dashicons dashicons-update"></span></button> ' +
				( it.url ? '<a class="seob-btn seob-btn--ghost seob-btn--sm" target="_blank" rel="noopener" href="' + esc( it.url ) + '"><span class="dashicons dashicons-external"></span></a>' : '' ) +
				'</td></tr>'
			);
		} ).join( '' );

		var totalPages = Math.max( 1, Math.ceil( d.total / 20 ) );
		body.innerHTML =
			'<table class="seob-table"><thead><tr>' +
			'<th>Link</th><th>Found in</th><th>Status</th><th>Last checked</th><th></th>' +
			'</tr></thead><tbody>' + rows + '</tbody></table>' +
			'<div class="seob-pager"><div class="seob-pager__info">' + num( d.total ) + ' link(s) · page ' + linkState.page + ' of ' + totalPages + '</div>' +
			'<div class="seob-pager__btns">' +
			'<button class="seob-btn seob-btn--ghost seob-btn--sm" id="seob-prev"' + ( linkState.page <= 1 ? ' disabled' : '' ) + '>Prev</button>' +
			'<button class="seob-btn seob-btn--ghost seob-btn--sm" id="seob-next"' + ( linkState.page >= totalPages ? ' disabled' : '' ) + '>Next</button>' +
			'</div></div>';

		body.querySelectorAll( '.seob-recheck' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				setBusy( b, true );
				api( '/links/' + b.dataset.id + '/recheck', 'POST' ).then( function () {
					loadLinks();
					toast( 'Re-checked.', 'success' );
				} ).catch( function () {
					setBusy( b, false );
					toast( cfg.i18n.error, 'error' );
				} );
			} );
		} );

		var prev = document.getElementById( 'seob-prev' );
		var next = document.getElementById( 'seob-next' );
		if ( prev ) {
			prev.addEventListener( 'click', function () {
				if ( linkState.page > 1 ) {
					linkState.page--;
					loadLinks();
				}
			} );
		}
		if ( next ) {
			next.addEventListener( 'click', function () {
				if ( linkState.page < totalPages ) {
					linkState.page++;
					loadLinks();
				}
			} );
		}
	}

	function runScan() {
		var btn = document.getElementById( 'seob-scan' );
		setBusy( btn, true );
		var wrap = document.getElementById( 'seob-progress-wrap' );
		var bar = document.getElementById( 'seob-progress-bar' );
		var label = document.getElementById( 'seob-progress-label' );
		wrap.hidden = false;
		label.textContent = 'Indexing links…';
		bar.style.width = '6%';

		api( '/links/scan', 'POST' ).then( function ( r ) {
			var total = r.pending || 0;
			if ( total === 0 ) {
				finishScan( btn, wrap, 'No links to check.' );
				loadLinks();
				return;
			}
			checkBatchLoop( total, total, btn, wrap, bar, label );
		} ).catch( function () {
			finishScan( btn, wrap, cfg.i18n.error, 'error' );
		} );
	}

	function checkBatchLoop( total, remaining, btn, wrap, bar, label ) {
		api( '/links/check-batch', 'POST', { limit: 25 } ).then( function ( r ) {
			var done = total - r.pending;
			var pct = total > 0 ? Math.round( ( done / total ) * 100 ) : 100;
			bar.style.width = pct + '%';
			label.textContent = 'Checked ' + num( done ) + ' of ' + num( total ) + ' links…';
			if ( r.done || r.pending <= 0 ) {
				finishScan( btn, wrap, 'Scan complete. ' + num( r.broken ) + ' broken in last batch.' );
				loadLinks();
				refreshBadge();
			} else {
				checkBatchLoop( total, r.pending, btn, wrap, bar, label );
			}
		} ).catch( function () {
			finishScan( btn, wrap, cfg.i18n.error, 'error' );
		} );
	}

	function finishScan( btn, wrap, msg, type ) {
		setBusy( btn, false );
		toast( msg, type || 'success' );
		setTimeout( function () {
			wrap.hidden = true;
		}, 1200 );
	}

	/* ===== Google Search Console ===== */
	Views[ '/search-console' ] = function () {
		loading();
		setHeader( 'Search Console', 'Connect your site to Google Search Console.' );
		Promise.all( [ api( '/settings' ), api( '/stats' ) ] ).then( function ( res ) {
			var st = res[ 0 ];
			var stats = res[ 1 ];
			var gsc = stats.search_console || {};
			var sitemapUrl = cfg.homeUrl + 'sitemap.xml';
			view.innerHTML = '';

			/* Status banner */
			var status = h( '<div class="seob-card"></div>' );
			var verified = !! gsc.verified;
			status.innerHTML =
				'<div class="seob-card__head"><div><h2>Connection status</h2>' +
				'<p>Google uses site verification to confirm you own this site.</p></div>' +
				'<span class="seob-badge seob-badge--' + ( verified ? 'on">Verified' : 'off">Not verified' ) + '</span></div>' +
				'<div class="seob-note seob-note--' + ( verified ? 'ok' : 'warn' ) + '">' +
				'<span class="dashicons dashicons-' + ( verified ? 'yes-alt' : 'info-outline' ) + '"></span>' +
				'<div><p class="seob-hint" style="margin:0">' +
				( verified
					? 'A verification tag is active in your site\'s &lt;head&gt;. You can now add this property in Search Console (if you haven\'t already).'
					: 'Paste your Google verification code below to add the meta tag automatically — no file uploads or DNS changes needed.' ) +
				'</p></div></div>';
			view.appendChild( status );

			/* Verification card */
			var card = h( '<div class="seob-card seob-mt"></div>' );
			card.innerHTML =
				'<div class="seob-card__head"><div><h2>1. Verify ownership (HTML tag method)</h2>' +
				'<p>In Search Console choose <em>HTML tag</em> verification, copy the tag, and paste it here.</p></div></div>' +
				'<div class="seob-field">' +
				'<label class="seob-label">Google verification code or meta tag</label>' +
				'<p class="seob-hint">Paste the whole <code>&lt;meta name="google-site-verification" ...&gt;</code> tag or just the code — we\'ll extract it.</p>' +
				'<input class="seob-input" type="text" name="gsc_verification" value="' + esc( st.gsc_verification || '' ) + '" ' +
				'placeholder="&lt;meta name=&quot;google-site-verification&quot; content=&quot;abc123...&quot; /&gt;" style="max-width:640px">' +
				'</div>' +
				'<a class="seob-btn seob-btn--ghost seob-btn--sm" target="_blank" rel="noopener" href="' +
				esc( gsc.add_property_url || 'https://search.google.com/search-console' ) + '"><span class="dashicons dashicons-external"></span> Open Search Console</a>';
			view.appendChild( card );

			/* Sitemap submission card */
			var sm = h( '<div class="seob-card seob-mt"></div>' );
			sm.innerHTML =
				'<div class="seob-card__head"><div><h2>2. Submit your sitemap</h2>' +
				'<p>After verifying, add your sitemap so Google can discover every page.</p></div></div>' +
				'<div class="seob-keybox"><code>' + esc( sitemapUrl ) + '</code>' +
				'<button class="seob-btn seob-btn--ghost seob-btn--sm" id="seob-copy-sitemap" style="margin-left:auto"><span class="dashicons dashicons-clipboard"></span> Copy</button></div>' +
				'<div class="seob-mt">' +
				'<a class="seob-btn seob-btn--primary" target="_blank" rel="noopener" href="' +
				esc( gsc.sitemaps_url || 'https://search.google.com/search-console/sitemaps' ) + '"><span class="dashicons dashicons-external"></span> Open Sitemaps in Search Console</a></div>' +
				'<p class="seob-hint seob-mt">In the Sitemaps screen, paste <code>sitemap.xml</code> into the "Add a new sitemap" box and click Submit.</p>';
			view.appendChild( sm );

			/* Note about full API */
			var note = h( '<div class="seob-card seob-mt"></div>' );
			note.innerHTML =
				'<div class="seob-card__head"><div><h2>Want automatic API access?</h2></div></div>' +
				'<p class="seob-hint" style="margin:0">This connects your site using Google\'s official <em>HTML tag</em> method, which needs no Google Cloud setup. ' +
				'Full API access (to pull search analytics into this dashboard) requires creating a Google Cloud OAuth app — tell us if you\'d like that added.</p>';
			view.appendChild( note );

			setHeader( 'Search Console', 'Connect your site to Google Search Console.', primarySaveBtn() );
			bindSave( function () {
				return { gsc_verification: document.querySelector( '[name="gsc_verification"]' ).value };
			} );

			var copyBtn = document.getElementById( 'seob-copy-sitemap' );
			if ( copyBtn ) {
				copyBtn.addEventListener( 'click', function () {
					copyText( sitemapUrl );
					toast( 'Sitemap URL copied.', 'success' );
				} );
			}
		} ).catch( function () {
			view.innerHTML = errorState();
		} );
	};

	/* ===== Local SEO & Schema ===== */
	Views[ '/local-seo' ] = function () {
		loading();
		setHeader( 'Local SEO & Schema', 'Structured data for rich results and the local pack.' );
		api( '/settings' ).then( function ( st ) {
			view.innerHTML = '';
			var isLocal = st.schema_type && st.schema_type !== 'Organization';

			/* Toggle + type */
			var typeCard = h( '<div class="seob-card"></div>' );
			typeCard.innerHTML = '<div class="seob-card__head"><div><h2>Structured data</h2><p>Tell Google exactly what your business is.</p></div></div>';
			typeCard.appendChild( toggleField( 'schema_enabled', 'Enable structured data output', 'Adds JSON-LD schema to your site\u2019s &lt;head&gt;.', st.schema_enabled ) );
			typeCard.appendChild(
				selectField( 'schema_type', 'Business type', st.schema_type || 'Organization', [
					[ 'Organization', 'Organization (general company)' ],
					[ 'LocalBusiness', 'Local Business (has a physical location)' ],
					[ 'ProfessionalService', 'Professional Service (agency, consultancy)' ],
				] )
			);
			typeCard.appendChild(
				h( '<p class="seob-hint" style="margin:0">For a Goa digital marketing agency, <strong>ProfessionalService</strong> or <strong>Local Business</strong> unlocks local-pack features like maps, hours and service areas.</p>' )
			);
			view.appendChild( typeCard );

			/* Business details */
			var napCard = h( '<div class="seob-card seob-mt"></div>' );
			napCard.innerHTML = '<div class="seob-card__head"><div><h2>Business details (NAP)</h2><p>Name, address &amp; phone \u2014 keep these consistent everywhere online.</p></div></div>';
			napCard.appendChild( textField( 'org_name', 'Business name', 'Leave blank to use your site title.', st.org_name, cfg.siteName || 'Your Agency', '640px' ) );
			napCard.appendChild( textField( 'org_logo', 'Logo URL', 'A square logo works best for Google.', st.org_logo, 'https://\u2026/logo.png', '640px' ) );

			var r1 = fieldRow();
			r1.appendChild( wrapFlex( textField( 'org_phone', 'Phone', '', st.org_phone, '+91 \u2026' ) ) );
			r1.appendChild( wrapFlex( textField( 'org_email', 'Email', '', st.org_email, 'hello@\u2026' ) ) );
			napCard.appendChild( r1 );

			napCard.appendChild( textField( 'org_street', 'Street address', '', st.org_street, 'Shop 1, MG Road', '640px' ) );

			var r2 = fieldRow();
			r2.appendChild( wrapFlex( textField( 'org_locality', 'City', '', st.org_locality, 'Panaji' ) ) );
			r2.appendChild( wrapFlex( textField( 'org_region', 'State / region', '', st.org_region, 'Goa' ) ) );
			napCard.appendChild( r2 );

			var r3 = fieldRow();
			r3.appendChild( wrapFlex( textField( 'org_postal', 'Postal code', '', st.org_postal, '403001' ) ) );
			r3.appendChild( wrapFlex( textField( 'org_country', 'Country code', '', st.org_country, 'IN' ) ) );
			napCard.appendChild( r3 );

			var r4 = fieldRow();
			r4.appendChild( wrapFlex( textField( 'org_lat', 'Latitude', '', st.org_lat, '15.4909' ) ) );
			r4.appendChild( wrapFlex( textField( 'org_lng', 'Longitude', '', st.org_lng, '73.8278' ) ) );
			napCard.appendChild( r4 );
			view.appendChild( napCard );

			/* Local-business extras */
			var localCard = h( '<div class="seob-card seob-mt"></div>' );
			localCard.innerHTML =
				'<div class="seob-card__head"><div><h2>Local business extras</h2>' +
				'<p>Shown for Local Business / Professional Service types.</p></div></div>';
			localCard.appendChild( textField( 'org_area_served', 'Area served', 'e.g. the region you serve.', st.org_area_served, 'Goa', '640px' ) );
			var r5 = fieldRow();
			r5.appendChild( wrapFlex( textField( 'org_price_range', 'Price range', '', st.org_price_range, '$$' ) ) );
			r5.appendChild( wrapFlex( textField( 'org_hours', 'Opening hours', '', st.org_hours, 'Mo-Sa 09:00-18:00' ) ) );
			localCard.appendChild( r5 );
			view.appendChild( localCard );

			/* Social profiles */
			var socialCard = h( '<div class="seob-card seob-mt"></div>' );
			socialCard.innerHTML = '<div class="seob-card__head"><div><h2>Social profiles</h2><p>Linked as <code>sameAs</code> \u2014 helps Google connect your brand.</p></div></div>';
			socialCard.appendChild(
				textareaField(
					'social_profiles',
					'Profile URLs (one per line)',
					'',
					( st.social_profiles || [] ).join( '\n' ),
					'https://facebook.com/youragency\nhttps://instagram.com/youragency\nhttps://linkedin.com/company/youragency'
				)
			);
			view.appendChild( socialCard );

			/* Rich result toggles */
			var richCard = h( '<div class="seob-card seob-mt"></div>' );
			richCard.innerHTML = '<div class="seob-card__head"><div><h2>Rich results</h2><p>Extra schema types on your pages.</p></div></div>';
			richCard.appendChild( toggleField( 'schema_article', 'Article schema on posts', 'Includes published &amp; modified dates (a freshness signal).', st.schema_article ) );
			richCard.appendChild( toggleField( 'schema_breadcrumbs', 'Breadcrumb schema', 'Shows breadcrumb trails in search results.', st.schema_breadcrumbs ) );
			richCard.appendChild( toggleField( 'schema_searchbox', 'Sitelinks search box', 'Lets Google show a search box for your site.', st.schema_searchbox ) );
			richCard.appendChild(
				h( '<div class="seob-mt"><a class="seob-btn seob-btn--ghost seob-btn--sm" target="_blank" rel="noopener" href="https://search.google.com/test/rich-results?url=' +
					encodeURIComponent( cfg.homeUrl ) + '"><span class="dashicons dashicons-external"></span> Test with Google Rich Results</a></div>' )
			);
			view.appendChild( richCard );

			setHeader( 'Local SEO & Schema', 'Structured data for rich results and the local pack.', primarySaveBtn() );
			bindSave( collectLocalSeo );

			if ( isLocal ) { /* reserved for future conditional UI */ }
		} ).catch( function () {
			view.innerHTML = errorState();
		} );
	};

	function wrapFlex( node ) {
		var w = h( '<div class="seob-flex1"></div>' );
		node.style.marginBottom = '0';
		w.appendChild( node );
		return w;
	}

	function fieldVal( name ) {
		var el = document.querySelector( '[name="' + name + '"]' );
		return el ? el.value : '';
	}

	function collectLocalSeo() {
		return {
			schema_enabled: getToggle( 'schema_enabled' ),
			schema_type: fieldVal( 'schema_type' ),
			org_name: fieldVal( 'org_name' ),
			org_logo: fieldVal( 'org_logo' ),
			org_phone: fieldVal( 'org_phone' ),
			org_email: fieldVal( 'org_email' ),
			org_street: fieldVal( 'org_street' ),
			org_locality: fieldVal( 'org_locality' ),
			org_region: fieldVal( 'org_region' ),
			org_postal: fieldVal( 'org_postal' ),
			org_country: fieldVal( 'org_country' ),
			org_lat: fieldVal( 'org_lat' ),
			org_lng: fieldVal( 'org_lng' ),
			org_area_served: fieldVal( 'org_area_served' ),
			org_price_range: fieldVal( 'org_price_range' ),
			org_hours: fieldVal( 'org_hours' ),
			social_profiles: fieldVal( 'social_profiles' ),
			schema_article: getToggle( 'schema_article' ),
			schema_breadcrumbs: getToggle( 'schema_breadcrumbs' ),
			schema_searchbox: getToggle( 'schema_searchbox' ),
		};
	}

	/* ===== Content Freshness ===== */
	var freshState = { filter: 'stale', page: 1, search: '' };

	Views[ '/content' ] = function () {
		setHeader( 'Content Freshness', 'Keep your content current to protect rankings.' );
		freshState.page = 1;
		renderFreshnessShell();
		loadFreshness();
	};

	function renderFreshnessShell() {
		view.innerHTML = '';
		var summary = h( '<div class="seob-grid seob-grid--stats" id="seob-fresh-summary"></div>' );
		view.appendChild( summary );

		var card = h( '<div class="seob-card seob-mt"></div>' );
		card.innerHTML =
			'<div class="seob-toolbar">' +
			'<div class="seob-tabs" id="seob-fresh-filters">' +
			freshTab( 'stale', 'Stale' ) +
			freshTab( 'aging', 'Aging' ) +
			freshTab( 'fresh', 'Fresh' ) +
			freshTab( 'all', 'All' ) +
			'</div>' +
			'<div class="seob-search"><span class="dashicons dashicons-search"></span>' +
			'<input type="search" id="seob-fresh-search" placeholder="Search content…"></div>' +
			'</div>' +
			'<div id="seob-fresh-body"></div>';
		view.appendChild( card );

		card.querySelectorAll( '#seob-fresh-filters button' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				freshState.filter = b.dataset.filter;
				freshState.page = 1;
				card.querySelectorAll( '#seob-fresh-filters button' ).forEach( function ( x ) {
					x.classList.toggle( 'is-active', x === b );
				} );
				loadFreshness();
			} );
		} );

		var searchInput = document.getElementById( 'seob-fresh-search' );
		var timer;
		searchInput.addEventListener( 'input', function () {
			clearTimeout( timer );
			timer = setTimeout( function () {
				freshState.search = searchInput.value;
				freshState.page = 1;
				loadFreshness();
			}, 350 );
		} );
	}

	function freshTab( filter, label ) {
		var active = freshState.filter === filter ? ' is-active' : '';
		return '<button class="' + active.trim() + '" data-filter="' + filter + '">' + label + '</button>';
	}

	function loadFreshness() {
		var body = document.getElementById( 'seob-fresh-body' );
		body.innerHTML = '<div class="seob-loading"><span class="seob-spinner"></span> Analysing content…</div>';
		var q =
			'/freshness?filter=' + encodeURIComponent( freshState.filter ) +
			'&page=' + freshState.page +
			'&per_page=20&search=' + encodeURIComponent( freshState.search );
		api( q ).then( function ( d ) {
			renderFreshnessSummary( d.summary );
			renderFreshnessTable( d );
		} ).catch( function () {
			body.innerHTML = errorState();
		} );
	}

	function renderFreshnessSummary( s ) {
		s = s || { fresh: 0, aging: 0, stale: 0, avg_score: 100 };
		var el = document.getElementById( 'seob-fresh-summary' );
		if ( ! el ) {
			return;
		}
		el.innerHTML = '';
		el.appendChild( statTile( s.avg_score >= 70 ? 'green' : 'amber', 'chart-bar', ( s.avg_score != null ? s.avg_score : 100 ) + '%', 'Avg freshness score', s.total + ' items' ) );
		el.appendChild( statTile( 'green', 'yes-alt', num( s.fresh ), 'Fresh', 'Updated recently' ) );
		el.appendChild( statTile( 'amber', 'clock', num( s.aging ), 'Aging', 'Older than ' + num( s.aging_months ) + ' months' ) );
		el.appendChild( statTile( s.stale > 0 ? 'red' : 'green', 'backup', num( s.stale ), 'Stale', 'Older than ' + num( s.stale_months ) + ' months' ) );
	}

	function freshBadge( status, score ) {
		var map = { fresh: 'ok', aging: 'redirect', stale: 'broken' };
		var cls = map[ status ] || 'pending';
		var label = status.charAt( 0 ).toUpperCase() + status.slice( 1 );
		return '<span class="seob-badge seob-badge--' + cls + '">' + esc( label ) + ' · ' + score + '%</span>';
	}

	function renderFreshnessTable( d ) {
		var body = document.getElementById( 'seob-fresh-body' );
		if ( ! d.items || ! d.items.length ) {
			body.innerHTML =
				'<div class="seob-empty"><span class="dashicons dashicons-yes-alt"></span>' +
				'<h3>Nothing here</h3><p>' +
				( freshState.filter === 'stale' ? 'No stale content \u2014 your content is nicely up to date!' : 'No content matches this view.' ) +
				'</p></div>';
			return;
		}

		var rows = d.items.map( function ( it ) {
			var ageText = it.days === 0 ? 'today' : num( it.days ) + ' days ago';
			return (
				'<tr>' +
				'<td><div class="seob-url" style="font-family:inherit;font-weight:600">' + esc( it.title || '(no title)' ) + '</div>' +
				'<div class="seob-anchor">' + esc( it.url ) + '</div></td>' +
				'<td><span class="seob-muted">' + esc( it.post_type ) + '</span></td>' +
				'<td><small class="seob-muted">' + esc( it.modified ) + '<br>' + ageText + '</small></td>' +
				'<td>' + freshBadge( it.status, it.score ) + '</td>' +
				'<td style="text-align:right;white-space:nowrap">' +
				( it.edit_link ? '<a class="seob-btn seob-btn--primary seob-btn--sm" target="_blank" rel="noopener" href="' + esc( it.edit_link ) + '"><span class="dashicons dashicons-edit"></span> Refresh</a> ' : '' ) +
				( it.url ? '<a class="seob-btn seob-btn--ghost seob-btn--sm" target="_blank" rel="noopener" href="' + esc( it.url ) + '"><span class="dashicons dashicons-external"></span></a>' : '' ) +
				'</td></tr>'
			);
		} ).join( '' );

		var totalPages = Math.max( 1, Math.ceil( d.total / 20 ) );
		body.innerHTML =
			'<table class="seob-table"><thead><tr>' +
			'<th>Content</th><th>Type</th><th>Last updated</th><th>Freshness</th><th></th>' +
			'</tr></thead><tbody>' + rows + '</tbody></table>' +
			'<div class="seob-pager"><div class="seob-pager__info">' + num( d.total ) + ' item(s) · page ' + freshState.page + ' of ' + totalPages + '</div>' +
			'<div class="seob-pager__btns">' +
			'<button class="seob-btn seob-btn--ghost seob-btn--sm" id="seob-fresh-prev"' + ( freshState.page <= 1 ? ' disabled' : '' ) + '>Prev</button>' +
			'<button class="seob-btn seob-btn--ghost seob-btn--sm" id="seob-fresh-next"' + ( freshState.page >= totalPages ? ' disabled' : '' ) + '>Next</button>' +
			'</div></div>';

		var prev = document.getElementById( 'seob-fresh-prev' );
		var next = document.getElementById( 'seob-fresh-next' );
		if ( prev ) {
			prev.addEventListener( 'click', function () {
				if ( freshState.page > 1 ) {
					freshState.page--;
					loadFreshness();
				}
			} );
		}
		if ( next ) {
			next.addEventListener( 'click', function () {
				if ( freshState.page < totalPages ) {
					freshState.page++;
					loadFreshness();
				}
			} );
		}
	}

	/* ===== AI Content Kit ===== */
	var aiState = { filter: 'all', page: 1, search: '', template: 'rewrite', format: 'markdown', selected: {} };
	var AI_TEMPLATES = [
		[ 'rewrite', 'Rewrite & optimise existing content' ],
		[ 'fresh', 'Write brand-new content on this topic' ],
		[ 'expand', 'Expand thin content into an in-depth guide' ],
		[ 'meta', 'Generate SEO title, meta description & FAQs' ],
	];

	Views[ '/ai-content' ] = function () {
		loading();
		setHeader( 'AI Content Kit', 'Export page data as ready-to-paste prompts for Claude or ChatGPT.' );
		aiState.page = 1;
		aiState.selected = {};
		api( '/settings' ).then( function ( st ) {
			renderAiShell( st );
			loadAiPosts();
		} ).catch( function () {
			view.innerHTML = errorState();
		} );
	};

	function renderAiShell( st ) {
		view.innerHTML = '';

		/* Business context card */
		var ctx = h( '<div class="seob-card"></div>' );
		ctx.innerHTML =
			'<div class="seob-card__head"><div><h2>Business context</h2><p>Injected into every prompt so the AI writes for your agency &amp; location.</p></div></div>' +
			'<div class="seob-field" style="margin:0">' +
			'<textarea class="seob-input" name="ai_business_context" placeholder="e.g. We are a digital marketing agency in Goa serving local hotels, restaurants and startups. Friendly, expert tone.">' +
			esc( st.ai_business_context || '' ) + '</textarea>' +
			'<div class="seob-mt"><button class="seob-btn seob-btn--ghost seob-btn--sm" id="seob-ai-savectx"><span class="dashicons dashicons-saved"></span> Save context</button> ' +
			'<span class="seob-muted" style="font-size:12px">Pulls in your name, location &amp; area served from Local SEO automatically.</span></div>' +
			'</div>';
		view.appendChild( ctx );

		/* Controls card */
		var controls = h( '<div class="seob-card seob-mt"></div>' );
		var tplOpts = AI_TEMPLATES.map( function ( t ) {
			return '<option value="' + t[ 0 ] + '"' + ( t[ 0 ] === aiState.template ? ' selected' : '' ) + '>' + esc( t[ 1 ] ) + '</option>';
		} ).join( '' );
		controls.innerHTML =
			'<div class="seob-card__head"><div><h2>Export options</h2><p>Pick the goal and format, then export single pages or a bulk pack.</p></div></div>' +
			'<div class="seob-row">' +
			'<div class="seob-flex1"><label class="seob-label">AI task</label>' +
			'<select class="seob-select" id="seob-ai-template" style="max-width:100%">' + tplOpts + '</select></div>' +
			'<div class="seob-flex1"><label class="seob-label">Format</label>' +
			'<select class="seob-select" id="seob-ai-format" style="max-width:100%">' +
			'<option value="markdown"' + ( aiState.format === 'markdown' ? ' selected' : '' ) + '>Markdown (for Claude/ChatGPT)</option>' +
			'<option value="json"' + ( aiState.format === 'json' ? ' selected' : '' ) + '>JSON (structured)</option>' +
			'</select></div>' +
			'</div>';
		view.appendChild( controls );

		/* List card */
		var card = h( '<div class="seob-card seob-mt"></div>' );
		card.innerHTML =
			'<div class="seob-toolbar">' +
			'<div class="seob-tabs" id="seob-ai-filters">' +
			aiTab( 'all', 'All' ) +
			aiTab( 'stale', 'Stale' ) +
			aiTab( 'aging', 'Aging' ) +
			aiTab( 'fresh', 'Fresh' ) +
			'</div>' +
			'<div class="seob-search"><span class="dashicons dashicons-search"></span>' +
			'<input type="search" id="seob-ai-search" placeholder="Search pages…"></div>' +
			'</div>' +
			'<div class="seob-bulkbar" id="seob-ai-bulkbar" hidden>' +
			'<span id="seob-ai-count">0 selected</span>' +
			'<span style="margin-left:auto;display:inline-flex;gap:8px">' +
			'<button class="seob-btn seob-btn--ghost seob-btn--sm" id="seob-ai-clear">Clear</button>' +
			'<button class="seob-btn seob-btn--ghost seob-btn--sm" id="seob-ai-copysel"><span class="dashicons dashicons-clipboard"></span> Copy pack</button>' +
			'<button class="seob-btn seob-btn--primary seob-btn--sm" id="seob-ai-exportsel"><span class="dashicons dashicons-download"></span> Download pack</button>' +
			'</span></div>' +
			'<div id="seob-ai-body"></div>';
		view.appendChild( card );

		/* Preview area */
		var preview = h( '<div class="seob-card seob-mt" id="seob-ai-preview" hidden></div>' );
		view.appendChild( preview );

		/* Wire controls */
		document.getElementById( 'seob-ai-savectx' ).addEventListener( 'click', function ( e ) {
			setBusy( e.target, true );
			api( '/settings', 'POST', { ai_business_context: document.querySelector( '[name="ai_business_context"]' ).value } )
				.then( function () {
					setBusy( e.target, false );
					toast( 'Context saved.', 'success' );
				} )
				.catch( function () {
					setBusy( e.target, false );
					toast( cfg.i18n.error, 'error' );
				} );
		} );

		document.getElementById( 'seob-ai-template' ).addEventListener( 'change', function ( e ) {
			aiState.template = e.target.value;
		} );
		document.getElementById( 'seob-ai-format' ).addEventListener( 'change', function ( e ) {
			aiState.format = e.target.value;
		} );

		card.querySelectorAll( '#seob-ai-filters button' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				aiState.filter = b.dataset.filter;
				aiState.page = 1;
				card.querySelectorAll( '#seob-ai-filters button' ).forEach( function ( x ) {
					x.classList.toggle( 'is-active', x === b );
				} );
				loadAiPosts();
			} );
		} );

		var searchInput = document.getElementById( 'seob-ai-search' );
		var timer;
		searchInput.addEventListener( 'input', function () {
			clearTimeout( timer );
			timer = setTimeout( function () {
				aiState.search = searchInput.value;
				aiState.page = 1;
				loadAiPosts();
			}, 350 );
		} );

		document.getElementById( 'seob-ai-clear' ).addEventListener( 'click', function () {
			aiState.selected = {};
			loadAiPosts();
			updateAiBulkBar();
		} );
		document.getElementById( 'seob-ai-exportsel' ).addEventListener( 'click', function ( e ) {
			aiExportSelected( e.target, false );
		} );
		document.getElementById( 'seob-ai-copysel' ).addEventListener( 'click', function ( e ) {
			aiExportSelected( e.target, true );
		} );
	}

	function aiTab( filter, label ) {
		var active = aiState.filter === filter ? ' is-active' : '';
		return '<button class="' + active.trim() + '" data-filter="' + filter + '">' + label + '</button>';
	}

	function selectedIds() {
		return Object.keys( aiState.selected ).filter( function ( k ) {
			return aiState.selected[ k ];
		} );
	}

	function updateAiBulkBar() {
		var bar = document.getElementById( 'seob-ai-bulkbar' );
		if ( ! bar ) {
			return;
		}
		var ids = selectedIds();
		bar.hidden = ids.length === 0;
		var c = document.getElementById( 'seob-ai-count' );
		if ( c ) {
			c.textContent = ids.length + ' selected' + ( ids.length >= 30 ? ' (max 30 per pack)' : '' );
		}
	}

	function loadAiPosts() {
		var body = document.getElementById( 'seob-ai-body' );
		body.innerHTML = '<div class="seob-loading"><span class="seob-spinner"></span> Loading pages…</div>';
		var q =
			'/ai/posts?filter=' + encodeURIComponent( aiState.filter ) +
			'&page=' + aiState.page +
			'&per_page=20&search=' + encodeURIComponent( aiState.search );
		api( q ).then( function ( d ) {
			renderAiTable( d );
			updateAiBulkBar();
		} ).catch( function () {
			body.innerHTML = errorState();
		} );
	}

	function renderAiTable( d ) {
		var body = document.getElementById( 'seob-ai-body' );
		if ( ! d.items || ! d.items.length ) {
			body.innerHTML = '<div class="seob-empty"><span class="dashicons dashicons-search"></span><h3>No pages found</h3><p>Try a different filter or search.</p></div>';
			return;
		}

		var rows = d.items.map( function ( it ) {
			var checked = aiState.selected[ it.id ] ? ' checked' : '';
			return (
				'<tr>' +
				'<td style="width:34px"><input type="checkbox" class="seob-ai-check" data-id="' + it.id + '"' + checked + '></td>' +
				'<td><div class="seob-url" style="font-family:inherit;font-weight:600">' + esc( it.title || '(no title)' ) + '</div>' +
				'<div class="seob-anchor">' + esc( it.url ) + '</div></td>' +
				'<td><span class="seob-muted">' + esc( it.post_type ) + '</span></td>' +
				'<td>' + freshBadge( it.status, it.score ) + '</td>' +
				'<td><small class="seob-muted">' + num( it.words ) + ' words</small></td>' +
				'<td style="text-align:right;white-space:nowrap">' +
				'<button class="seob-btn seob-btn--ghost seob-btn--sm seob-ai-preview" data-id="' + it.id + '"><span class="dashicons dashicons-visibility"></span> Preview</button> ' +
				'<button class="seob-btn seob-btn--primary seob-btn--sm seob-ai-copy" data-id="' + it.id + '"><span class="dashicons dashicons-clipboard"></span> Copy</button>' +
				'</td></tr>'
			);
		} ).join( '' );

		var totalPages = Math.max( 1, Math.ceil( d.total / 20 ) );
		body.innerHTML =
			'<table class="seob-table"><thead><tr>' +
			'<th><input type="checkbox" id="seob-ai-all"></th><th>Page</th><th>Type</th><th>Freshness</th><th>Length</th><th></th>' +
			'</tr></thead><tbody>' + rows + '</tbody></table>' +
			'<div class="seob-pager"><div class="seob-pager__info">' + num( d.total ) + ' page(s) · page ' + aiState.page + ' of ' + totalPages + '</div>' +
			'<div class="seob-pager__btns">' +
			'<button class="seob-btn seob-btn--ghost seob-btn--sm" id="seob-ai-prev"' + ( aiState.page <= 1 ? ' disabled' : '' ) + '>Prev</button>' +
			'<button class="seob-btn seob-btn--ghost seob-btn--sm" id="seob-ai-next"' + ( aiState.page >= totalPages ? ' disabled' : '' ) + '>Next</button>' +
			'</div></div>';

		body.querySelectorAll( '.seob-ai-check' ).forEach( function ( cb ) {
			cb.addEventListener( 'change', function () {
				aiState.selected[ cb.dataset.id ] = cb.checked;
				updateAiBulkBar();
			} );
		} );
		var allCb = document.getElementById( 'seob-ai-all' );
		if ( allCb ) {
			allCb.addEventListener( 'change', function () {
				body.querySelectorAll( '.seob-ai-check' ).forEach( function ( cb ) {
					cb.checked = allCb.checked;
					aiState.selected[ cb.dataset.id ] = allCb.checked;
				} );
				updateAiBulkBar();
			} );
		}

		body.querySelectorAll( '.seob-ai-preview' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				aiPreview( b.dataset.id );
			} );
		} );
		body.querySelectorAll( '.seob-ai-copy' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				aiCopySingle( b );
			} );
		} );

		var prev = document.getElementById( 'seob-ai-prev' );
		var next = document.getElementById( 'seob-ai-next' );
		if ( prev ) {
			prev.addEventListener( 'click', function () {
				if ( aiState.page > 1 ) {
					aiState.page--;
					loadAiPosts();
				}
			} );
		}
		if ( next ) {
			next.addEventListener( 'click', function () {
				if ( aiState.page < totalPages ) {
					aiState.page++;
					loadAiPosts();
				}
			} );
		}
	}

	function aiBriefUrl( postId ) {
		return '/ai/brief?post_id=' + encodeURIComponent( postId ) +
			'&template=' + encodeURIComponent( aiState.template ) +
			'&format=' + encodeURIComponent( aiState.format );
	}

	function aiCopySingle( btn ) {
		setBusy( btn, true );
		api( aiBriefUrl( btn.dataset.id ) ).then( function ( r ) {
			copyText( r.content );
			setBusy( btn, false );
			toast( 'Brief copied — paste into Claude or ChatGPT.', 'success' );
		} ).catch( function () {
			setBusy( btn, false );
			toast( cfg.i18n.error, 'error' );
		} );
	}

	function aiPreview( postId ) {
		var box = document.getElementById( 'seob-ai-preview' );
		box.hidden = false;
		box.innerHTML = '<div class="seob-loading"><span class="seob-spinner"></span> Building brief…</div>';
		box.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		api( aiBriefUrl( postId ) ).then( function ( r ) {
			box.innerHTML =
				'<div class="seob-card__head"><div><h2>Preview: ' + esc( r.brief ? r.brief.title : '' ) + '</h2>' +
				'<p>' + esc( r.filename ) + '</p></div>' +
				'<span style="display:inline-flex;gap:8px">' +
				'<button class="seob-btn seob-btn--ghost seob-btn--sm" id="seob-ai-pv-close">Close</button>' +
				'<button class="seob-btn seob-btn--ghost seob-btn--sm" id="seob-ai-pv-dl"><span class="dashicons dashicons-download"></span> Download</button>' +
				'<button class="seob-btn seob-btn--primary seob-btn--sm" id="seob-ai-pv-copy"><span class="dashicons dashicons-clipboard"></span> Copy</button>' +
				'</span></div>' +
				'<pre class="seob-pre">' + esc( r.content ) + '</pre>';
			document.getElementById( 'seob-ai-pv-close' ).addEventListener( 'click', function () {
				box.hidden = true;
			} );
			document.getElementById( 'seob-ai-pv-copy' ).addEventListener( 'click', function () {
				copyText( r.content );
				toast( 'Copied.', 'success' );
			} );
			document.getElementById( 'seob-ai-pv-dl' ).addEventListener( 'click', function () {
				downloadFile( r.filename, r.mime, r.content );
			} );
		} ).catch( function () {
			box.innerHTML = errorState();
		} );
	}

	function aiExportSelected( btn, copyOnly ) {
		var ids = selectedIds();
		if ( ! ids.length ) {
			toast( 'Select at least one page first.', 'error' );
			return;
		}
		setBusy( btn, true );
		api( '/ai/bulk', 'POST', { post_ids: ids, template: aiState.template, format: aiState.format } ).then( function ( r ) {
			setBusy( btn, false );
			if ( ! r.success ) {
				toast( r.message || cfg.i18n.error, 'error' );
				return;
			}
			if ( copyOnly ) {
				copyText( r.content );
				toast( 'Content pack (' + num( r.count ) + ' pages) copied.', 'success' );
			} else {
				downloadFile( r.filename, r.mime, r.content );
				toast( 'Downloaded pack of ' + num( r.count ) + ' pages.', 'success' );
			}
		} ).catch( function () {
			setBusy( btn, false );
			toast( cfg.i18n.error, 'error' );
		} );
	}

	/* ===== Settings ===== */
	Views[ '/settings' ] = function () {
		loading();
		setHeader( 'Settings', 'Broken link scanning &amp; global options.' );
		api( '/settings' ).then( function ( st ) {
			view.innerHTML = '';
			var card = h( '<div class="seob-card"></div>' );
			card.innerHTML = '<div class="seob-card__head"><div><h2>Broken link checker</h2><p>How often to crawl your content for dead links.</p></div></div>';
			card.appendChild( toggleField( 'blc_enabled', 'Enable broken link checker', 'Scan content on a schedule.', st.blc_enabled ) );
			card.appendChild(
				selectField( 'blc_frequency', 'Scan frequency', st.blc_frequency, [
					[ 'hourly', 'Hourly' ],
					[ 'twicedaily', 'Twice daily' ],
					[ 'daily', 'Daily' ],
					[ 'weekly', 'Weekly' ],
				] )
			);
			card.appendChild( pillsField( 'blc_post_types', 'Content to scan', cfg.postTypes, st.blc_post_types ) );
			card.appendChild( numberField( 'blc_timeout', 'Request timeout (seconds)', 'How long to wait for each link to respond.', st.blc_timeout ) );
			view.appendChild( card );

			/* Content freshness thresholds */
			var freshCard = h( '<div class="seob-card seob-mt"></div>' );
			freshCard.innerHTML = '<div class="seob-card__head"><div><h2>Content freshness</h2><p>When to flag content as aging or stale.</p></div></div>';
			freshCard.appendChild( pillsField( 'freshness_post_types', 'Content to audit', cfg.postTypes, st.freshness_post_types ) );
			var fr1 = fieldRow();
			fr1.appendChild( wrapFlex( numberField( 'freshness_aging_months', 'Aging after (months)', 'Flag as "aging" past this age.', st.freshness_aging_months ) ) );
			fr1.appendChild( wrapFlex( numberField( 'freshness_stale_months', 'Stale after (months)', 'Flag as "stale" past this age.', st.freshness_stale_months ) ) );
			freshCard.appendChild( fr1 );
			view.appendChild( freshCard );

			var info = h( '<div class="seob-card seob-mt"></div>' );
			info.innerHTML =
				'<div class="seob-card__head"><div><h2>About SEO Boost</h2></div></div>' +
				'<div class="seob-list">' +
				'<div class="seob-list__row"><span>Sitemap index</span><a class="seob-src" target="_blank" rel="noopener" href="' +
				esc( cfg.homeUrl + 'sitemap.xml' ) + '">' + esc( cfg.homeUrl + 'sitemap.xml' ) + '</a></div>' +
				'<div class="seob-list__row"><span>IndexNow protocol</span><span class="seob-muted">Bing, Yandex, Seznam, Naver &amp; partners</span></div>' +
				'<div class="seob-list__row"><span>Version</span><span class="seob-muted">' + esc( cfg.version || '1.0.0' ) + '</span></div>' +
				'</div>';
			view.appendChild( info );

			setHeader( 'Settings', 'Broken link scanning & global options.', primarySaveBtn() );
			bindSave( function () {
				return {
					blc_enabled: getToggle( 'blc_enabled' ),
					blc_frequency: document.querySelector( '[name="blc_frequency"]' ).value,
					blc_post_types: getPills( 'blc_post_types' ),
					blc_timeout: parseInt( document.querySelector( '[name="blc_timeout"]' ).value, 10 ) || 10,
					freshness_post_types: getPills( 'freshness_post_types' ),
					freshness_aging_months: parseInt( document.querySelector( '[name="freshness_aging_months"]' ).value, 10 ) || 3,
					freshness_stale_months: parseInt( document.querySelector( '[name="freshness_stale_months"]' ).value, 10 ) || 6,
				};
			} );
		} ).catch( function () {
			view.innerHTML = errorState();
		} );
	};

	/* ---------------- Field builders ---------------- */

	function toggleField( name, label, hint, checked ) {
		var f = h( '<div class="seob-field"></div>' );
		f.innerHTML =
			'<label class="seob-toggle"><input type="checkbox" name="' + name + '"' + ( checked ? ' checked' : '' ) + '>' +
			'<span class="seob-toggle__track"></span>' +
			'<span><strong>' + esc( label ) + '</strong><div class="seob-hint" style="margin:0">' + hint + '</div></span></label>';
		return f;
	}

	function numberField( name, label, hint, value ) {
		var f = h( '<div class="seob-field"></div>' );
		f.innerHTML =
			'<label class="seob-label">' + esc( label ) + '</label><p class="seob-hint">' + hint + '</p>' +
			'<input class="seob-input" type="number" name="' + name + '" value="' + esc( value ) + '" style="max-width:200px">';
		return f;
	}

	function textField( name, label, hint, value, placeholder, width ) {
		var f = h( '<div class="seob-field"></div>' );
		f.innerHTML =
			'<label class="seob-label">' + esc( label ) + '</label>' +
			( hint ? '<p class="seob-hint">' + hint + '</p>' : '' ) +
			'<input class="seob-input" type="text" name="' + name + '" value="' + esc( value == null ? '' : value ) + '"' +
			' placeholder="' + esc( placeholder || '' ) + '" style="max-width:' + ( width || '460px' ) + '">';
		return f;
	}

	function textareaField( name, label, hint, value, placeholder ) {
		var f = h( '<div class="seob-field"></div>' );
		f.innerHTML =
			'<label class="seob-label">' + esc( label ) + '</label>' +
			( hint ? '<p class="seob-hint">' + hint + '</p>' : '' ) +
			'<textarea class="seob-input" name="' + name + '" placeholder="' + esc( placeholder || '' ) + '">' +
			esc( value == null ? '' : value ) + '</textarea>';
		return f;
	}

	function fieldRow() {
		return h( '<div class="seob-row"></div>' );
	}

	function selectField( name, label, value, options ) {
		var opts = options
			.map( function ( o ) {
				return '<option value="' + esc( o[ 0 ] ) + '"' + ( o[ 0 ] === value ? ' selected' : '' ) + '>' + esc( o[ 1 ] ) + '</option>';
			} )
			.join( '' );
		var f = h( '<div class="seob-field"></div>' );
		f.innerHTML = '<label class="seob-label">' + esc( label ) + '</label><select class="seob-select" name="' + name + '" style="max-width:260px">' + opts + '</select>';
		return f;
	}

	function pillsField( name, label, options, selected ) {
		selected = selected || [];
		var pills = Object.keys( options || {} )
			.map( function ( slug ) {
				var on = selected.indexOf( slug ) !== -1;
				return (
					'<label class="seob-pill' + ( on ? ' is-checked' : '' ) + '" data-group="' + name + '">' +
					'<input type="checkbox" value="' + esc( slug ) + '"' + ( on ? ' checked' : '' ) + '>' +
					esc( options[ slug ] ) + '</label>'
				);
			} )
			.join( '' );
		var f = h( '<div class="seob-field"></div>' );
		f.innerHTML = '<label class="seob-label">' + esc( label ) + '</label><div class="seob-pills">' + pills + '</div>';
		f.querySelectorAll( '.seob-pill' ).forEach( function ( p ) {
			var input = p.querySelector( 'input' );
			p.addEventListener( 'click', function ( e ) {
				if ( e.target !== input ) {
					input.checked = ! input.checked;
				}
				p.classList.toggle( 'is-checked', input.checked );
			} );
		} );
		return f;
	}

	function getToggle( name ) {
		var el = document.querySelector( '[name="' + name + '"]' );
		return el && el.checked ? 1 : 0;
	}

	function getPills( group ) {
		var out = [];
		document.querySelectorAll( '.seob-pill[data-group="' + group + '"] input:checked' ).forEach( function ( i ) {
			out.push( i.value );
		} );
		return out;
	}

	/* ---------------- Save handling ---------------- */

	function primarySaveBtn() {
		return '<button class="seob-btn seob-btn--primary" id="seob-save"><span class="dashicons dashicons-saved"></span> Save changes</button>';
	}

	function bindSave( collector ) {
		var btn = document.getElementById( 'seob-save' );
		if ( ! btn ) {
			return;
		}
		btn.addEventListener( 'click', function () {
			setBusy( btn, true );
			api( '/settings', 'POST', collector() )
				.then( function ( r ) {
					toast( r.message || cfg.i18n.saved, 'success' );
					setBusy( btn, false );
				} )
				.catch( function () {
					toast( cfg.i18n.error, 'error' );
					setBusy( btn, false );
				} );
		} );
	}

	/* ---------------- Shared helpers ---------------- */

	function setBusy( btn, busy ) {
		if ( ! btn ) {
			return;
		}
		btn.disabled = busy;
		if ( busy ) {
			btn._html = btn.innerHTML;
			btn.innerHTML = '<span class="seob-spinner" style="width:14px;height:14px;border-width:2px"></span>';
		} else if ( btn._html ) {
			btn.innerHTML = btn._html;
		}
	}

	function errorState() {
		return '<div class="seob-empty"><span class="dashicons dashicons-warning" style="color:var(--seob-red)"></span>' +
			'<h3>Could not load data</h3><p>Please refresh the page or check your permissions.</p></div>';
	}

	function downloadFile( filename, mime, content ) {
		var blob = new Blob( [ content ], { type: ( mime || 'text/plain' ) + ';charset=utf-8' } );
		var url = URL.createObjectURL( blob );
		var a = document.createElement( 'a' );
		a.href = url;
		a.download = filename || 'export.txt';
		document.body.appendChild( a );
		a.click();
		document.body.removeChild( a );
		setTimeout( function () {
			URL.revokeObjectURL( url );
		}, 1000 );
	}

	function copyText( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text );
			return;
		}
		var ta = document.createElement( 'textarea' );
		ta.value = text;
		ta.style.position = 'fixed';
		ta.style.opacity = '0';
		document.body.appendChild( ta );
		ta.select();
		try {
			document.execCommand( 'copy' );
		} catch ( e ) {}
		document.body.removeChild( ta );
	}

	function updateBadge( count ) {
		var badge = document.getElementById( 'seob-badge-links' );
		if ( ! badge ) {
			return;
		}
		if ( count > 0 ) {
			badge.textContent = count;
			badge.hidden = false;
		} else {
			badge.hidden = true;
		}
	}

	function refreshBadge() {
		api( '/stats' ).then( function ( s ) {
			updateBadge( s.links.broken );
		} );
	}

	/* ---------------- Router ---------------- */

	function currentRoute() {
		var hash = window.location.hash.replace( /^#/, '' );
		if ( ! hash || hash === '/' ) {
			return '/';
		}
		return hash;
	}

	function router() {
		var route = currentRoute();
		var render = Views[ route ] || Views[ '/' ];

		document.querySelectorAll( '.seob-nav__item' ).forEach( function ( item ) {
			item.classList.toggle( 'is-active', item.getAttribute( 'data-route' ) === route );
		} );

		render();
	}

	window.addEventListener( 'hashchange', router );
	document.addEventListener( 'DOMContentLoaded', function () {
		cfg.version = cfg.version || '1.0.0';
		router();
		refreshBadge();
	} );

	// In case DOMContentLoaded already fired.
	if ( document.readyState !== 'loading' ) {
		router();
		refreshBadge();
	}
} )();
