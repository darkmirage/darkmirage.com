/* DarkMirage Dune (blog only): click-to-play video, image viewer, card picture fitting, taglines.
   The toggle, header and sheet live in chrome.ts. */
import { isArray, parseJson, str } from './lib/data';
import { closest, query, queryAll } from './lib/dom';

const t = (key: string, fallback: string): string => str(window.dmdune, key, fallback);

document.addEventListener('click', (e) => {
  const play = closest(e.target, '[data-yt]', HTMLElement);
  if (play) {
    const frame = document.createElement('iframe');
    frame.src = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(play.getAttribute('data-yt') ?? '')}?autoplay=1&rel=0`;
    frame.title = t('playLabel', 'Play video');
    frame.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
    frame.allowFullscreen = true;
    play.replaceWith(frame);
    return;
  }

  const link = closest(e.target, 'a[data-dm-lightbox], .dm-prose a[href]', HTMLAnchorElement);
  if (link && isImageLink(link)) {
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.button > 0) return;
    e.preventDefault();
    openViewer(groupOf(link), link);
  }
});

function isImageLink(a: HTMLAnchorElement): boolean {
  return a.querySelector('img') !== null && /\.(jpe?g|png|gif|webp)(\?.*)?$/i.test(a.getAttribute('href') ?? '');
}

// The old Lightbox/Thickbox plugins grouped pictures into sets with rel="lightbox[name]" (or any
// shared rel). Pictures in one set browse together; anything else opens on its own.
function groupOf(link: HTMLAnchorElement): HTMLAnchorElement[] {
  const rel = link.getAttribute('rel') ?? '';
  const scope = link.closest('.dm-prose') ?? document;
  const all = queryAll(scope, 'a[href]', HTMLAnchorElement).filter(isImageLink);
  if (rel !== '' && rel !== 'lightbox') return all.filter((a) => a.getAttribute('rel') === rel);
  const p = link.closest('.thumbnails, .dm-figure, p');
  if (p?.classList.contains('thumbnails') === true) return all.filter((a) => p.contains(a));
  return [link];
}

function captionOf(a: HTMLAnchorElement): string {
  const title = a.getAttribute('title');
  if (title !== null && title !== '') return title;
  const cap = a.closest('figure')?.querySelector('figcaption')?.textContent.trim();
  if (cap !== undefined && cap !== '') return cap;
  return a.querySelector('img')?.getAttribute('alt') ?? '';
}

function openViewer(set: HTMLAnchorElement[], start: HTMLAnchorElement): void {
  let index = Math.max(0, set.indexOf(start));
  const dlg = document.createElement('dialog');
  dlg.className = 'dm-lightbox';
  dlg.setAttribute('aria-label', t('viewer', 'Picture viewer'));
  const fig = document.createElement('figure');
  const img = document.createElement('img');
  const cap = document.createElement('figcaption');
  fig.appendChild(img);
  fig.appendChild(cap);

  function button(cls: string, label: string, text: string, fn: () => void): void {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = cls;
    b.setAttribute('aria-label', label);
    b.textContent = text;
    b.addEventListener('click', (ev) => { ev.stopPropagation(); fn(); });
    dlg.appendChild(b);
  }
  function show(i: number): void {
    index = (i + set.length) % set.length;
    const a = set[index];
    if (!a) return;
    img.src = a.href;
    img.alt = captionOf(a);
    const counter = set.length > 1 ? `${String(index + 1)} / ${String(set.length)}` : '';
    cap.textContent = [img.alt, counter].filter((s) => s !== '').join('  ·  ');
    const after = set[(index + 1) % set.length];
    if (set.length > 1 && after) new Image().src = after.href;
  }
  button('dm-lightbox__close', t('close', 'Close'), '×', () => { dlg.close(); });
  if (set.length > 1) {
    button('dm-lightbox__prev', t('prev', 'Previous picture'), '‹', () => { show(index - 1); });
    button('dm-lightbox__next', t('next', 'Next picture'), '›', () => { show(index + 1); });
  }
  dlg.appendChild(fig);
  dlg.addEventListener('click', (ev) => { if (ev.target === dlg || ev.target === fig) dlg.close(); });
  dlg.addEventListener('keydown', (ev) => {
    if (ev.key === 'ArrowLeft') show(index - 1);
    if (ev.key === 'ArrowRight') show(index + 1);
  });
  let x0: number | null = null;
  fig.addEventListener('pointerdown', (ev) => { x0 = ev.clientX; });
  fig.addEventListener('pointerup', (ev) => {
    if (x0 !== null && set.length > 1 && Math.abs(ev.clientX - x0) > 50) show(index + (ev.clientX < x0 ? 1 : -1));
    x0 = null;
  });
  dlg.addEventListener('close', () => { dlg.remove(); });
  document.body.appendChild(dlg);
  show(index);
  dlg.showModal();
}

// Pictures that no longer exist (2003-04 files and sites long gone): a card or log picture becomes
// the NO SIGNAL panel; a picture in a post becomes a small "picture lost" plate. Errors don't
// bubble, so this listens in the capture phase, which also covers rows the timeline adds later.
function lose(img: HTMLImageElement): void {
  if (img.closest('.dm-video')) return; // handled by markGone below
  const media = img.closest('.dm-media');
  if (media) { media.classList.add('is-lost'); return; }
  if (!img.closest('.dm-prose')) return;
  const w = Number(img.getAttribute('width'));
  const h = Number(img.getAttribute('height'));
  if ((w > 0 && w < 40) || (h > 0 && h < 40)) { img.remove(); return; } // counters, buttons
  const plate = document.createElement('span');
  plate.className = 'dm-lost';
  plate.setAttribute('role', 'img');
  plate.setAttribute('aria-label', img.alt !== '' ? `Picture no longer available: ${img.alt}` : 'Picture no longer available');
  plate.textContent = 'PICTURE LOST';
  // A link around just this picture pointed at the same lost file.
  const a = img.parentElement;
  const target = a instanceof HTMLAnchorElement && a.children.length === 1 && a.textContent.trim() === '' ? a : img;
  target.replaceWith(plate);
}
document.addEventListener('error', (e) => { if (e.target instanceof HTMLImageElement) lose(e.target); }, true);
for (const img of queryAll(document, '.dm-media img, .dm-prose img', HTMLImageElement)) {
  if (img.complete && img.naturalWidth === 0 && img.getAttribute('src') !== null && !img.hasAttribute('data-dm-src')) lose(img);
}

// A missing thumbnail means YouTube has removed the video.
function markGone(img: HTMLImageElement): void {
  img.closest('.dm-video')?.classList.add('is-gone');
}
for (const img of queryAll(document, '.dm-video__play img', HTMLImageElement)) {
  if (img.complete && img.naturalWidth === 0) markGone(img);
  img.addEventListener('error', () => { markGone(img); });
  // YouTube serves a 120px grey placeholder (status 200) for some removed videos.
  img.addEventListener('load', () => { if (img.naturalWidth <= 120) markGone(img); });
}

// A random subtitle per visit, as the Randomize plugin did from 2005.
const tag = query(document, '.dm-hero__tag[data-taglines]', HTMLElement);
if (tag) {
  let lines: unknown = null;
  try { lines = parseJson(tag.getAttribute('data-taglines') ?? ''); } catch { /* keep the server's line */ }
  const usable = isArray(lines) ? lines.filter((l): l is string => typeof l === 'string') : [];
  const line = usable[Math.floor(Math.random() * usable.length)];
  if (line !== undefined) {
    tag.textContent = line;
    tag.lang = /[぀-ヿ一-鿿]/.test(line) ? 'ja' : 'en';
  }
}

// Post pictures the server held back (data-dm-src) load about a screen before they scroll into
// view, together with their blurred backdrop copy.
const show = (box: ParentNode): void => {
  for (const img of queryAll(box, 'img[data-dm-src]', HTMLImageElement)) {
    const src = img.getAttribute('data-dm-src');
    img.removeAttribute('data-dm-src');
    if (src !== null) img.src = src;
  }
};
const late = queryAll(document, '.dm-media__img[data-dm-src]', HTMLImageElement);
if ('IntersectionObserver' in window) {
  const io = new IntersectionObserver((entries) => {
    for (const e of entries) {
      if (!e.isIntersecting) continue;
      io.unobserve(e.target);
      show(e.target.closest('.dm-media') ?? e.target.parentElement ?? document);
    }
  }, { rootMargin: '600px 0px' });
  for (const img of late) io.observe(img);
} else {
  for (const img of late) show(img.closest('.dm-media') ?? img.parentElement ?? document);
}

// Card pictures: crop to fill when that looks fine, otherwise show the whole picture letterboxed.
function fitMedia(img: HTMLImageElement): void {
  const box = img.parentElement;
  if (!box || box.classList.contains('dm-media--yt')) return;
  const bw = box.clientWidth, bh = box.clientHeight, nw = img.naturalWidth, nh = img.naturalHeight;
  if (bw === 0 || bh === 0 || nw === 0 || nh === 0) return;
  const upscale = Math.max(bw / nw, bh / nh);
  const mismatch = Math.abs(Math.log((nw / nh) / (bw / bh)));
  // Wide panoramas fill by cropping the sides (their middle is the subject); everything else that
  // would be badly cropped or blown up is letterboxed.
  const wider = nw / nh > bw / bh;
  const fit = upscale > 1.6 || (mismatch > Math.log(1.7) && !(wider && nw / nh <= 5 && upscale <= 1.6));
  box.classList.toggle('is-fit', fit);
  // Never enlarge a letterboxed picture: old logos and screencaps are small, often palette PNGs
  // with hard edges, and any upscaling makes their edges stair-step.
  box.style.setProperty('--nw', `${String(nw)}px`);
  box.style.setProperty('--nh', `${String(nh)}px`);
}
for (const img of queryAll(document, '.dm-media__img', HTMLImageElement)) {
  if (img.complete && img.naturalWidth > 0) fitMedia(img);
  else img.addEventListener('load', () => { fitMedia(img); });
}
let mediaQueued = false;
window.addEventListener('resize', () => {
  if (mediaQueued) return;
  mediaQueued = true;
  requestAnimationFrame(() => {
    mediaQueued = false;
    for (const img of queryAll(document, '.dm-media__img', HTMLImageElement)) if (img.naturalWidth > 0) fitMedia(img);
  });
});
