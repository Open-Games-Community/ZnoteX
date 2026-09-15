/* ZnoteX Admin Control Panel - day/night theme, menu search, side sections. */
(function () {
	'use strict';

	var root = document.documentElement;
	var backdrop = document.getElementById('acpBackdrop');
	var burger = document.getElementById('acpBurger');
	var filter = document.getElementById('acpFilter');
	var nav = document.getElementById('acpNav');
	var themeBtn = document.getElementById('acpTheme');
	var MOBILE = '(max-width: 840px)';

	function store(key, value) {
		try { localStorage.setItem('acp.' + key, value); } catch (e) {}
	}

	function isMobile() {
		return window.matchMedia(MOBILE).matches;
	}

	function openDrawer(open) {
		root.classList.toggle('acp-nav-open', open);
		if (backdrop) { backdrop.hidden = !open; }
	}

	if (burger) {
		burger.addEventListener('click', function () {
			if (isMobile()) {
				openDrawer(!root.classList.contains('acp-nav-open'));
				return;
			}

			var closed = root.classList.toggle('acp-sidebar-closed');
			store('sidebar_closed', closed ? '1' : '0');
		});
	}
	if (backdrop) {
		backdrop.addEventListener('click', function () { openDrawer(false); });
	}
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') { openDrawer(false); }
	});
	window.addEventListener('resize', function () {
		if (!isMobile()) { openDrawer(false); }
	});

	if (nav) {
		Array.prototype.slice.call(nav.querySelectorAll('.acp-nav-group')).forEach(function (group) {
			var button = group.querySelector('.acp-nav-group-label');
			var active = group.querySelector('.acp-nav-link.is-active');
			group.classList.toggle('is-open', !!active);
			if (!active && group === nav.querySelector('.acp-nav-group')) {
				group.classList.add('is-open');
			}
			if (button) {
				button.setAttribute('aria-expanded', group.classList.contains('is-open') ? 'true' : 'false');
				button.addEventListener('click', function () {
					var open = group.classList.toggle('is-open');
					button.setAttribute('aria-expanded', open ? 'true' : 'false');
				});
			}
		});

		// Same collapse behaviour, one level down: a plugin's own sub-list of
		// pages inside a group. Already open when one of its pages is the
		// current one (server-rendered with is-open), closed otherwise.
		Array.prototype.slice.call(nav.querySelectorAll('.acp-nav-plugin')).forEach(function (cluster) {
			var button = cluster.querySelector('.acp-nav-plugin-label');
			if (!button) { return; }
			button.addEventListener('click', function () {
				var open = cluster.classList.toggle('is-open');
				button.setAttribute('aria-expanded', open ? 'true' : 'false');
			});
		});
	}

	if (filter && nav) {
		var links = Array.prototype.slice.call(nav.querySelectorAll('.acp-nav-link'));
		var clusters = Array.prototype.slice.call(nav.querySelectorAll('.acp-nav-plugin'));
		var groups = Array.prototype.slice.call(nav.querySelectorAll('.acp-nav-group'));
		var noMatch = nav.querySelector('.acp-nav-nomatch');

		function applyFilter() {
			var q = filter.value.trim().toLowerCase();
			var hits = 0;

			links.forEach(function (link) {
				var match = q === '' || (link.getAttribute('data-title') || '').indexOf(q) !== -1;
				link.parentNode.hidden = !match;
				if (match) { hits++; }
			});

			// A plugin's own <li> never matches .acp-nav-link (it is the
			// cluster wrapper, not a page), so it needs its own visibility
			// pass based on whether any page inside it just matched above.
			clusters.forEach(function (cluster) {
				var visible = cluster.querySelectorAll('.acp-nav-link:not([hidden])').length > 0;
				cluster.hidden = !visible;
				if (q !== '' && visible) {
					cluster.classList.add('is-open');
					var pbutton = cluster.querySelector('.acp-nav-plugin-label');
					if (pbutton) { pbutton.setAttribute('aria-expanded', 'true'); }
				}
			});

			groups.forEach(function (group) {
				var visible = group.querySelectorAll('li:not([hidden])').length > 0;
				group.hidden = !visible;
				if (q !== '' && visible) {
					group.classList.add('is-open');
					var button = group.querySelector('.acp-nav-group-label');
					if (button) { button.setAttribute('aria-expanded', 'true'); }
				}
			});

			if (noMatch) { noMatch.hidden = hits > 0; }
		}

		filter.addEventListener('input', applyFilter);
		filter.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				filter.value = '';
				applyFilter();
			}
			if (e.key === 'Enter') {
				var first = nav.querySelector('li:not([hidden]) .acp-nav-link');
				if (first) { window.location.href = first.href; }
			}
		});
	}

	function syncTheme() {
		if (!themeBtn) { return; }
		var dark = root.getAttribute('data-acp-theme') === 'dark';
		var icon = themeBtn.querySelector('.fa');
		var label = themeBtn.querySelector('span');
		if (icon) { icon.className = 'fa ' + (dark ? 'fa-sun-o' : 'fa-moon-o'); }
		if (label) { label.textContent = dark ? 'Day' : 'Night'; }
		themeBtn.title = dark ? 'Switch to day mode' : 'Switch to night mode';
	}

	if (themeBtn) {
		themeBtn.addEventListener('click', function () {
			var dark = root.getAttribute('data-acp-theme') === 'dark';
			root.setAttribute('data-acp-theme', dark ? 'light' : 'dark');
			store('theme', dark ? 'light' : 'dark');
			syncTheme();
		});
		syncTheme();
	}

	var shopPreview = document.getElementById('shopOfferPreview');
	var shopType = document.getElementById('type');
	var shopItem = document.getElementById('itemid');
	var shopCount = document.getElementById('count');

	if (shopPreview && shopType && shopItem && shopCount) {
		function image(src, className) {
			var img = document.createElement('img');
			img.src = src;
			img.className = className;
			img.alt = '';
			return img;
		}

		function outfitUrl(outfitId, mountId) {
			var server = shopPreview.getAttribute('data-outfit-server') || '';
			var count = parseInt(shopCount.value, 10) || 0;
			if (!server || !outfitId) { return ''; }

			var url = server + '?id=' + encodeURIComponent(outfitId)
				+ '&addons=' + encodeURIComponent(count)
				+ '&head=78&body=68&legs=58&feet=76&direction=2';

			if (mountId) {
				url += '&mount=' + encodeURIComponent(mountId);
			}

			return url;
		}

		function updateShopPreview() {
			var type = parseInt(shopType.value, 10) || 0;
			var raw = shopItem.value.trim();
			var template = shopPreview.getAttribute('data-item-template') || '';

			shopPreview.innerHTML = '';

			if (type === 5) {
				var pair = raw.match(/^\s*(\d+)\s*,\s*(\d+)\s*$/);
				if (pair) {
					shopPreview.appendChild(image(outfitUrl(pair[1], ''), 'acp-shop-preview-img'));
					shopPreview.appendChild(image(outfitUrl(pair[2], ''), 'acp-shop-preview-img'));
					return;
				}
			} else if (type === 6) {
				if (/^\d+$/.test(raw)) {
					shopPreview.appendChild(image(outfitUrl(128, raw), 'acp-shop-preview-img'));
					return;
				}
			} else if (/^\d+$/.test(raw) && template) {
				shopPreview.appendChild(image(template.replace('{id}', raw), 'acp-shop-preview-item'));
				return;
			}

			var muted = document.createElement('span');
			muted.className = 'is-muted';
			muted.appendChild(document.createTextNode('Preview'));
			shopPreview.appendChild(muted);
		}

		shopType.addEventListener('change', updateShopPreview);
		shopItem.addEventListener('input', updateShopPreview);
		shopCount.addEventListener('input', updateShopPreview);
		updateShopPreview();
	}

	document.addEventListener('submit', function (e) {
		var form = e.target;
		var message = form.getAttribute('data-confirm');
		if (message && !window.confirm(message)) {
			e.preventDefault();
		}
	});
})();

