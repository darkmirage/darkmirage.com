/* DarkMirage chrome (www, lyrics, gallery): light/dark toggle, header overflow menu, mobile sheet. */
(function () {
	'use strict';
	var root = document.documentElement;
	var media = window.matchMedia('(prefers-color-scheme: dark)');

	function isDark() {
		var forced = root.getAttribute('data-theme');
		return forced ? forced === 'dark' : media.matches;
	}

	function label() {
		var text = isDark() ? 'Switch to light mode' : 'Switch to dark mode';
		document.querySelectorAll('[data-dm-toggle]').forEach(function (b) { b.setAttribute('aria-label', text); });
	}

	// One cookie for all *.darkmirage.com sites, so the choice follows the reader between them.
	// "system" is stored rather than deleted, or another site's localStorage fallback would win.
	function save(mode) {
		var value = mode || 'system';
		var domain = /(^|\.)darkmirage\.com$/.test(location.hostname) ? '; domain=.darkmirage.com' : '';
		document.cookie = 'dm-theme=' + value + '; path=/; SameSite=Lax; max-age=31536000' + domain;
		try { localStorage.setItem('dm-theme', value); } catch (err) {}
	}

	// A page restored by Back, or a tab left open, may predate a change made on another site.
	function sync() {
		var m = (document.cookie.match(/(?:^|; )dm-theme=(light|dark|system)/) || [])[1];
		if (!m) return;
		if (m === 'system') root.removeAttribute('data-theme');
		else root.setAttribute('data-theme', m);
		label();
	}
	window.addEventListener('pageshow', sync);
	document.addEventListener('visibilitychange', function () { if (!document.hidden) sync(); });

	// Era skins: one cookie across the three sites, then reload so the server sends that era.
	document.addEventListener('click', function (e) {
		var era = e.target.closest('[data-dm-skin]');
		if (!era) return;
		var skin = era.getAttribute('data-dm-skin');
		// Picked from the time machine: reopen it on the next page (see dmc_time_machine_html).
		if (era.closest('.dm-tmw')) {
			try { sessionStorage.setItem('dm-tm-open', '1'); } catch (err) {}
		}
		var domain = /(^|\.)darkmirage\.com$/.test(location.hostname) ? '; domain=.darkmirage.com' : '';
		document.cookie = 'dm-skin=' + skin + '; path=/; SameSite=Lax' + domain + (skin ? '; max-age=31536000' : '; max-age=0');
		// A shared ?era= link would otherwise win over the era just picked.
		var url = new URL(location.href);
		if (url.searchParams.has('era')) {
			url.searchParams.delete('era');
			location.replace(url.toString());
		} else {
			location.reload();
		}
	});

	// In an old era, "Copy link" shares this page in that era (?era=<theme>): the share sheet on
	// phones, the clipboard elsewhere.
	(function () {
		var tm = document.querySelector('.dm-tmw');
		var btn = tm && tm.querySelector('.dm-tmw__share');
		var cur = tm && tm.querySelector('[data-dm-skin][aria-pressed="true"]');
		var skin = cur && cur.getAttribute('data-dm-skin');
		if (!btn || !skin) return;
		var text = btn.textContent;
		btn.addEventListener('click', function () {
			var canon = document.querySelector('link[rel="canonical"]');
			var url = new URL(canon ? canon.href : location.href);
			url.searchParams.set('era', skin);
			var link = url.toString();
			var done = function (msg) {
				btn.textContent = msg;
				setTimeout(function () { btn.textContent = text; }, 1800);
			};
			if (navigator.share && matchMedia('(pointer: coarse)').matches) {
				navigator.share({ title: document.title, url: link }).catch(function () {});
			} else if (navigator.clipboard) {
				navigator.clipboard.writeText(link).then(function () { done('Copied!'); }, function () { window.prompt('Copy this link:', link); });
			} else {
				window.prompt('Copy this link:', link);
			}
		});
	})();

	document.addEventListener('click', function (e) {
		var toggle = e.target.closest('[data-dm-toggle]');
		if (toggle) {
			var next = isDark() ? 'light' : 'dark';
			// Choosing the mode the OS already uses means "follow the OS" again.
			if ((next === 'dark') === media.matches) {
				root.removeAttribute('data-theme');
				save(null);
			} else {
				root.setAttribute('data-theme', next);
				save(next);
			}
			label();
			return;
		}
		// Tapping outside an open menu closes it.
		document.querySelectorAll('.dm-sheet[open], .dm-nav__more[open], .dm-tmw[open]').forEach(function (d) {
			if (!d.contains(e.target)) d.removeAttribute('open');
		});
	});

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') {
			document.querySelectorAll('.dm-sheet[open], .dm-nav__more[open], .dm-tmw[open]').forEach(function (d) { d.removeAttribute('open'); });
		}
	});

	// Header: "More" always holds the links the server put there (Japanese lessons, Capsule); links
	// that don't fit on one line join them, so the bar never wraps.
	function fitNav() {
		var navList = document.querySelector('.dm-nav__links');
		var navMore = document.querySelector('.dm-nav__more');
		if (!navList || !navMore) return;
		var menu = navMore.querySelector('ul');
		if (!navMore.dmPinned) navMore.dmPinned = Array.prototype.slice.call(menu.children);
		var pinned = navMore.dmPinned;
		navList.classList.add('is-managed');
		var items = Array.prototype.slice.call(navList.children);
		items.forEach(function (li) { li.hidden = false; });
		navMore.hidden = !pinned.length;
		navMore.removeAttribute('open');
		menu.textContent = '';
		if (navList.offsetParent && navList.scrollWidth > navList.clientWidth) {
			navMore.hidden = false;
			for (var i = items.length - 1; i > 0 && navList.scrollWidth > navList.clientWidth; i--) {
				items[i].hidden = true;
			}
		}
		var current = false;
		items.forEach(function (li) {
			if (!li.hidden) return;
			menu.appendChild(li.cloneNode(true)).hidden = false;
			if (li.querySelector('[aria-current]')) current = true;
		});
		pinned.forEach(function (li) {
			menu.appendChild(li);
			if (li.querySelector('[aria-current]')) current = true;
		});
		navMore.classList.toggle('has-current', current);
	}
	var fitQueued = false;
	window.addEventListener('resize', function () {
		if (fitQueued) return;
		fitQueued = true;
		requestAnimationFrame(function () { fitQueued = false; fitNav(); });
	});
	function start() {
		label();
		// Measured in the stand-in font, the links can fold into More only to come back out when the
		// real font lands a moment later; that jump is worse than waiting for it.
		if (document.fonts && document.fonts.ready && document.fonts.status !== 'loaded') document.fonts.ready.then(fitNav);
		else fitNav();
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
	if (media.addEventListener) media.addEventListener('change', label);
})();
