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
	}

	if (filter && nav) {
		var links = Array.prototype.slice.call(nav.querySelectorAll('.acp-nav-link'));
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
