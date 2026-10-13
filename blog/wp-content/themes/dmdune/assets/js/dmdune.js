/* DarkMirage Dune (blog only): click-to-play video, image viewer, card picture fitting, taglines.
   The toggle, header and sheet live in chrome.js. */
(function () {
	'use strict';

	document.addEventListener('click', function (e) {
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
		if (link && isImageLink(link)) {
			if (e.metaKey || e.ctrlKey || e.shiftKey || e.button > 0) return;
			e.preventDefault();
			openViewer(groupOf(link), link);
			return;
		}

	});

	function isImageLink(a) {
		return a.querySelector('img') && /\.(jpe?g|png|gif|webp)(\?.*)?$/i.test(a.getAttribute('href') || '');
	}

	// The old Lightbox/Thickbox plugins grouped pictures into sets with rel="lightbox[name]" (or any
	// shared rel). Pictures in one set browse together; anything else opens on its own.
	function groupOf(link) {
		var rel = link.getAttribute('rel') || '';
		var scope = link.closest('.dm-prose') || document;
		var all = Array.prototype.filter.call(scope.querySelectorAll('a[href]'), isImageLink);
		if (rel && rel !== 'lightbox') {
			return all.filter(function (a) { return a.getAttribute('rel') === rel; });
		}
		var p = link.closest('.thumbnails, .dm-figure, p');
		if (p && p.classList.contains('thumbnails')) {
			return all.filter(function (a) { return p.contains(a); });
		}
		return [link];
	}

	function captionOf(a) {
		var img = a.querySelector('img');
		var fig = a.closest('figure');
		var cap = fig && fig.querySelector('figcaption');
		return a.getAttribute('title') || (cap && cap.textContent.trim()) || (img && img.getAttribute('alt')) || '';
	}

	function openViewer(set, start) {
		var index = Math.max(0, set.indexOf(start));
		var dlg = document.createElement('dialog');
		dlg.className = 'dm-lightbox';
		dlg.setAttribute('aria-label', window.dmdune.viewer);
		var fig = document.createElement('figure');
		var img = document.createElement('img');
		var cap = document.createElement('figcaption');
		fig.appendChild(img);
		fig.appendChild(cap);

		function button(cls, label, text, fn) {
			var b = document.createElement('button');
			b.type = 'button';
			b.className = cls;
			b.setAttribute('aria-label', label);
			b.textContent = text;
			b.addEventListener('click', function (ev) { ev.stopPropagation(); fn(); });
			dlg.appendChild(b);
			return b;
		}
		function show(i) {
			index = (i + set.length) % set.length;
			img.src = set[index].href;
			img.alt = captionOf(set[index]);
			var counter = set.length > 1 ? (index + 1) + ' / ' + set.length : '';
			cap.textContent = [img.alt, counter].filter(Boolean).join('  ·  ');
			if (set.length > 1) {
				new Image().src = set[(index + 1) % set.length].href;
			}
		}
		button('dm-lightbox__close', window.dmdune.close, '×', function () { dlg.close(); });
		if (set.length > 1) {
			button('dm-lightbox__prev', window.dmdune.prev, '‹', function () { show(index - 1); });
			button('dm-lightbox__next', window.dmdune.next, '›', function () { show(index + 1); });
		}
		dlg.appendChild(fig);
		dlg.addEventListener('click', function (ev) { if (ev.target === dlg || ev.target === fig) dlg.close(); });
		dlg.addEventListener('keydown', function (ev) {
			if (ev.key === 'ArrowLeft') show(index - 1);
			if (ev.key === 'ArrowRight') show(index + 1);
		});
		var x0 = null;
		fig.addEventListener('pointerdown', function (ev) { x0 = ev.clientX; });
		fig.addEventListener('pointerup', function (ev) {
			if (x0 !== null && set.length > 1 && Math.abs(ev.clientX - x0) > 50) show(index + (ev.clientX < x0 ? 1 : -1));
			x0 = null;
		});
		dlg.addEventListener('close', function () { dlg.remove(); });
		document.body.appendChild(dlg);
		show(index);
		dlg.showModal();
	}

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

	// A random subtitle per visit, as the Randomize plugin did from 2005.
	var tag = document.querySelector('.dm-hero__tag[data-taglines]');
	if (tag) {
		try {
			var lines = JSON.parse(tag.getAttribute('data-taglines'));
			if (lines.length) {
				tag.textContent = lines[Math.floor(Math.random() * lines.length)];
				tag.lang = /[\u3040-\u30ff\u4e00-\u9fff]/.test(tag.textContent) ? 'ja' : 'en';
			}
		} catch (err) {}
	}

	// Card pictures: crop to fill when that looks fine, otherwise show the whole picture letterboxed.
	function fitMedia(img) {
		var box = img.parentNode;
		if (box.classList.contains('dm-media--yt')) return;
		var bw = box.clientWidth, bh = box.clientHeight, nw = img.naturalWidth, nh = img.naturalHeight;
		if (!bw || !bh || !nw || !nh) return;
		var upscale = Math.max(bw / nw, bh / nh);
		var mismatch = Math.abs(Math.log((nw / nh) / (bw / bh)));
		// Wide panoramas fill by cropping the sides (their middle is the subject); everything else that
		// would be badly cropped or blown up is letterboxed.
		var wider = nw / nh > bw / bh;
		var fit = upscale > 1.6 || (mismatch > Math.log(1.7) && !(wider && nw / nh <= 5 && upscale <= 1.6));
		box.classList.toggle('is-fit', fit);
		// Never enlarge a letterboxed picture: old logos and screencaps are small, often palette PNGs
		// with hard edges, and any upscaling makes their edges stair-step.
		box.style.setProperty('--nw', nw + 'px');
		box.style.setProperty('--nh', nh + 'px');
	}
	function watchMedia() {
		document.querySelectorAll('.dm-media__img').forEach(function (img) {
			if (img.complete && img.naturalWidth) fitMedia(img);
			else img.addEventListener('load', function () { fitMedia(img); });
		});
	}
	watchMedia();
	var mediaQueued = false;
	window.addEventListener('resize', function () {
		if (mediaQueued) return;
		mediaQueued = true;
		requestAnimationFrame(function () {
			mediaQueued = false;
			document.querySelectorAll('.dm-media__img').forEach(function (img) { if (img.naturalWidth) fitMedia(img); });
		});
	});

})();