/*
 * Click-to-sort for any <table class="acp-table" data-sortable>. Sorts the
 * rows currently in the DOM - on a paginated list that is only the current
 * page, which is fine: the point is letting an admin re-order what is on
 * screen (highest points first, most recent first), not a full-dataset sort.
 */
(function () {
	function cellText(row, index) {
		var cell = row.children[index];
		if (!cell) return '';
		return (cell.getAttribute('data-sort-value') || cell.textContent || '').trim();
	}

	function looksNumeric(value) {
		return value !== '' && !isNaN(parseFloat(value.replace(/[^0-9.\-]/g, ''))) && /^[\s0-9.,\-]+$/.test(value);
	}

	Array.prototype.slice.call(document.querySelectorAll('table.acp-table[data-sortable]')).forEach(function (table) {
		var thead = table.querySelector('thead');
		var tbody = table.querySelector('tbody');
		if (!thead || !tbody) return;

		var headers = Array.prototype.slice.call(thead.querySelectorAll('th'));

		headers.forEach(function (th, index) {
			if (th.textContent.trim() === '') return; // icon/action-only columns stay unsortable

			th.classList.add('acp-th-sortable');
			th.setAttribute('role', 'button');
			th.setAttribute('tabindex', '0');

			var sort = function () {
				var dir = th.getAttribute('data-sort-dir') === 'asc' ? 'desc' : 'asc';
				headers.forEach(function (h) {
					h.removeAttribute('data-sort-dir');
					h.classList.remove('is-sorted-asc', 'is-sorted-desc');
				});
				th.setAttribute('data-sort-dir', dir);
				th.classList.add(dir === 'asc' ? 'is-sorted-asc' : 'is-sorted-desc');

				var rows = Array.prototype.slice.call(tbody.querySelectorAll(':scope > tr'));
				var numeric = rows.length > 0 && looksNumeric(cellText(rows[0], index));

				rows.sort(function (a, b) {
					var av = cellText(a, index);
					var bv = cellText(b, index);
					var cmp;
					if (numeric) {
						cmp = (parseFloat(av.replace(/[^0-9.\-]/g, '')) || 0) - (parseFloat(bv.replace(/[^0-9.\-]/g, '')) || 0);
					} else {
						cmp = av.localeCompare(bv, undefined, { sensitivity: 'base', numeric: true });
					}
					return dir === 'asc' ? cmp : -cmp;
				});

				rows.forEach(function (row) { tbody.appendChild(row); });
			};

			th.addEventListener('click', sort);
			th.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); sort(); }
			});
		});
	});
})();

