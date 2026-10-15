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
import { fetchJson, isArray, isRecord, num, orIfZero, str } from './lib/data';
import { closest, query, queryAll } from './lib/dom';

/* ---------- Data ---------- */

interface Post {
  id: number;
  title: string;
  cat: string;
  era: string;
  n: number;          // replies
  locked: boolean;
  thumb: string;
  url: string;
  y: number;
  m: number;          // 0-11
  d: number;
  date: string;       // YYYY-MM-DD
  t: number;          // ms, in the site's time zone
}

type Row =
  | { t: 'year0'; y: number; p: number; to: number; months: number[]; top: number; h: number }
  | { t: 'yhdr'; y: number; p: number; h: number }
  | { t: 'mhdr'; y: number; m: number; p: number; h: number }
  | { t: 'band'; y: number; m: number; p: number; to: number; h: number }
  | { t: 'title' | 'card' | 'post'; p: number; h: number }
  | { t: 'end'; p: number; h: number };

type Level = 0 | 1 | 2 | 3 | 4;
const LEVELS = ['Years', 'Months', 'Titles', 'Cards', 'Posts'] as const;
const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] as const;
const JA: Readonly<Record<string, string>> = { anime: 'アニメ', blog: 'ブログ', otaku: 'オタク', games: 'ゲーム', music: '音楽', money: 'お金', reviews: 'レビュー', tech: '技術', rant: '愚痴', figures: 'フィギュア', manga: '漫画', world: '世界', japanese: '日本語', news: 'ニュース' };

