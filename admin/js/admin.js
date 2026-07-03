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

			/* Broken links call to action */
			if ( s.links.broken > 0 ) {
				var cta = h( '<div class="seob-card seob-mt"></div>' );
				cta.innerHTML =
					'<div class="seob-card__head"><div><h2>⚠️ ' + num( s.links.broken ) + ' broken link(s) found</h2>' +
					'<p>Broken links hurt user experience and crawlability. Review and fix them.</p></div>' +
					'<a class="seob-btn seob-btn--primary" href="#/links">Review links</a></div>';
				view.appendChild( cta );
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
				'<p class="seob-hint">Served automatically at <code>' + esc( cfg.homeUrl + stats.indexnow.key + '.txt' ) + '</code></p>' +
				'<div class="seob-keybox"><code id="seob-key">' + esc( stats.indexnow.key ) + '</code>' +
				'<button class="seob-btn seob-btn--ghost seob-btn--sm" id="seob-regen" style="margin-left:auto"><span class="dashicons dashicons-update"></span> Regenerate</button></div>';
			card.appendChild( keyField );
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
			return '<div class="seob-list__row"><div><strong>' + num( item.count ) + ' URL(s)</strong>' +
				'<br><small>' + esc( item.time ) + ' — ' + esc( item.message ) + '</small></div>' + badge + '</div>';
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