/*
 * Client-side row filter for small lists (plugins, shop offers - anything
 * that is already fully rendered on the page, not a paginated server query).
 * Usage: <input data-acp-search-input="tableId"> filters
 * #tableId tbody tr[data-acp-search] by substring match, and optionally
 * updates a live count in [data-acp-search-count="tableId"].
 */
(function () {
	Array.prototype.slice.call(document.querySelectorAll('[data-acp-search-input]')).forEach(function (input) {
		var tableId = input.getAttribute('data-acp-search-input');
		var table = document.getElementById(tableId);
		if (!table) return;

		var rows  = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-acp-search]'));
		var count = document.querySelector('[data-acp-search-count="' + tableId + '"]');

		input.addEventListener('keyup', function () {
			var needle = input.value.trim().toLowerCase();
			var shown = 0;
			rows.forEach(function (row) {
				var match = needle === '' || (row.getAttribute('data-acp-search') || '').indexOf(needle) !== -1;
				row.hidden = !match;
				if (match) shown++;
			});
			if (count) count.textContent = needle === '' ? '' : (shown + ' / ' + rows.length);
		});
	});
})();

/*
 * Dynamic add/remove-row tables: any element with data-acp-table-add="key"
 * clones the row template registered as data-acp-table-template="key" into
 * the matching [data-acp-table="key"] container; data-acp-table-remove
 * removes its own row. Shared by settings.php's 'table' schema fields and
 * the serverdata single-record editors (items/creatures attribute rows).
 */
(function () {
	document.addEventListener('click', function (e) {
		var addBtn = e.target.closest('[data-acp-table-add]');
		if (addBtn) {
			var key = addBtn.getAttribute('data-acp-table-add');
			var container = document.querySelector('[data-acp-table="' + key + '"]');
			var template = document.querySelector('template[data-acp-table-template="' + key + '"]');
			if (!container || !template) return;
			var body = container.querySelector('[data-acp-table-body]') || container;
			// <tr> fragments only parse correctly inside a <tbody> wrapper; anything
			// else (the permissions table's plain <div> account blocks) parses fine
			// in a generic <div>.
			var wrapper = document.createElement(body.tagName === 'TBODY' ? 'tbody' : 'div');
			var nextIndex = body.children.length;
			var html = template.innerHTML.split('__ROWIDX__').join(String(nextIndex));
			wrapper.innerHTML = html;
			body.appendChild(wrapper.firstElementChild);
			return;
		}

		var removeBtn = e.target.closest('[data-acp-table-remove]');
		if (removeBtn) {
			var row = removeBtn.closest('tr, .acp-perm-account');
			if (row) row.remove();
		}
	});
})();

/* Ctrl+K / Cmd+K jumps focus to the top-bar search, from anywhere in the panel. */
(function () {
	document.addEventListener('keydown', function (e) {
		if (!(e.ctrlKey || e.metaKey) || e.key.toLowerCase() !== 'k') return;
		var input = document.getElementById('acpTopSearch');
		if (!input) return;
		e.preventDefault();
		input.focus();
		input.select();
	});
})();

/* Arriving from search: highlight the field the anchor points at. */
(function () {
	var id = (window.location.hash || '').replace('#', '');
	if (!id) return;

	var el = document.getElementById(id);
	if (!el) return;

	var field = el.closest ? el.closest('.acp-field') : null;
	var target = field || el;

	target.classList.add('acp-field--found');
	setTimeout(function () { target.classList.remove('acp-field--found'); }, 2600);

	setTimeout(function () {
		target.scrollIntoView({ behavior: 'smooth', block: 'center' });
		if (el.focus && el.type !== 'checkbox') { try { el.focus({ preventScroll: true }); } catch (e) {} }
	}, 60);
})();