function toLevel(n: number): Level {
  return n <= 0 ? 0 : n >= 4 ? 4 : n === 1 ? 1 : n === 2 ? 2 : 3;
}
function mon(m: number): string { return MON[m] ?? ''; }
function esc(s: string | number): string {
  return String(s).replace(/[&<>"]/g, (c) => (c === '&' ? '&amp;' : c === '<' ? '&lt;' : c === '>' ? '&gt;' : '&quot;'));
}
function pad(n: number): string { return n < 10 ? `0${String(n)}` : String(n); }
function docTop(start: HTMLElement): number {
  let y = 0;
  for (let el: Element | null = start; el instanceof HTMLElement; el = el.offsetParent) y += el.offsetTop;
  return y;
}

/** One row of the feed: [id, unix time, title, category, era, replies, locked, picture, link]. */
function parsePost(r: unknown, tz: number): Post | null {
  if (!isArray(r)) return null;
  const [id, ts, title, cat, era, n, locked, thumb, url] = r;
  if (typeof id !== 'number' || typeof ts !== 'number' || typeof title !== 'string' || typeof cat !== 'string' ||
    typeof era !== 'string' || typeof n !== 'number' || typeof thumb !== 'string' || typeof url !== 'string') return null;
  const d = new Date(ts * 1000 + tz);
  return {
    id, title: title !== '' ? title : '(untitled)', cat, era, n, locked: locked === 1 || locked === true, thumb, url,
    y: d.getUTCFullYear(), m: d.getUTCMonth(), d: d.getUTCDate(),
    date: `${String(d.getUTCFullYear())}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())}`,
    t: d.getTime(),
  };
}

function parseExcerpts(x: unknown): Map<number, string> {
  const out = new Map<number, string>();
  if (!isRecord(x)) return out;
  for (const [k, v] of Object.entries(x)) if (typeof v === 'string') out.set(Number(k), v);
  return out;
}

/* ---------- The timeline ---------- */

interface Heights {
  yhdr: number; post: number; postOne: number; titleLine: number; titleFont: string; titleWidth: number;
  card: number; title: number; mhdr: number; band: number; year0: number; end: number;
}
interface Layout { rows: Row[]; offs: number[]; postRow: (number | undefined)[]; top: number; scroll: number; head: boolean }

function run(api: string, feed: HTMLElement, log: HTMLElement, posts: Post[]): void {
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const post = (i: number): Post => {
    const p = posts[i];
    if (!p) throw new RangeError(`no post ${String(i)}`);
    return p;
  };

  // The server-rendered rows stay only if they are exactly the newest posts, in order.
  const arts = queryAll(log, '.dm-tx', HTMLElement);
  const ok = arts.length > 0 && arts.every((a, k) => {
    const no = query(a, '.dm-tx__no', HTMLElement);
    return no !== null && posts[k]?.id === parseInt(no.textContent.replace(/\D/g, ''), 10);
  });
  const head: HTMLElement[] = ok ? arts : [];   // newest first
  const headEl = (k: number): HTMLElement => {
    const a = head[k];
    if (!a) throw new RangeError(`no server row ${String(k)}`);
    return a.closest('li') ?? a;
  };

  let level: Level = 4;
  let rows: Row[] = [];
  let offs: number[] = [0];
  let postRow: (number | undefined)[] = [];
  let total = 0;
  const nodes = new Map<number, HTMLElement>();
  const excerpts = new Map<number, Map<number, string>>();
  const asked = new Set<number>();
  let canvasTop = 0;
  let queued = false;
  let settleTimer = 0;
  let highlightId = 0;
  let focus = -1;  // the post a zoom started from, so zooming back in returns to it

  const row = (i: number): Row => {
    const r = rows[i];
    if (!r) throw new RangeError(`no row ${String(i)}`);
    return r;
  };
  const off = (i: number): number => offs[i] ?? total;

  /* ---------- The page's parts ---------- */

  const root = document.createElement('div');
  const canvas = document.createElement('div');
  canvas.className = 'tl-canvas';
  root.appendChild(canvas);
  const ui = document.createElement('div');
  ui.className = 'tl-ui';
  const stickyYear = document.createElement('div');
  stickyYear.className = 'tl-year dm-tx-year';
  stickyYear.setAttribute('aria-hidden', 'true');
  const stickyLink = document.createElement('a');
  stickyLink.tabIndex = -1;
  stickyLink.href = '#';
  stickyYear.appendChild(stickyLink);
  ui.appendChild(stickyYear);

  function icon(d: string): string {
    return `<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${d}</svg>`;
  }
  function ctlButton(cls: string, labelText: string, title: string, svg: string): HTMLButtonElement {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = cls;
    b.setAttribute('aria-label', labelText);
    b.title = title;
    b.innerHTML = icon(svg);
    return b;
  }
  const ctl = document.createElement('div');
  ctl.className = 'tl-ctl';
  ctl.setAttribute('role', 'toolbar');
  ctl.setAttribute('aria-label', 'Timeline zoom');
  const btnOut = ctlButton('tl-ctl__out', 'Zoom out', 'Zoom out (−)', '<path d="M5 12h14"/>');
  const label = document.createElement('span');
  label.className = 'tl-ctl__level';
  label.setAttribute('aria-live', 'polite');
  const btnIn = ctlButton('tl-ctl__in', 'Zoom in', 'Zoom in (+)', '<path d="M12 5v14M5 12h14"/>');
  const btnRand = ctlButton('tl-ctl__rand', 'Random post', 'Random post (R)', '<rect x="4" y="4" width="16" height="16" rx="3"/><circle cx="9" cy="9" r="1.2" fill="currentColor"/><circle cx="15" cy="15" r="1.2" fill="currentColor"/><circle cx="15" cy="9" r="1.2" fill="currentColor"/><circle cx="9" cy="15" r="1.2" fill="currentColor"/>');
  ctl.append(btnOut, label, btnIn, btnRand);

  const scrub = document.createElement('div');
  scrub.className = 'tl-scrub';
  scrub.setAttribute('aria-hidden', 'true');
  const track = document.createElement('div');
  track.className = 'tl-scrub__track';
  const scrubThumb = document.createElement('span');
  scrubThumb.className = 'tl-scrub__thumb';
  const bubble = document.createElement('span');
  bubble.className = 'tl-scrub__bubble';
  const ghost = document.createElement('span');
  ghost.className = 'tl-scrub__ghost';
  scrub.appendChild(track);

  /* ---------- Layout: rows and their fixed heights for the current level ---------- */

  let H: Heights = { yhdr: 0, post: 0, postOne: 0, titleLine: 0, titleFont: '', titleWidth: 0, card: 0, title: 0, mhdr: 0, band: 0, year0: 0, end: 150 };

  /* Heights come from rendering a sample of each row type off screen, so they follow the CSS
     at every width. A post row takes the height of the server-rendered ones when they agree. */
  function heights(): Heights {
    const probe = document.createElement('div');
    probe.className = `tl-probe tl tl--l${String(level)}`;
    probe.style.width = `${String(canvas.clientWidth)}px`;
    canvas.appendChild(probe);
    function measureHtml(r: Row): number {
      const box = document.createElement('div');
      box.className = `tl-row tl-row--${r.t}`;
      box.style.position = 'static';
      box.innerHTML = html(r);
      probe.replaceChildren(box);
      const x = query(probe, '[data-x]', HTMLElement);
      if (x) x.textContent = 'wwww '.repeat(59);
      return box.offsetHeight;
    }
    const sample: Post = { id: 0, title: 'Wwww '.repeat(29), cat: 'blog', era: 'now', n: 9, locked: false, thumb: '', url: '#', y: 2013, m: 0, d: 1, date: '2013-01-01', t: 0 };
    posts.push(sample);
    const p = posts.length - 1;
    const yhdr = measureHtml({ t: 'yhdr', y: 2013, p, h: 0 });
    const full = measureHtml({ t: 'post', p, h: 0 });
    const titleEl = query(probe, '.dm-tx__title', HTMLElement);
    const tc = titleEl ? getComputedStyle(titleEl) : null;
    const h: Heights = {
      yhdr,
      post: full,
      postOne: full,
      titleLine: 0,
      titleFont: tc ? `${tc.fontWeight} ${tc.fontSize} ${tc.fontFamily}` : '',
      titleWidth: titleEl ? titleEl.clientWidth : 0,
      card: measureHtml({ t: 'card', p, h: 0 }),
      title: measureHtml({ t: 'title', p, h: 0 }),
      mhdr: measureHtml({ t: 'mhdr', y: 2013, m: 0, p, h: 0 }),
      band: measureHtml({ t: 'band', y: 2013, m: 0, p, to: p + 1, h: 0 }),
      year0: measureHtml({ t: 'year0', y: 2013, p, to: p + 1, months: [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], top: p, h: 0 }),
      end: 150,
    };
    // Where a title's wrapping changes a post row's height (phones), rows are sized per title.
    sample.title = 'W';
    h.postOne = measureHtml({ t: 'post', p, h: 0 });
    h.titleLine = tc && h.post > h.postOne ? orIfZero(parseFloat(tc.lineHeight), 0) : 0;
    posts.pop();
    probe.remove();
    if (level === 4 && head.length > 0) {
      const hs = head.map((a) => a.offsetHeight);
      const first = hs[0];
      if (h.titleLine === 0 && first !== undefined && hs.every((v) => v === first)) h.post = first;
    }
    return h;
  }

  let ctx2d: CanvasRenderingContext2D | null = null;
  /* Lines a title wraps to in a post row, by measuring its words in the title font. */
  function titleLines(p: Post): number {
    ctx2d ??= document.createElement('canvas').getContext('2d');
    if (!ctx2d) return 1;
    const c2 = ctx2d;
    c2.font = H.titleFont;
    const max = H.titleWidth;
    let lines = 1, x = p.locked ? 19 : 0;
    const space = c2.measureText(' ').width;
    for (const word of p.title.split(/\s+/)) {
      const w = c2.measureText(word).width;
      if (w > max) {
        // No spaces to break at (Japanese, long URLs): it breaks between characters.
        for (const ch of word) {
          const cw = c2.measureText(ch).width;
          if (x + cw > max) { lines++; x = 0; }
          x += cw;
        }
        x += space;
        continue;
      }
      if (x > 0 && x + w > max) { lines++; x = 0; }
      x += w + space;
    }
    return Math.min(3, lines);
  }
  function postHeight(k: number): number {
    return H.titleLine > 0 ? H.postOne + (titleLines(post(k)) - 1) * H.titleLine : H.post;
  }

  function postLevel(): boolean { return level === 4 && head.length > 0; }

  function build(): void {
    root.className = `tl tl--l${String(level)}`;
    ui.setAttribute('data-level', String(level));
    feed.classList.toggle('tl-zoomed', !postLevel());
    const h = H = heights();
    rows = [];
    postRow = new Array<number | undefined>(posts.length);
    const start = postLevel() ? head.length : 0;
    let i = start;
    while (i < posts.length) {
      const y = post(i).y;
      let j = i;
      while (j < posts.length && post(j).y === y) j++;
      if (level === 0) {
        const months = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
        let top = i;
        for (let k = i; k < j; k++) {
          const p = post(k);
          months[p.m] = (months[p.m] ?? 0) + 1;
          if (!p.locked && p.n > post(top).n) top = k;
        }
        rows.push({ t: 'year0', y, p: i, to: j, months, top, h: h.year0 });
        for (let k = i; k < j; k++) postRow[k] = rows.length - 1;
      } else {
        // After the server-rendered rows the year carries on without a second marker.
        if (!(i > 0 && i === start && post(i - 1).y === y)) rows.push({ t: 'yhdr', y, p: i, h: h.yhdr });
        let a = i;
        while (a < j) {
          const m = post(a).m;
          let b = a;
          while (b < j && post(b).m === m) b++;
          if (level === 1) {
            rows.push({ t: 'band', y, m, p: a, to: b, h: h.band });
            for (let k = a; k < b; k++) postRow[k] = rows.length - 1;
          } else {
            if (level === 2) rows.push({ t: 'mhdr', y, m, p: a, h: h.mhdr });
            for (let k = a; k < b; k++) {
              rows.push(level === 2 ? { t: 'title', p: k, h: h.title } : level === 3 ? { t: 'card', p: k, h: h.card } : { t: 'post', p: k, h: postHeight(k) });
              postRow[k] = rows.length - 1;
            }
          }
          a = b;
        }
      }
      i = j;
    }
    rows.push({ t: 'end', p: posts.length - 1, h: h.end });
    offs = [0];
    let sum = 0;
    for (const r of rows) { sum += r.h; offs.push(sum); }
    total = sum;
    canvas.style.height = `${String(total)}px`;
    label.textContent = LEVELS[level];
    btnOut.disabled = level === 0;
    btnIn.disabled = level === 4;
    for (const n of nodes.values()) n.remove();
    nodes.clear();
    measure();
  }

  function rowAt(y: number): number {
    let lo = 0, hi = rows.length - 1;
    while (lo < hi) {
      const mid = (lo + hi + 1) >> 1;
      if (off(mid) <= y) lo = mid; else hi = mid - 1;
    }
    return lo;
  }

  /* Where post i's row starts on the page, and how tall it is. */
  function postTop(i: number): number {
    if (postLevel() && i < head.length) return docTop(headEl(i));
    return canvasTop + off(postRow[i] ?? 0);
  }
  function postH(i: number): number {
    if (postLevel() && i < head.length) return headEl(i).offsetHeight;
    return rows[postRow[i] ?? -1]?.h ?? 0;
  }

  /* ---------- Rows to HTML ---------- */

  const LOCK = '<svg class="tl-lock" viewBox="0 0 24 24" width="13" height="13" aria-label="Locked" role="img"><rect x="5" y="11" width="14" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3" fill="none" stroke="currentColor" stroke-width="2"/></svg>';

  function catLabel(p: Post): string {
    if (p.cat === '') return '';
    const name = p.cat.charAt(0).toUpperCase() + p.cat.slice(1);
    return `<span class="dm-cat" data-cat="${esc(p.cat)}">${esc(name)}</span>`;
  }
  function title(p: Post): string { return (p.locked ? LOCK : '') + esc(p.title); }
  function excerpt(p: Post): string {
    if (p.locked || p.id === 0) return '';
    const x = excerpts.get(p.y);
    if (!x) { want(p.y); return ''; }
    return esc(x.get(p.id) ?? '');
  }
  function signal(n: number): string {
    const bars = n > 0 ? Math.min(5, Math.max(1, Math.round(Math.log(n + 1) / Math.LN2))) : 0;
    let s = '';
    for (let i = 0; i < 5; i++) s += `<b class="${i < bars ? 'on' : ''}" style="height:${String(4 + i * 3)}px"></b>`;
    return s;
  }
  function frame(p: Post, cls: string): string {
    if (p.locked) return `<div class="${cls} tl-locked"><span>${LOCK} LOCKED</span></div>`;
    if (p.thumb === '') return `<div class="${cls} dm-tx__nosignal"><span>NO SIGNAL</span></div>`;
    return `<div class="${cls} dm-media"><img class="dm-media__img" src="${esc(p.thumb)}" alt="" loading="lazy" decoding="async"></div>`;
  }

  function html(r: Row): string {
    const p = post(r.p);
    switch (r.t) {
      case 'year0': {
        const max = orIfZero(Math.max(...r.months), 1);
        const eras: string[] = [];
        for (let q = r.p; q < r.to; q++) { const e = post(q); eras[e.m] ??= e.era; }
        let era = post(r.to - 1).era;
        const bars = r.months.map((n, m) => {
          era = eras[m] ?? era;
          return `<i data-era="${esc(era)}" style="--h:${(n / max).toFixed(3)}" title="${mon(m)} ${String(r.y)}: ${String(n)}"></i>`;
        }).join('');
        const count = r.to - r.p;
        return `<button type="button" class="tl-y0" data-zoom="1" data-p="${String(r.p)}">` +
          `<span class="tl-y0__year">${String(r.y)}</span>` +
          `<span class="tl-y0__spark" aria-hidden="true">${bars}</span>` +
          `<span class="tl-y0__meta"><b>${String(count)}</b> ${count === 1 ? 'post' : 'posts'}<br><span>Most replies: ${esc(post(r.top).title)}</span></span></button>`;
      }
      case 'yhdr':
        return `<div class="dm-tx-year tl-yhdr" data-era="${esc(p.era)}"><a href="/${String(r.y)}/">${String(r.y)}</a><i aria-hidden="true"></i></div>`;
      case 'mhdr':
        return `<div class="tl-mhdr"><span>${mon(r.m).toUpperCase()} ${String(r.y)}</span><i aria-hidden="true"></i></div>`;
      case 'band': {
        const titles: string[] = [];
        for (let k = r.p; k < r.to && titles.length < 3; k++) titles.push(title(post(k)));
        const n = r.to - r.p;
        return `<button type="button" class="tl-band" data-zoom="2" data-era="${esc(p.era)}" data-p="${String(r.p)}">` +
          `<span class="tl-band__m">${mon(r.m).toUpperCase()}</span>` +
          `<span class="tl-band__bar" style="--w:${Math.min(1, n / 60).toFixed(3)}"></span>` +
          `<span class="tl-band__n">${String(n)}</span>` +
          `<span class="tl-band__titles">${titles.join('<span class="dm-sep"> · </span>')}</span></button>`;
      }
      case 'title':
        return `<a class="tl-title" data-era="${esc(p.era)}" href="${esc(p.url)}">` +
          `<time>${pad(p.d)}</time><span class="tl-dot" data-cat="${esc(p.cat)}"></span>` +
          `<span class="tl-title__t">${title(p)}</span>` +
          (p.n > 0 ? `<span class="tl-title__n">${String(p.n)}</span>` : '') + '</a>';
      case 'card':
        return `<article class="tl-card" data-era="${esc(p.era)}">` +
          `<div class="tl-card__rail"><span class="tl-card__d">${pad(p.d)}</span><span class="tl-card__m">${mon(p.m).toUpperCase()}</span></div>` +
          `<a class="tl-card__frame" href="${esc(p.url)}" tabindex="-1" aria-hidden="true">${frame(p, 'tl-card__media')}</a>` +
          `<div class="tl-card__body"><p class="dm-tx__cat">${catLabel(p)}</p>` +
          `<h3 class="tl-card__title"><a href="${esc(p.url)}">${title(p)}</a></h3>` +
          `<p class="tl-card__ex" data-x="${String(p.id)}">${excerpt(p)}</p></div></article>`;
      case 'post': {
        const ja = JA[p.cat];
        return `<article class="dm-tx tl-post" data-era="${esc(p.era)}">` +
          `<div class="dm-tx__rail"><time datetime="${p.date}"><span class="dm-tx__day">${pad(p.d)}</span><span class="dm-tx__mon">${mon(p.m).toUpperCase()}</span></time><span class="dm-tx__no">TX·${String(p.id)}</span></div>` +
          `<a class="dm-tx__frame" href="${esc(p.url)}" tabindex="-1" aria-hidden="true">${frame(p, 'dm-tx__media')}<span class="dm-tx__scan"></span><span class="dm-tx__rec">REC ${p.date.replace(/-/g, '.')}</span></a>` +
          `<div class="dm-tx__body"><p class="dm-tx__cat">${catLabel(p)}${ja !== undefined ? `<span class="dm-tx__ja" lang="ja">${ja}</span>` : ''}</p>` +
          `<h2 class="dm-tx__title"><a href="${esc(p.url)}">${title(p)}</a></h2>` +
          `<p class="dm-tx__excerpt" data-x="${String(p.id)}">${excerpt(p)}</p>` +
          `<p class="dm-tx__meta"><span class="dm-tx__sig" aria-hidden="true">${signal(p.n)}</span><a href="${esc(p.url)}#comments">${p.n > 0 ? `${String(p.n)}${p.n === 1 ? ' reply' : ' replies'}` : 'No replies'}</a></p></div></article>`;
      }
      case 'end':
        return '<div class="tl-end"><b>2003.04.01</b> “This blog is live!” The first transmission. That’s everything.</div>';
    }
  }

  /* ---------- Excerpts, a year at a time ---------- */

  function want(y: number): void {
    if (asked.has(y)) return;
    asked.add(y);
    const sep = api.includes('?') ? '/excerpts&year=' : '/excerpts?year=';
    fetchJson(api + sep + String(y))
      .then((data) => {
        const x = parseExcerpts(data);
        excerpts.set(y, x);
        for (const el of queryAll(canvas, '[data-x]', HTMLElement)) {
          const text = x.get(Number(el.getAttribute('data-x')));
          if (text !== undefined && el.textContent === '') el.textContent = text;
        }
      })
      .catch(() => { asked.delete(y); });
  }

  /* ---------- Rendering the visible window ---------- */

  // Offsets rather than getBoundingClientRect(), which would include the zoom's transform.
  function measure(): void { canvasTop = docTop(canvas); }

  function render(): void {
    queued = false;
    const top = window.scrollY - canvasTop;
    const vh = window.innerHeight;
    const a = rowAt(Math.max(0, top - vh));
    const b = top + vh * 2 < 0 ? -1 : rowAt(top + vh * 2);
    for (const [i, n] of nodes) {
      if (i < a || i > b) { n.remove(); nodes.delete(i); }
    }
    const frag = document.createDocumentFragment();
    for (let i = a; i <= b; i++) {
      if (nodes.has(i)) continue;
      const r = row(i);
      const el = document.createElement('div');
      el.className = `tl-row tl-row--${r.t}`;
      el.style.height = `${String(r.h)}px`;
      el.style.transform = `translateY(${String(off(i))}px)`;
      el.innerHTML = html(r);
      if (r.t !== 'yhdr' && r.t !== 'mhdr' && r.t !== 'end' && post(r.p).id === highlightId) el.classList.add('is-hit');
      nodes.set(i, el);
      frag.appendChild(el);
    }
    canvas.appendChild(frag);
    updateUi();
  }
  function queue(): void {
    if (!queued) { queued = true; requestAnimationFrame(render); }
    clearTimeout(settleTimer);
    settleTimer = window.setTimeout(settle, 350);
    flag('is-scrolling', true);
  }

  /* The post whose row is at page position y. */
  function postAt(y: number): number {
    if (postLevel() && y < canvasTop) {
      for (let s = head.length - 1; s > 0; s--) if (docTop(headEl(s)) <= y) return s;
      return 0;
    }
    return rows[rowAt(Math.max(0, Math.min(total - 1, y - canvasTop)))]?.p ?? 0;
  }
  function centerPost(): number { return postAt(window.scrollY + window.innerHeight / 2); }

  /* Put post i's row at viewport position `screenY` (px from the top of the window). */
  function scrollToPost(i: number, screenY: number): void {
    window.scrollTo(0, Math.max(0, postTop(i) - screenY));
  }

  /* ---------- Zoom, keeping the post in the middle of the screen where it is ---------- */

  /* A jump (random): fade out, swap, fade back in. */
  function fade(fn: () => void): void {
    if (reduce) { fn(); return; }
    const els = [log, canvas];
    const set = (transition: string, opacity: string): void => {
      for (const e of els) { e.style.transition = transition; e.style.opacity = opacity; }
    };
    set('opacity 0.09s ease-in', '0');
    setTimeout(() => {
      fn();
      set('none', '0');
      canvas.getBoundingClientRect(); // apply the hidden state before fading back in
      set('opacity 0.18s ease-out', '');
    }, 90);
  }

  /* Zooming morphs each row from where its posts were to where they are now: zooming in, a
     month's band unfolds into its posts; zooming out, they fold back into it. The old rows on
     screen become ghosts in an overlay that stretch or shrink toward their posts' new place and
     fade, while the new rows grow out of their posts' old place. Only transform and opacity
     animate, on the few dozen rows on screen, so it all runs on the compositor. */
  let morphing: { ov: HTMLElement; anims: Animation[] } | null = null;
  function snap(): Layout {
    return { rows, offs, postRow, top: canvasTop, scroll: window.scrollY, head: postLevel() };
  }
  // Where post k sat in layout L, in viewport px: a share of its row when the row holds many posts.
  function slice(L: Layout, k: number): { top: number; h: number } | null {
    if (L.head && k < head.length) {
      const a = headEl(k);
      return { top: docTop(a) - L.scroll, h: a.offsetHeight };
    }
    const i = L.postRow[k];
    const r = i === undefined ? undefined : L.rows[i];
    if (i === undefined || !r) return null;
    const y = L.top + (L.offs[i] ?? 0) - L.scroll;
    if ((r.t === 'band' || r.t === 'year0') && r.to - r.p > 1) {
      const h = r.h / (r.to - r.p);
      return { top: y + h * (k - r.p), h };
    }
    return { top: y, h: r.h };
  }
  // Where a row's posts were (or will be) in layout L, as a move and a stretch from `top`/`h`.
  // The stretch is capped so text never distorts far; the move covers the rest.
  function toward(L: Layout, r: Row, top: number, h: number, vh: number, lo: number, hi: number): { d: number; s: number } | null {
    const a = slice(L, r.p);
    if (!a) return null;
    const single = r.t === 'yhdr' || r.t === 'mhdr' || r.t === 'end';
    const last = r.t === 'band' || r.t === 'year0' ? slice(L, r.to - 1) : null;
    const b = last ?? a;
    const y = single ? a.top - (r.t === 'end' ? 0 : h) : a.top;
    const s = single ? 1 : Math.max(lo, Math.min(hi, (b.top + b.h - a.top) / h));
    return { d: Math.max(-1.2 * vh, Math.min(1.2 * vh, y - top)), s };
  }
  function endMorph(): void {
    if (!morphing) return;
    for (const a of morphing.anims) a.cancel();
    morphing.ov.remove();
    morphing = null;
  }
  function morph(fn: () => void): void {
    endMorph();
    const vh = window.innerHeight;
    const A = snap();
    const ov = document.createElement('div');
    ov.className = `${root.className} tl-morph`;
    ov.setAttribute('aria-hidden', 'true');
    const ghosts: { el: HTMLElement; r: Row; top: number; h: number }[] = [];
    for (const [i, el] of nodes) {
      const b = el.getBoundingClientRect();
      const r = A.rows[i];
      if (b.bottom < 0 || b.top > vh || !r) continue;
      nodes.delete(i);
      el.style.left = `${String(b.left)}px`;
      el.style.width = `${String(b.width)}px`;
      el.style.right = 'auto';
      el.style.transform = `translateY(${String(b.top)}px)`;
      el.classList.remove('is-hit');
      ov.appendChild(el);
      ghosts.push({ el, r, top: b.top, h: b.height });
    }
    if (A.head) {
      const wrap = document.createElement('ol');
      wrap.className = log.className;
      wrap.style.cssText = 'position:absolute;inset:0;margin:0;display:block!important';
      ov.appendChild(wrap);
      head.forEach((_, k) => {
        const li = headEl(k);
        const b = li.getBoundingClientRect();
        if (b.bottom < 0 || b.top > vh) return;
        const c = li.cloneNode(true);
        if (!(c instanceof HTMLElement)) return;
        c.style.cssText = `position:absolute;margin:0;top:0;left:${String(b.left)}px;width:${String(b.width)}px;transform:translateY(${String(b.top)}px)`;
        wrap.appendChild(c);
        ghosts.push({ el: c, r: { t: 'post', p: k, h: b.height }, top: b.top, h: b.height });
      });
    }
    document.body.appendChild(ov);
    fn();
    const B = snap();
    const anims: Animation[] = [];
    const ease = 'cubic-bezier(.2,.75,.25,1)';
    for (const g of ghosts) {
      const t = toward(B, g.r, g.top, g.h, vh, 0.2, 2.5);
      g.el.style.transformOrigin = '0 0';
      const base = `translateY(${String(g.top)}px)`;
      const end = t ? `${base} translateY(${String(t.d)}px) scaleY(${String(t.s)})` : base;
      anims.push(g.el.animate([{ transform: base, opacity: 1 }, { transform: end, opacity: 0 }], { duration: 340, easing: ease, fill: 'forwards' }));
    }
    const enter = (el: HTMLElement, r: Row, top: number, h: number, base: string): void => {
      if (top + h < 0 || top > vh) return;
      const t = toward(A, r, top, h, vh, 0.35, 2);
      if (!t) return;
      el.style.transformOrigin = '0 0';
      const delay = Math.round(Math.min(1, Math.abs(top + h / 2 - vh / 2) / vh) * 90);
      anims.push(el.animate([
        { transform: `${base} translateY(${String(t.d)}px) scaleY(${String(t.s)})`, opacity: 0 },
        { opacity: 1, offset: 0.55 },
        { transform: `${base} translateY(0) scaleY(1)`, opacity: 1 },
      ], { duration: 480, delay, easing: ease, fill: 'backwards' }));
    };
    for (const [i, el] of nodes) {
      const r = B.rows[i];
      const o = B.offs[i];
      if (r && o !== undefined) enter(el, r, B.top + o - B.scroll, r.h, `translateY(${String(o)}px)`);
    }
    if (B.head) {
      head.forEach((_, k) => {
        const li = headEl(k);
        const b = li.getBoundingClientRect();
        enter(li, { t: 'post', p: k, h: b.height }, b.top, b.height, '');
      });
    }
    const m = morphing = { ov, anims };
    void Promise.all(anims.map((a) => a.finished.catch(() => null))).then(() => {
      if (morphing === m) { ov.remove(); morphing = null; }
    });
  }

  function zoom(toRaw: number, anchorIn?: number): void {
    const to = toLevel(toRaw);
    let anchor = anchorIn;
    if (to === level && anchor === undefined) return;
    let i = anchor ?? centerPost();
    // A Months or Years row holds many posts; if it still holds the one we zoomed out from, go back to that.
    if (anchor === undefined && focus >= 0 && postRow[focus] === postRow[i] && !(postLevel() && i < head.length)) i = focus;
    else if (anchor !== undefined && focus >= 0 && postRow[focus] === postRow[anchor]) i = anchor = focus;
    focus = i;
    let screenY = postTop(i) - window.scrollY;
    let frac = (window.innerHeight / 2 - screenY) / orIfZero(postH(i), 1);
    if (anchor !== undefined) { screenY = window.innerHeight * 0.3; frac = 0; }
    const target = i;
    const apply = (): void => {
      level = to;
      build();
      const y = anchor !== undefined ? screenY : window.innerHeight / 2 - Math.max(0.05, Math.min(0.95, frac)) * postH(target);
      scrollToPost(target, y);
      render();
      settle();
    };
    if (reduce) apply(); else morph(apply);
  }

  /* A random post, in Posts view (or Cards, if that's where the reader is). */
  function random(): void {
    const pool: number[] = [];
    posts.forEach((p, i) => { if (!p.locked) pool.push(i); });
    const pick = pool[Math.floor(Math.random() * pool.length)];
    if (pick === undefined) return;
    highlightId = post(pick).id;
    const to: Level = level === 3 ? 3 : 4;
    fade(() => {
      if (to !== level) { level = to; build(); }
      for (const n of nodes.values()) n.remove();
      nodes.clear();
      scrollToPost(pick, window.innerHeight * 0.28);
      render();
      if (postLevel() && pick < head.length) headEl(pick).classList.add('is-hit');
      settle();
    });
    setTimeout(() => {
      highlightId = 0;
      for (const n of queryAll(document, '.is-hit', Element)) n.classList.remove('is-hit');
    }, 2400);
  }

  function step(dir: number): void {
    const i = Math.max(0, Math.min(posts.length - 1, centerPost() + dir));
    scrollToPost(i, window.innerHeight / 2 - postH(i) / 2);
  }

  /* ---------- Scrubber: the rail runs on time, newest at the top. A silence of three years or
     more is cut down to a small // break, as on the charts, so the dot crawls through busy years
     and hurries through quiet ones. ---------- */

  const YEAR = 365.25 * 864e5;
  const SILENCE = 3 * YEAR;
  interface Span { from: number; to: number; at: number; len: number }   // from: newest; at, len: rail fractions
  const spans: Span[] = [];
  {
    const runs: [number, number][] = [];
    let from = post(0).t;
    let prev = from;
    for (let i = 1; i < posts.length; i++) {
      const t = post(i).t;
      if (prev - t >= SILENCE) { runs.push([from, prev]); from = t; }
      prev = t;
    }
    runs.push([from, prev]);
    // Each break is as tall as the time it leaves below the last year label above it, topped up to
    // a year, so the labels either side ('26, '13) sit a year apart like all the others.
    const breaks = runs.slice(0, -1).map(([a, b]) => {
      const label = Math.min(Date.UTC(new Date(b).getUTCFullYear() + 1, 0, 1) - 1, a);
      return Math.max(YEAR / 4, YEAR - (label - b));
    });
    const total = Math.max(1, runs.reduce((sum, [a, b]) => sum + a - b, 0) + breaks.reduce((sum, g) => sum + g, 0));
    let at = 0;
    runs.forEach(([a, b], k) => {
      const len = (a - b) / total;
      spans.push({ from: a, to: b, at, len });
      at += len + (breaks[k] ?? 0) / total;
    });
  }
  function railAt(t: number): number {
    for (const sp of spans) {
      if (t >= sp.to) return sp.from === sp.to ? sp.at : sp.at + (sp.from - Math.min(t, sp.from)) / (sp.from - sp.to) * sp.len;
    }
    return 1;
  }
  function timeAt(f: number): number {
    let prev: Span | null = null;
    for (const sp of spans) {
      if (f < sp.at) return prev && f - (prev.at + prev.len) < sp.at - f ? prev.to : sp.from;   // in a break: its nearer side
      if (f <= sp.at + sp.len) return sp.len > 0 ? sp.from - (f - sp.at) / sp.len * (sp.from - sp.to) : sp.from;
      prev = sp;
    }
    return prev ? prev.to : 0;
  }
  /** The post nearest in time (posts run newest first). */
  function postNear(t: number): number {
    let lo = 0;
    let hi = posts.length - 1;
    while (lo < hi) {
      const mid = (lo + hi) >> 1;
      if (post(mid).t > t) lo = mid + 1; else hi = mid;
    }
    return lo > 0 && post(lo - 1).t - t < t - post(lo).t ? lo - 1 : lo;
  }

  function buildScrub(): void {
    let marks = '';
    const seen = new Set<number>();
    let last = -1;
    posts.forEach((p, i) => {
      if (seen.has(p.y)) return;
      seen.add(p.y);
      // A year's label sits at its top: New Year's Eve, or the top of its span if that comes sooner.
      const f = railAt(Date.UTC(p.y + 1, 0, 1) - 1);
      if (last >= 0 && f - last < 0.025) return;
      last = f;
      marks += `<span class="tl-scrub__y" data-p="${String(i)}" style="top:${(f * 100).toFixed(2)}%">'${String(p.y).slice(2)}</span>`;
    });
    for (let k = 1; k < spans.length; k++) {
      const a = spans[k - 1];
      const b = spans[k];
      if (a && b) marks += `<span class="tl-scrub__gap" style="top:${((a.at + a.len + b.at) / 2 * 100).toFixed(2)}%"></span>`;
    }
    track.innerHTML = marks;
    track.append(ghost, scrubThumb, bubble);
    ui.appendChild(scrub);
    // Point at the rail to see the date there (a ghost dot and a label); click to go to it.
    const pointAt = (e: MouseEvent): number => {
      const b = track.getBoundingClientRect();
      const f = Math.max(0, Math.min(1, (e.clientY - b.top) / b.height));
      const i = postNear(timeAt(f));
      const p = post(i);
      ghost.style.top = bubble.style.top = `${String(f * 100)}%`;
      if (ghost.getAttribute('data-era') !== p.era) ghost.setAttribute('data-era', p.era);
      bubble.textContent = `${mon(p.m)} ${String(p.y)}`;
      return i;
    };
    track.addEventListener('pointermove', (e) => {
      if (e.pointerType !== 'mouse') return;
      scrub.classList.add('is-pointing');
      pointAt(e);
    });
    track.addEventListener('pointerleave', () => { scrub.classList.remove('is-pointing'); });
    track.addEventListener('click', (e) => {
      // A year label goes to the top of that year (its newest post), near the top of the screen.
      const y = closest(e.target, '.tl-scrub__y', HTMLElement);
      const yi = y ? Number(y.getAttribute('data-p')) : NaN;
      if (Number.isInteger(yi) && posts[yi]) { scrollToPost(yi, Math.max(90, window.innerHeight * 0.18)); return; }
      const i = pointAt(e);
      scrollToPost(i, window.innerHeight / 2 - postH(i) / 2);
    });
  }

  function flag(name: string, on: boolean): void {
    ui.classList.toggle(name, on);
    ctl.classList.toggle(name, on);
  }

  /* Scrubber position, the pinned year, and whether the reader is deep enough in to show them. */
  function headerBottom(): number {
    const hdr = query(document, '.dm-header', HTMLElement);
    return hdr ? Math.max(0, hdr.getBoundingClientRect().bottom) : 0;
  }
  function updateUi(): void {
    const i = centerPost();
    const p = post(i);
    const firstRow = postTop(Math.min(3, posts.length - 1));
    // Past the first few posts (or anywhere once zoomed), the scrubber and controls wake up.
    flag('is-deep', level !== 4 || window.scrollY + window.innerHeight / 2 > firstRow);
    flag('is-in', level !== 4 || docTop(log) - window.scrollY < window.innerHeight * 0.4);
    flag('is-off', window.scrollY + window.innerHeight < docTop(postLevel() ? log : canvas));
    scrubThumb.style.top = `${String(railAt(p.t) * 100)}%`;
    if (scrubThumb.getAttribute('data-era') !== p.era) scrubThumb.setAttribute('data-era', p.era);

    const hb = headerBottom();
    const y = window.scrollY + hb + 8;
    const listTop = postLevel() ? docTop(log) : canvasTop;
    let show = level !== 0 && y > listTop + 40;
    if (show) {
      const at = post(postAt(y));
      if (stickyLink.textContent !== String(at.y)) { stickyLink.textContent = String(at.y); stickyLink.href = `/${String(at.y)}/`; }
      stickyYear.setAttribute('data-era', level === 1 ? '' : at.era);
      stickyYear.style.top = `${String(hb)}px`;
      stickyYear.style.left = `${String(Math.round(root.getBoundingClientRect().left))}px`;
      // Step aside while the year's own marker passes underneath.
      const marks = [...queryAll(canvas, '.tl-yhdr, .dm-tx-year', Element), ...(postLevel() ? queryAll(log, '.dm-tx-year', Element) : [])];
      for (const m of marks) {
        const b = m.getBoundingClientRect();
        if (b.bottom > hb - 10 && b.top < hb + stickyYear.offsetHeight + 24) show = false;
      }
    }
    stickyYear.classList.toggle('is-on', show);
  }

  /* ---------- Deep links: ?at=YYYY-MM-DD&z=N ---------- */

  function settle(): void {
    flag('is-scrolling', false);
    const url = new URL(location.href);
    const i = centerPost();
    // Reading the first page as a plain home page keeps the address plain too.
    if (level === 4 && (i < Math.min(head.length, 12) || window.scrollY + window.innerHeight / 2 < docTop(log))) {
      url.searchParams.delete('at');
      url.searchParams.delete('z');
    } else {
      url.searchParams.set('at', post(i).date);
      url.searchParams.set('z', String(level));
    }
    if (url.href !== location.href) history.replaceState(history.state, '', url.href);
  }

  /* ---------- Controls ---------- */

  function placeControls(): void {
    // Wide screens: in the header beside search, where it covers nothing. Otherwise it floats.
    const tools = query(document, '.dm-nav__tools', HTMLElement);
    const wide = window.matchMedia('(min-width: 1240px)');
    const place = (): void => {
      if (wide.matches && tools) { tools.insertBefore(ctl, tools.firstChild); ctl.classList.add('tl-ctl--nav'); }
      else { ui.appendChild(ctl); ctl.classList.remove('tl-ctl--nav'); }
    };
    place();
    wide.addEventListener('change', place);
    btnOut.addEventListener('click', () => { zoom(level - 1); });
    btnIn.addEventListener('click', () => { zoom(level + 1); });
    btnRand.addEventListener('click', random);
  }

  /* ---------- Start ---------- */

  const q = new URLSearchParams(location.search);
  const z = q.get('z');
  if (z !== null) level = toLevel(orIfZero(parseInt(z, 10), 0));
  const at = q.get('at');

  placeControls();
  buildScrub();
  document.body.appendChild(ui);
  // Below the server-rendered rows, so nothing already on screen moves.
  log.after(root);
  feed.classList.add('tl-host');
  build();
  if (at !== null) {
    let i = posts.findIndex((p) => p.date <= at);
    if (i < 0) i = posts.length - 1;
    scrollToPost(i, window.innerHeight / 2 - postH(i) / 2);
  }
  render();

  window.addEventListener('scroll', queue, { passive: true });
  let rt = 0;
  let lastW = window.innerWidth;
  window.addEventListener('resize', () => {
    if (window.innerWidth === lastW) { queue(); return; } // phones resize height as their toolbars slide
    lastW = window.innerWidth;
    clearTimeout(rt);
    rt = window.setTimeout(() => {
      const i = centerPost();
      build();
      scrollToPost(i, window.innerHeight / 2 - postH(i) / 2);
      render();
    }, 150);
  });
  root.addEventListener('click', (e) => {
    const zb = closest(e.target, '[data-zoom]', HTMLElement);
    if (!zb) return;
    const toZ = Number(zb.getAttribute('data-zoom'));
    const p = Number(zb.getAttribute('data-p'));
    if (Number.isInteger(toZ) && Number.isInteger(p) && posts[p]) zoom(toZ, p);
  });
  document.addEventListener('keydown', (e) => {
    const t = e.target;
    if (e.metaKey || e.ctrlKey || e.altKey) return;
    if (t instanceof HTMLElement && (/^(input|textarea|select)$/i.test(t.tagName) || t.isContentEditable)) return;
    if (window.scrollY + window.innerHeight < docTop(postLevel() ? log : canvas)) return;
    const k = e.key;
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

/* ---------- Boot ---------- */

const cfg = window.dmTimeline;
const api = str(cfg, 'api', '');
const tz = num(cfg, 'tz', 0) * 1000;
const feed = query(document, '.dm-feed', HTMLElement);
const log = feed && query(feed, '.dm-log', HTMLElement);

if (api !== '' && feed && log) {
  fetchJson(api, { credentials: 'same-origin' })
    .then(async (data) => {
      const posts = isArray(data) ? data.map((r) => parsePost(r, tz)).filter((p): p is Post => p !== null) : [];
      await document.fonts.ready;
      if (posts.length > 0) run(api, feed, log, posts);
    })
    .catch(() => { /* Keep the server-rendered page. */ });
}
