/*
 * The home page's zoomable timeline: every post from 2003 to 2013 in one continuous list, at
 * five levels of zoom (Years, Months, Titles, Cards, Posts), with a date scrubber on the right,
 * a "random" button and the current year pinned at the top.
 *
 * It opens at Posts, looking exactly like the plain home page: the server-rendered rows stay
 * where they are (so taking over moves nothing on screen) and the list simply carries on below
 * them. /wp-json/dmdune/v1/timeline gives one compact row per post (~55 KB gzipped).
 *
 * Performance: every generated row at a level has a fixed height, so each row's position is a
 * prefix sum and the row at any scroll offset is a binary search; nothing is measured while
 * scrolling. Only rows within a screen of the viewport exist in the DOM (well under 100 at any
 * zoom), placed with transforms. Excerpts load a year at a time; pictures load lazily. Zooming
 * keeps the post at the middle of the screen in place.
 */
(function () {
	'use strict';
	var cfg = window.dmTimeline;
	var feed = document.querySelector('.dm-feed');
	var log = feed && feed.querySelector('.dm-log');
	if (!cfg || !log || !window.fetch) return;

	var LEVELS = ['Years', 'Months', 'Titles', 'Cards', 'Posts'];
	var MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
	var JA = { anime: 'アニメ', blog: 'ブログ', otaku: 'オタク', games: 'ゲーム', music: '音楽', money: 'お金', reviews: 'レビュー', tech: '技術', rant: '愚痴', figures: 'フィギュア', manga: '漫画', world: '世界', japanese: '日本語', news: 'ニュース' };
	var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var tz = (+cfg.tz || 0) * 1000;

	var posts = [];
	var level = 4;
	var rows = [];
	var offs = [];
	var postRow = [];
	var total = 0;
	var nodes = new Map();
	var excerpts = {};
	var asked = {};
	var head = [];   // the server-rendered rows (Posts level only): their <article>s, newest first
	var root, canvas, ui, ctl, scrub, scrubThumb, bubble, label, btnOut, btnIn, stickyYear;
	var canvasTop = 0;
	var queued = false;
	var settleTimer = 0;
	var highlightId = 0;
	var focus = -1;  // the post a zoom started from, so zooming back in returns to it

	function esc(s) {
		return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; });
	}
	function pad(n) { return n < 10 ? '0' + n : String(n); }
	function docTop(el) {
		var y = 0;
		for (; el; el = el.offsetParent) y += el.offsetTop;
		return y;
	}

	function parse(data) {
		posts = data.map(function (r) {
			var d = new Date(r[1] * 1000 + tz);
			return {
				id: r[0], title: r[2] || '(untitled)', cat: r[3], era: r[4], n: r[5], locked: !!r[6], thumb: r[7], url: r[8],
				y: d.getUTCFullYear(), m: d.getUTCMonth(), d: d.getUTCDate(),
				date: d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate())
			};
		});
	}

	/* ---------- Layout: rows and their fixed heights for the current level ---------- */

	var H = null;
	/* Heights come from rendering a sample of each row type off screen, so they follow the CSS
	   at every width. A post row takes the height of the server-rendered ones when they agree. */
	function heights() {
		var probe = document.createElement('div');
		probe.className = 'tl-probe tl tl--l' + level;
		probe.style.width = canvas.clientWidth + 'px';
		canvas.appendChild(probe);
		function measureHtml(t, extra) {
			probe.innerHTML = '<div class="tl-row tl-row--' + t + '" style="position:static">' + html(extra) + '</div>';
			var x = probe.querySelector('[data-x]');
			if (x) x.textContent = new Array(60).join('wwww ');
			return probe.firstChild.offsetHeight;
		}
		var sample = { id: 0, title: new Array(30).join('Wwww '), cat: 'blog', era: 'now', n: 9, locked: false, thumb: '', url: '#', y: 2013, m: 0, d: 1, date: '2013-01-01' };
		posts.push(sample);
		var p = posts.length - 1;
		var h = {
			yhdr: measureHtml('yhdr', { t: 'yhdr', y: 2013, p: p }),
			post: measureHtml('post', { t: 'post', p: p }),
			titleCss: (function (cs) { return { fontWeight: cs.fontWeight, fontSize: cs.fontSize, fontFamily: cs.fontFamily, lineHeight: cs.lineHeight }; })(getComputedStyle(probe.querySelector('.dm-tx__title'))),
			titleWidth: probe.querySelector('.dm-tx__title').clientWidth,
			card: measureHtml('card', { t: 'card', p: p }),
			title: measureHtml('title', { t: 'title', p: p }),
			mhdr: measureHtml('mhdr', { t: 'mhdr', y: 2013, m: 0, p: p }),
			band: measureHtml('band', { t: 'band', y: 2013, m: 0, p: p, to: p + 1 }),
			year0: measureHtml('year0', { t: 'year0', y: 2013, p: p, to: p + 1, months: [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], top: p }),
			end: 150
		};
		// Where a title's wrapping changes a post row's height (phones), rows are sized per title.
		sample.title = 'W';
		var one = measureHtml('post', { t: 'post', p: p });
		var tc = h.titleCss;
		h.titleFont = tc.fontWeight + ' ' + tc.fontSize + ' ' + tc.fontFamily;
		h.titleLine = h.post > one ? parseFloat(tc.lineHeight) : 0;
		delete h.titleCss;
		h.postOne = one;
		posts.pop();
		probe.remove();
		if (level === 4 && head.length) {
			var hs = head.map(function (a) { return a.offsetHeight; });
			if (!h.titleLine && hs.every(function (v) { return v === hs[0]; })) h.post = hs[0];
		}
		return h;
	}

	var ctx2d = null;
	/* Lines a title wraps to in a post row, by measuring its words in the title font. */
	function titleLines(p) {
		ctx2d = ctx2d || document.createElement('canvas').getContext('2d');
		ctx2d.font = H.titleFont;
		var max = H.titleWidth;
		var lines = 1, x = p.locked ? 19 : 0;
		var space = ctx2d.measureText(' ').width;
		p.title.split(/\s+/).forEach(function (word) {
			var w = ctx2d.measureText(word).width;
			if (w > max) {
				// No spaces to break at (Japanese, long URLs): it breaks between characters.
				for (var c = 0; c < word.length; c++) {
					var cw = ctx2d.measureText(word[c]).width;
					if (x + cw > max) { lines++; x = 0; }
					x += cw;
				}
				x += space;
				return;
			}
			if (x > 0 && x + w > max) { lines++; x = 0; }
			x += w + space;
		});
		return Math.min(3, lines);
	}
	function postHeight(k) { return H.titleLine ? H.postOne + (titleLines(posts[k]) - 1) * H.titleLine : H.post; }

	function postLevel() { return level === 4 && head.length > 0; }

	function build() {
		root.className = 'tl tl--l' + level;
		ui.setAttribute('data-level', level);
		feed.classList.toggle('tl-zoomed', !postLevel());
		var h = H = heights();
		rows = [];
		postRow = new Array(posts.length);
		var start = postLevel() ? head.length : 0;
		var i = start;
		while (i < posts.length) {
			var y = posts[i].y;
			var j = i;
			while (j < posts.length && posts[j].y === y) j++;
			if (level === 0) {
				var months = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
				var top = i;
				for (var k = i; k < j; k++) { months[posts[k].m]++; if (!posts[k].locked && posts[k].n > posts[top].n) top = k; }
				rows.push({ t: 'year0', y: y, p: i, to: j, months: months, top: top, h: h.year0 });
				for (k = i; k < j; k++) postRow[k] = rows.length - 1;
			} else {
				// After the server-rendered rows the year carries on without a second marker.
				if (i > 0 && i === start && posts[i - 1].y === y) {
					// no marker
				} else {
					rows.push({ t: 'yhdr', y: y, p: i, h: h.yhdr });
				}
				var a = i;
				while (a < j) {
					var m = posts[a].m;
					var b = a;
					while (b < j && posts[b].m === m) b++;
					if (level === 1) {
						rows.push({ t: 'band', y: y, m: m, p: a, to: b, h: h.band });
						for (k = a; k < b; k++) postRow[k] = rows.length - 1;
					} else {
						if (level === 2) rows.push({ t: 'mhdr', y: y, m: m, p: a, h: h.mhdr });
						for (k = a; k < b; k++) {
							rows.push({ t: level === 2 ? 'title' : level === 3 ? 'card' : 'post', p: k, h: level === 2 ? h.title : level === 3 ? h.card : postHeight(k) });
							postRow[k] = rows.length - 1;
						}
					}
					a = b;
				}
			}
			i = j;
		}
		rows.push({ t: 'end', p: posts.length - 1, h: h.end });
		offs = new Array(rows.length + 1);
		offs[0] = 0;
		for (var r = 0; r < rows.length; r++) offs[r + 1] = offs[r] + rows[r].h;
		total = offs[rows.length];
		canvas.style.height = total + 'px';
		label.textContent = LEVELS[level];
		btnOut.disabled = level === 0;
		btnIn.disabled = level === 4;
		nodes.forEach(function (n) { n.remove(); });
		nodes.clear();
		measure();
	}

	function rowAt(y) {
		var lo = 0, hi = rows.length - 1;
		while (lo < hi) {
			var mid = (lo + hi + 1) >> 1;
			if (offs[mid] <= y) lo = mid; else hi = mid - 1;
		}
		return lo;
	}

	/* Where post i's row starts on the page, and how tall it is. */
	function postTop(i) { return postLevel() && i < head.length ? docTop(head[i]) : canvasTop + offs[postRow[i]]; }
	function postH(i) { return postLevel() && i < head.length ? head[i].offsetHeight : rows[postRow[i]].h; }

	/* ---------- Rows to HTML ---------- */

	var LOCK = '<svg class="tl-lock" viewBox="0 0 24 24" width="13" height="13" aria-label="Locked" role="img"><rect x="5" y="11" width="14" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3" fill="none" stroke="currentColor" stroke-width="2"/></svg>';

	function catLabel(p) {
		if (!p.cat) return '';
		var name = p.cat.charAt(0).toUpperCase() + p.cat.slice(1);
		return '<span class="dm-cat" data-cat="' + esc(p.cat) + '">' + esc(name) + '</span>';
	}
	function title(p) { return (p.locked ? LOCK : '') + esc(p.title); }
	function excerpt(p) {
		if (p.locked || !p.id) return '';
		var x = excerpts[p.y];
		if (!x) { want(p.y); return ''; }
		return esc(x[p.id] || '');
	}
	function signal(n) {
		var bars = n ? Math.min(5, Math.max(1, Math.round(Math.log(n + 1) / Math.LN2))) : 0;
		var s = '';
		for (var i = 0; i < 5; i++) s += '<b class="' + (i < bars ? 'on' : '') + '" style="height:' + (4 + i * 3) + 'px"></b>';
		return s;
	}
	function frame(p, cls) {
		if (p.locked) return '<div class="' + cls + ' tl-locked"><span>' + LOCK + ' LOCKED</span></div>';
		if (!p.thumb) return '<div class="' + cls + ' dm-tx__nosignal"><span>NO SIGNAL</span></div>';
		return '<div class="' + cls + ' dm-media"><img class="dm-media__img" src="' + esc(p.thumb) + '" alt="" loading="lazy" decoding="async"></div>';
	}

	function html(r) {
		var p = posts[r.p];
		switch (r.t) {
			case 'year0': {
				var max = Math.max.apply(null, r.months) || 1;
				var eras = [];
				for (var q = r.p; q < r.to; q++) eras[posts[q].m] = eras[posts[q].m] || posts[q].era;
				var era = posts[r.to - 1].era;
				var bars = r.months.map(function (n, m) {
					era = eras[m] || era;
					return '<i data-era="' + esc(era) + '" style="--h:' + (n / max).toFixed(3) + '" title="' + MON[m] + ' ' + r.y + ': ' + n + '"></i>';
				}).join('');
				var top = posts[r.top];
				return '<button type="button" class="tl-y0" data-zoom="1" data-p="' + r.p + '">' +
					'<span class="tl-y0__year">' + r.y + '</span>' +
					'<span class="tl-y0__spark" aria-hidden="true">' + bars + '</span>' +
					'<span class="tl-y0__meta"><b>' + (r.to - r.p) + '</b> ' + (r.to - r.p === 1 ? 'post' : 'posts') + '<br><span>Most replies: ' + esc(top.title) + '</span></span></button>';
			}
			case 'yhdr':
				return '<div class="dm-tx-year tl-yhdr" data-era="' + esc(p.era) + '"><a href="/' + r.y + '/">' + r.y + '</a><i aria-hidden="true"></i></div>';
			case 'mhdr':
				return '<div class="tl-mhdr"><span>' + MON[r.m].toUpperCase() + ' ' + r.y + '</span><i aria-hidden="true"></i></div>';
			case 'band': {
				var titles = [];
				for (var k = r.p; k < r.to && titles.length < 3; k++) titles.push(title(posts[k]));
				var n = r.to - r.p;
				return '<button type="button" class="tl-band" data-zoom="2" data-era="' + esc(p.era) + '" data-p="' + r.p + '">' +
					'<span class="tl-band__m">' + MON[r.m].toUpperCase() + '</span>' +
					'<span class="tl-band__bar" style="--w:' + Math.min(1, n / 60).toFixed(3) + '"></span>' +
					'<span class="tl-band__n">' + n + '</span>' +
					'<span class="tl-band__titles">' + titles.join('<span class="dm-sep"> · </span>') + '</span></button>';
			}
			case 'title':
				return '<a class="tl-title" data-era="' + esc(p.era) + '" href="' + esc(p.url) + '">' +
					'<time>' + pad(p.d) + '</time><span class="tl-dot" data-cat="' + esc(p.cat) + '"></span>' +
					'<span class="tl-title__t">' + title(p) + '</span>' +
					(p.n ? '<span class="tl-title__n">' + p.n + '</span>' : '') + '</a>';
			case 'card':
				return '<article class="tl-card" data-era="' + esc(p.era) + '">' +
					'<div class="tl-card__rail"><span class="tl-card__d">' + pad(p.d) + '</span><span class="tl-card__m">' + MON[p.m].toUpperCase() + '</span></div>' +
					'<a class="tl-card__frame" href="' + esc(p.url) + '" tabindex="-1" aria-hidden="true">' + frame(p, 'tl-card__media') + '</a>' +
					'<div class="tl-card__body"><p class="dm-tx__cat">' + catLabel(p) + '</p>' +
					'<h3 class="tl-card__title"><a href="' + esc(p.url) + '">' + title(p) + '</a></h3>' +
					'<p class="tl-card__ex" data-x="' + p.id + '">' + excerpt(p) + '</p></div></article>';
			case 'post':
				return '<article class="dm-tx tl-post" data-era="' + esc(p.era) + '">' +
					'<div class="dm-tx__rail"><time datetime="' + p.date + '"><span class="dm-tx__day">' + pad(p.d) + '</span><span class="dm-tx__mon">' + MON[p.m].toUpperCase() + '</span></time><span class="dm-tx__no">TX·' + p.id + '</span></div>' +
					'<a class="dm-tx__frame" href="' + esc(p.url) + '" tabindex="-1" aria-hidden="true">' + frame(p, 'dm-tx__media') + '<span class="dm-tx__scan"></span><span class="dm-tx__rec">REC ' + p.date.replace(/-/g, '.') + '</span></a>' +
					'<div class="dm-tx__body"><p class="dm-tx__cat">' + catLabel(p) + (JA[p.cat] ? '<span class="dm-tx__ja" lang="ja">' + JA[p.cat] + '</span>' : '') + '</p>' +
					'<h2 class="dm-tx__title"><a href="' + esc(p.url) + '">' + title(p) + '</a></h2>' +
					'<p class="dm-tx__excerpt" data-x="' + p.id + '">' + excerpt(p) + '</p>' +
					'<p class="dm-tx__meta"><span class="dm-tx__sig" aria-hidden="true">' + signal(p.n) + '</span><a href="' + esc(p.url) + '#comments">' + (p.n ? p.n + (p.n === 1 ? ' reply' : ' replies') : 'No replies') + '</a></p></div></article>';
			case 'end':
				return '<div class="tl-end"><b>2003.04.01</b> “This blog is live!” The first transmission. That’s everything.</div>';
		}
		return '';
	}

	/* ---------- Excerpts, a year at a time ---------- */

	function want(y) {
		if (asked[y]) return;
		asked[y] = true;
		fetch(cfg.api + (cfg.api.indexOf('?') < 0 ? '/excerpts?year=' : '/excerpts&year=') + y)
			.then(function (r) { return r.json(); })
			.then(function (x) {
				excerpts[y] = x;
				canvas.querySelectorAll('[data-x]').forEach(function (el) {
					var id = el.getAttribute('data-x');
					if (x[id] !== undefined && !el.textContent) el.textContent = x[id];
				});
			})
			.catch(function () { asked[y] = false; });
	}

	/* ---------- Rendering the visible window ---------- */

	// Offsets rather than getBoundingClientRect(), which would include the zoom's transform.
	function measure() { canvasTop = docTop(canvas); }

	function render() {
		queued = false;
		var top = window.scrollY - canvasTop;
		var vh = window.innerHeight;
		var a = rowAt(Math.max(0, top - vh));
		var b = top + vh * 2 < 0 ? -1 : rowAt(top + vh * 2);
		nodes.forEach(function (n, i) {
			if (i < a || i > b) { n.remove(); nodes.delete(i); }
		});
		var frag = null;
		for (var i = a; i <= b; i++) {
			if (nodes.has(i)) continue;
			var r = rows[i];
			var el = document.createElement('div');
			el.className = 'tl-row tl-row--' + r.t;
			el.style.height = r.h + 'px';
			el.style.transform = 'translateY(' + offs[i] + 'px)';
			el.innerHTML = html(r);
			if (r.t !== 'yhdr' && r.t !== 'mhdr' && r.t !== 'end' && posts[r.p].id === highlightId) el.classList.add('is-hit');
			nodes.set(i, el);
			(frag = frag || document.createDocumentFragment()).appendChild(el);
		}
		if (frag) canvas.appendChild(frag);
		updateUi();
	}
	function queue() {
		if (!queued) { queued = true; requestAnimationFrame(render); }
		clearTimeout(settleTimer);
		settleTimer = setTimeout(settle, 350);
		flag('is-scrolling', true);
	}

	/* The post whose row is at page position y. */
	function postAt(y) {
		if (postLevel() && y < canvasTop) {
			for (var s = head.length - 1; s > 0; s--) if (docTop(head[s]) <= y) return s;
			return 0;
		}
		var r = rows[rowAt(Math.max(0, Math.min(total - 1, y - canvasTop)))];
		return r ? r.p : 0;
	}
	function centerPost() { return postAt(window.scrollY + window.innerHeight / 2); }

	/* Put post i's row at viewport position `screenY` (px from the top of the window). */
	function scrollToPost(i, screenY) {
		window.scrollTo(0, Math.max(0, postTop(i) - screenY));
	}

	/* ---------- Zoom, keeping the post in the middle of the screen where it is ---------- */

	/* Out of the old level and into the new one, scaling about the middle of the screen so the
	   change reads as a zoom (dir: 1 in, -1 out, 0 a jump). */
	function fade(fn, dir) {
		if (reduce) { fn(); return; }
		var k = 0.04 * (dir || 0);
		var els = [log, canvas];
		function set(prop, v) { els.forEach(function (e) { e.style[prop] = v; }); }
		function origin() {
			els.forEach(function (e) { e.style.transformOrigin = '50% ' + Math.round(window.scrollY - docTop(e) + window.innerHeight / 2) + 'px'; });
		}
		origin();
		set('transition', 'opacity 0.09s ease-in, transform 0.09s ease-in');
		set('opacity', '0');
		set('transform', 'scale(' + (1 + k) + ')');
		setTimeout(function () {
			fn();
			set('transition', 'none');
			origin();
			set('transform', 'scale(' + (1 - k) + ')');
			void canvas.offsetWidth;
			set('transition', 'opacity 0.18s ease-out, transform 0.22s cubic-bezier(.2,.7,.3,1)');
			set('opacity', '');
			set('transform', '');
		}, 90);
	}

	function zoom(to, anchor) {
		to = Math.max(0, Math.min(4, to));
		if (to === level && anchor === undefined) return;
		var i = anchor !== undefined ? anchor : centerPost();
		// A Months or Years row holds many posts; if it still holds the one we zoomed out from, go back to that.
		if (anchor === undefined && focus >= 0 && postRow[focus] === postRow[i] && !(postLevel() && i < head.length)) i = focus;
		else if (anchor !== undefined && focus >= 0 && postRow[focus] === postRow[anchor]) i = anchor = focus;
		focus = i;
		var screenY = postTop(i) - window.scrollY;
		var frac = (window.innerHeight / 2 - screenY) / postH(i);
		if (anchor !== undefined) { screenY = window.innerHeight * 0.3; frac = 0; }
		var dir = to > level ? 1 : to < level ? -1 : 0;
		fade(function () {
			level = to;
			build();
			var y = anchor !== undefined ? screenY : window.innerHeight / 2 - Math.max(0.05, Math.min(0.95, frac)) * postH(i);
			scrollToPost(i, y);
			render();
			settle();
		}, dir);
	}

	/* A random post, in Posts view (or Cards, if that's where the reader is). */
	function random() {
		var pool = [];
		for (var i = 0; i < posts.length; i++) if (!posts[i].locked) pool.push(i);
		var pick = pool[Math.floor(Math.random() * pool.length)];
		highlightId = posts[pick].id;
		var to = level === 3 ? 3 : 4;
		fade(function () {
			if (to !== level) { level = to; build(); }
			nodes.forEach(function (n) { n.remove(); });
			nodes.clear();
			scrollToPost(pick, window.innerHeight * 0.28);
			render();
			if (postLevel() && pick < head.length) hit(head[pick].closest('li') || head[pick]);
			settle();
		}, 0);
		setTimeout(function () { highlightId = 0; document.querySelectorAll('.is-hit').forEach(function (n) { n.classList.remove('is-hit'); }); }, 2400);
	}
	function hit(el) { el.classList.add('is-hit'); }

	function step(dir) {
		var i = Math.max(0, Math.min(posts.length - 1, centerPost() + dir));
		scrollToPost(i, window.innerHeight / 2 - postH(i) / 2);
	}

	/* ---------- Scrubber: years spaced by how many posts they hold ---------- */

	function buildScrub() {
		scrub = document.createElement('div');
		scrub.className = 'tl-scrub';
		scrub.setAttribute('aria-hidden', 'true');
		var html = '';
		var seen = {};
		var last = -1;
		posts.forEach(function (p, i) {
			if (seen[p.y]) return;
			seen[p.y] = 1;
			// The quiet later years are only a sliver of the rail; label them only where there's room.
			if (last >= 0 && (i - last) / posts.length < 0.035) return;
			last = i;
			html += '<span class="tl-scrub__y" data-p="' + i + '" style="top:' + (i / posts.length * 100).toFixed(2) + '%">' + "'" + String(p.y).slice(2) + '</span>';
		});
		scrub.innerHTML = '<div class="tl-scrub__track">' + html + '<span class="tl-scrub__thumb"></span><span class="tl-scrub__bubble"></span></div>';
		ui.appendChild(scrub);
		scrubThumb = scrub.querySelector('.tl-scrub__thumb');
		bubble = scrub.querySelector('.tl-scrub__bubble');
		var track = scrub.querySelector('.tl-scrub__track');
		var dragging = false;
		function at(e) {
			var b = track.getBoundingClientRect();
			var f = Math.max(0, Math.min(1, (e.clientY - b.top) / b.height));
			var i = Math.min(posts.length - 1, Math.floor(f * posts.length));
			bubble.textContent = MON[posts[i].m] + ' ' + posts[i].y;
			bubble.style.top = (f * 100) + '%';
			scrollToPost(i, window.innerHeight / 2 - postH(i) / 2);
		}
		track.addEventListener('pointerdown', function (e) {
			dragging = true;
			track.setPointerCapture(e.pointerId);
			scrub.classList.add('is-dragging');
			var y = e.target.closest('.tl-scrub__y');
			if (y) {
				var i = +y.getAttribute('data-p');
				scrollToPost(i, Math.max(90, window.innerHeight * 0.18));
				bubble.textContent = posts[i].y;
				return;
			}
			at(e);
		});
		track.addEventListener('pointermove', function (e) { if (dragging) at(e); });
		function end() { dragging = false; scrub.classList.remove('is-dragging'); }
		track.addEventListener('pointerup', end);
		track.addEventListener('pointercancel', end);
	}

	function flag(name, on) {
		ui.classList.toggle(name, on);
		ctl.classList.toggle(name, on);
	}

	/* Scrubber position, the pinned year, and whether the reader is deep enough in to show them. */
	function headerBottom() {
		var hdr = document.querySelector('.dm-header');
		return hdr ? Math.max(0, hdr.getBoundingClientRect().bottom) : 0;
	}
	function updateUi() {
		var i = centerPost();
		var firstRow = postTop(Math.min(3, posts.length - 1));
		// Past the first few posts (or anywhere once zoomed), the scrubber and controls wake up.
		flag('is-deep', level !== 4 || window.scrollY + window.innerHeight / 2 > firstRow);
		flag('is-in', level !== 4 || docTop(log) - window.scrollY < window.innerHeight * 0.4);
		flag('is-off', window.scrollY + window.innerHeight < docTop(postLevel() ? log : canvas));
		scrubThumb.style.top = (i / posts.length * 100) + '%';
		if (scrubThumb.getAttribute('data-era') !== posts[i].era) scrubThumb.setAttribute('data-era', posts[i].era);
		if (!scrub.classList.contains('is-dragging')) bubble.textContent = MON[posts[i].m] + ' ' + posts[i].y;

		var hb = headerBottom();
		var y = window.scrollY + hb + 8;
		var listTop = postLevel() ? docTop(log) : canvasTop;
		var show = level !== 0 && y > listTop + 40;
		if (show) {
			var p = posts[postAt(y)];
			var a = stickyYear.firstChild;
			if (a.textContent !== String(p.y)) { a.textContent = p.y; a.href = '/' + p.y + '/'; }
			stickyYear.setAttribute('data-era', level === 1 ? '' : p.era);
			stickyYear.style.top = hb + 'px';
			stickyYear.style.left = Math.round(root.getBoundingClientRect().left) + 'px';
			// Step aside while the year's own marker passes underneath.
			var near = canvas.querySelectorAll('.tl-yhdr, .dm-tx-year');
			var mark = log.querySelectorAll('.dm-tx-year');
			[].concat([].slice.call(near), postLevel() ? [].slice.call(mark) : []).forEach(function (m) {
				var b = m.getBoundingClientRect();
				if (b.bottom > hb - 10 && b.top < hb + stickyYear.offsetHeight + 24) show = false;
			});
		}
		stickyYear.classList.toggle('is-on', show);
	}

	/* ---------- Deep links: ?at=YYYY-MM-DD&z=N ---------- */

	function settle() {
		flag('is-scrolling', false);
		var url = new URL(location.href);
		var i = centerPost();
		// Reading the first page as a plain home page keeps the address plain too.
		if (level === 4 && (i < Math.min(head.length, 12) || window.scrollY + window.innerHeight / 2 < docTop(log))) {
			url.searchParams.delete('at');
			url.searchParams.delete('z');
		} else {
			url.searchParams.set('at', posts[i].date);
			url.searchParams.set('z', String(level));
		}
		if (url.href !== location.href) history.replaceState(history.state, '', url.href);
	}

	/* ---------- Controls ---------- */

	function icon(d) { return '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + d + '</svg>'; }
	function buildControls() {
		var c = document.createElement('div');
		c.className = 'tl-ctl';
		c.setAttribute('role', 'toolbar');
		c.setAttribute('aria-label', 'Timeline zoom');
		c.innerHTML =
			'<button type="button" class="tl-ctl__out" aria-label="Zoom out" title="Zoom out (−)">' + icon('<path d="M5 12h14"/>') + '</button>' +
			'<span class="tl-ctl__level" aria-live="polite"></span>' +
			'<button type="button" class="tl-ctl__in" aria-label="Zoom in" title="Zoom in (+)">' + icon('<path d="M12 5v14M5 12h14"/>') + '</button>' +
			'<button type="button" class="tl-ctl__rand" aria-label="Random post" title="Random post (R)">' + icon('<rect x="4" y="4" width="16" height="16" rx="3"/><circle cx="9" cy="9" r="1.2" fill="currentColor"/><circle cx="15" cy="15" r="1.2" fill="currentColor"/><circle cx="15" cy="9" r="1.2" fill="currentColor"/><circle cx="9" cy="15" r="1.2" fill="currentColor"/>') + '</button>';
		ctl = c;
		// Wide screens: in the header beside search, where it covers nothing. Otherwise it floats.
		var tools = document.querySelector('.dm-nav__tools');
		var wide = window.matchMedia('(min-width: 1240px)');
		function place() {
			if (wide.matches && tools) { tools.insertBefore(c, tools.firstChild); c.classList.add('tl-ctl--nav'); }
			else { ui.appendChild(c); c.classList.remove('tl-ctl--nav'); }
		}
		place();
		if (wide.addEventListener) wide.addEventListener('change', place);
		label = c.querySelector('.tl-ctl__level');
		btnOut = c.querySelector('.tl-ctl__out');
		btnIn = c.querySelector('.tl-ctl__in');
		btnOut.addEventListener('click', function () { zoom(level - 1); });
		btnIn.addEventListener('click', function () { zoom(level + 1); });
		c.querySelector('.tl-ctl__rand').addEventListener('click', random);
	}

	/* ---------- Start ---------- */

	function start(data) {
		parse(data);
		if (!posts.length) return;
		// The server-rendered rows stay only if they are exactly the newest posts, in order.
		var arts = [].slice.call(log.querySelectorAll('.dm-tx'));
		var ok = arts.length > 0 && arts.every(function (a, k) {
			var no = a.querySelector('.dm-tx__no');
			return no && posts[k] && posts[k].id === parseInt(no.textContent.replace(/\D/g, ''), 10);
		});
		head = ok ? arts : [];

		var q = new URLSearchParams(location.search);
		if (q.has('z')) level = Math.max(0, Math.min(4, parseInt(q.get('z'), 10) || 0));
		var at = q.get('at');

		root = document.createElement('div');
		canvas = document.createElement('div');
		canvas.className = 'tl-canvas';
		root.appendChild(canvas);
		ui = document.createElement('div');
		ui.className = 'tl-ui';
		stickyYear = document.createElement('div');
		stickyYear.className = 'tl-year dm-tx-year';
		stickyYear.setAttribute('aria-hidden', 'true');
		stickyYear.innerHTML = '<a tabindex="-1" href="#"></a>';
		ui.appendChild(stickyYear);
		buildControls();
		buildScrub();
		document.body.appendChild(ui);
		// Below the server-rendered rows, so nothing already on screen moves.
		log.parentNode.insertBefore(root, log.nextSibling);
		feed.classList.add('tl-host');
		build();
		if (at) {
			var i = posts.findIndex(function (p) { return p.date <= at; });
			if (i < 0) i = posts.length - 1;
			scrollToPost(i, window.innerHeight / 2 - postH(i) / 2);
		}
		render();

		window.addEventListener('scroll', queue, { passive: true });
		var rt = 0;
		var lastW = window.innerWidth;
		window.addEventListener('resize', function () {
			if (window.innerWidth === lastW) { queue(); return; } // phones resize height as their toolbars slide
			lastW = window.innerWidth;
			clearTimeout(rt);
			rt = setTimeout(function () {
				var i = centerPost();
				build();
				scrollToPost(i, window.innerHeight / 2 - postH(i) / 2);
				render();
			}, 150);
		});
		root.addEventListener('click', function (e) {
			var z = e.target.closest('[data-zoom]');
			if (z) zoom(+z.getAttribute('data-zoom'), +z.getAttribute('data-p'));
		});
		document.addEventListener('keydown', function (e) {
			if (e.metaKey || e.ctrlKey || e.altKey || /input|textarea|select/i.test(e.target.tagName || '') || e.target.isContentEditable) return;
			if (window.scrollY + window.innerHeight < docTop(postLevel() ? log : canvas)) return;
			var k = e.key;
			if (k === '+' || k === '=') zoom(level + 1);
			else if (k === '-' || k === '_') zoom(level - 1);
			else if (k === 'r' || k === 'R') random();
			else if (k === 'j' || k === 'J') step(1);
			else if (k === 'k' || k === 'K') step(-1);
			else return;
			e.preventDefault();
		});
		document.dispatchEvent(new CustomEvent('dm:timeline-ready', { detail: { posts: posts.length } }));
	}

	fetch(cfg.api, { credentials: 'same-origin' })
		.then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
		.then(function (data) { return (document.fonts ? document.fonts.ready : Promise.resolve()).then(function () { start(data); }); })
		.catch(function () { /* Keep the server-rendered page. */ });
})();
