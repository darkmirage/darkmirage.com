/* DarkMirage Dune: light/dark toggle, click-to-play video, image viewer, mobile sheet. */
(function () {
	'use strict';
	var root = document.documentElement;
	var media = window.matchMedia('(prefers-color-scheme: dark)');

	function isDark() {
		var forced = root.getAttribute('data-theme');
		return forced ? forced === 'dark' : media.matches;
	}

	function label() {
		var text = isDark() ? window.dmdune.toLight : window.dmdune.toDark;
		document.querySelectorAll('[data-dm-toggle]').forEach(function (b) {
			b.setAttribute('aria-label', text);
		});
	}

	document.addEventListener('click', function (e) {
		var toggle = e.target.closest('[data-dm-toggle]');
		if (toggle) {
			var next = isDark() ? 'light' : 'dark';
			// Choosing the mode the OS already uses means "follow the OS" again.
			if ((next === 'dark') === media.matches) {
				root.removeAttribute('data-theme');
				try { localStorage.removeItem('dm-theme'); } catch (err) {}
			} else {
				root.setAttribute('data-theme', next);
				try { localStorage.setItem('dm-theme', next); } catch (err) {}
			}
			label();
			return;
		}

		var play = e.target.closest('[data-yt]');
		if (play) {
			var frame = document.createElement('iframe');
			frame.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(play.getAttribute('data-yt')) + '?autoplay=1&rel=0';
			frame.title = window.dmdune.playLabel;
			frame.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
			frame.allowFullscreen = true;
			play.replaceWith(frame);
			return;
		}

		var link = e.target.closest('a[data-dm-lightbox], .dm-prose a[href]');
		if (link && link.querySelector('img') && /\.(jpe?g|png|gif|webp)(\?.*)?$/i.test(link.getAttribute('href') || '')) {
			if (e.metaKey || e.ctrlKey || e.shiftKey || e.button > 0) return;
			e.preventDefault();
			openViewer(link.href, link.getAttribute('title') || (link.querySelector('img').getAttribute('alt') || ''));
			return;
		}

		// Tapping outside the open "More" sheet closes it.
		document.querySelectorAll('.dm-sheet[open]').forEach(function (d) {
			if (!d.contains(e.target)) d.removeAttribute('open');
		});
	});

	function openViewer(src, caption) {
		var dlg = document.createElement('dialog');
		dlg.className = 'dm-lightbox';
		var fig = document.createElement('figure');
		var img = document.createElement('img');
		img.src = src;
		img.alt = caption;
		fig.appendChild(img);
		if (caption) {
			var cap = document.createElement('figcaption');
			cap.textContent = caption;
			fig.appendChild(cap);
		}
		var close = document.createElement('button');
		close.type = 'button';
		close.setAttribute('aria-label', window.dmdune.close);
		close.textContent = '×';
		close.addEventListener('click', function () { dlg.close(); });
		dlg.appendChild(close);
		dlg.appendChild(fig);
		dlg.addEventListener('click', function (ev) { if (ev.target === dlg || ev.target === fig) dlg.close(); });
		dlg.addEventListener('close', function () { dlg.remove(); });
		document.body.appendChild(dlg);
		dlg.showModal();
	}

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') {
			document.querySelectorAll('.dm-sheet[open]').forEach(function (d) { d.removeAttribute('open'); });
		}
	});

	// A missing thumbnail means YouTube has removed the video.
	function markGone(img) {
		var fig = img.closest('.dm-video');
		if (fig) fig.classList.add('is-gone');
	}
	document.querySelectorAll('.dm-video__play img').forEach(function (img) {
		if (img.complete && img.naturalWidth === 0) markGone(img);
		img.addEventListener('error', function () { markGone(img); });
		// YouTube serves a 120px grey placeholder (status 200) for some removed videos.
		img.addEventListener('load', function () { if (img.naturalWidth <= 120) markGone(img); });
	});

	if (media.addEventListener) media.addEventListener('change', label);
	label();
})();
