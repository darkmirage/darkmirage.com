/* DarkMirage chrome (www, lyrics, gallery): light/dark toggle, era picker and time machine,
   header overflow menu, mobile sheet. */
import { closest, cookieDomain, isNode, onReady, query, queryAll } from './lib/dom';

const root = document.documentElement;
const media = window.matchMedia('(prefers-color-scheme: dark)');
type Mode = 'light' | 'dark';

function isDark(): boolean {
  const forced = root.getAttribute('data-theme');
  return forced !== null ? forced === 'dark' : media.matches;
}

function label(): void {
  const text = isDark() ? 'Switch to light mode' : 'Switch to dark mode';
  for (const b of queryAll(document, '[data-dm-toggle]', Element)) b.setAttribute('aria-label', text);
}

// One cookie for all *.darkmirage.com sites, so the choice follows the reader between them.
// "system" is stored rather than deleted, or another site's localStorage fallback would win.
function save(mode: Mode | null): void {
  const value = mode ?? 'system';
  document.cookie = `dm-theme=${value}; path=/; SameSite=Lax; max-age=31536000${cookieDomain()}`;
  try { localStorage.setItem('dm-theme', value); } catch { /* storage blocked: the cookie is enough */ }
}

// A page restored by Back, or a tab left open, may predate a change made on another site.
function sync(): void {
  const m = /(?:^|; )dm-theme=(light|dark|system)/.exec(document.cookie)?.[1];
  if (m === undefined) return;
  if (m === 'system') root.removeAttribute('data-theme');
  else root.setAttribute('data-theme', m);
  label();
}
window.addEventListener('pageshow', sync);
document.addEventListener('visibilitychange', () => { if (!document.hidden) sync(); });

// Era skins live in the address (?era=<theme>; see chrome/era-link.php), so an era belongs to
// one tab: picking one moves this page to that address, and clicks on the site's own links carry
// it along. Anything opened in a new tab, or from elsewhere, starts in today's design.
const ERA = document.documentElement.getAttribute('data-skin') ?? '';

function withEra(href: string, era: string): URL {
  const url = new URL(href, location.href);
  if (era !== '') url.searchParams.set('era', era);
  else url.searchParams.delete('era');
  return url;
}

function isSiteUrl(url: URL): boolean {
  return /(^|\.)darkmirage\.com$/.test(url.hostname) || url.hostname === location.hostname;
}

document.addEventListener('click', (e) => {
  const era = closest(e.target, '[data-dm-skin]', Element);
  if (!era) return;
  // Picked from the time machine: reopen it on the next page (see dmc_time_machine_html).
  if (era.closest('.dm-tmw')) {
    try { sessionStorage.setItem('dm-tm-open', '1'); } catch { /* it just opens closed */ }
  }
  location.assign(withEra(location.href, era.getAttribute('data-dm-skin') ?? '').toString());
});

if (ERA !== '') {
  // Same-tab clicks on pages of this site keep the era; Ctrl/Cmd/Shift/middle clicks (new tabs
  // and windows) and target=_blank links don't.
  document.addEventListener('click', (e) => {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    const a = closest(e.target, 'a[href]', HTMLAnchorElement);
    if (!a || (a.target !== '' && a.target !== '_self') || a.hasAttribute('download')) return;
    const url = new URL(a.href, location.href);
    if (!isSiteUrl(url) || url.searchParams.has('era')) return;
    if (url.pathname === location.pathname && url.search === location.search && url.hash !== '') return;
    if (/\.(?:jpe?g|png|gif|webp|svg|mp3|zip|pdf|txt)$/i.test(url.pathname)) return;
    a.href = withEra(a.href, ERA).toString();
  });
  // Forms that load a page of the site (search) carry it too.
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'get') return;
    if (!isSiteUrl(new URL(form.action, location.href)) || form.querySelector('input[name="era"]')) return;
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'era';
    input.value = ERA;
    form.appendChild(input);
  });
}

// The time machine grows out of its button: on hover with a mouse (closing a moment after the
// pointer leaves, so crossing the gap doesn't snap it shut), or on tap / Enter. Escape or a
// click elsewhere closes it.
function timeMachine(tm: HTMLElement, btn: HTMLElement): void {
  let timer = 0;
  let pointer = '';
  const set = (open: boolean): void => {
    clearTimeout(timer);
    if (open !== tm.classList.contains('is-open')) tm.classList.remove('is-still');
    tm.classList.toggle('is-open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  };
  tm.addEventListener('pointerenter', (e) => { if (e.pointerType === 'mouse') set(true); });
  tm.addEventListener('pointerleave', (e) => {
    if (e.pointerType !== 'mouse') return;
    clearTimeout(timer);
    timer = window.setTimeout(() => { set(false); }, 350);
  });
  // A mouse has already opened it by hovering, so its click keeps it open; touch and keys toggle.
  tm.addEventListener('pointerdown', (e) => { pointer = e.pointerType; });
  btn.addEventListener('click', (e) => {
    if (pointer === 'mouse' && e.detail > 0) set(true);
    else set(!tm.classList.contains('is-open'));
    pointer = '';
  });
  document.addEventListener('click', (e) => { if (!isNode(e.target) || !tm.contains(e.target)) set(false); });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && tm.classList.contains('is-open')) { set(false); btn.focus(); }
  });
}

// In an old era, "Copy link" shares this page in that era (?era=<theme>): the share sheet on
// phones, the clipboard elsewhere.
function shareLink(btn: HTMLElement, skin: string): void {
  const text = btn.textContent;
  btn.addEventListener('click', () => {
    const canon = query(document, 'link[rel="canonical"]', HTMLLinkElement);
    const url = new URL(canon ? canon.href : location.href);
    url.searchParams.set('era', skin);
    const link = url.toString();
    const done = (msg: string): void => {
      btn.textContent = msg;
      setTimeout(() => { btn.textContent = text; }, 1800);
    };
    const fallback = (): void => { window.prompt('Copy this link:', link); };
    if ('share' in navigator && matchMedia('(pointer: coarse)').matches) {
      navigator.share({ title: document.title, url: link }).catch(() => { /* dismissed */ });
    } else if ('clipboard' in navigator) {
      navigator.clipboard.writeText(link).then(() => { done('Copied!'); }, fallback);
    } else {
      fallback();
    }
  });
}

const tm = query(document, '.dm-tmw', HTMLElement);
if (tm) {
  // The era previews only load once the reader reaches for the time machine.
  const previews = (): void => {
    for (const img of queryAll(tm, 'img[data-dm-src]', HTMLImageElement)) {
      img.src = img.getAttribute('data-dm-src') ?? '';
      img.removeAttribute('data-dm-src');
    }
  };
  if (tm.classList.contains('is-open')) previews();
  else for (const type of ['pointerenter', 'focusin', 'click']) tm.addEventListener(type, previews, { once: true });
  const btn = query(tm, '.dm-tmw__btn', HTMLElement);
  if (btn) timeMachine(tm, btn);
  const share = query(tm, '.dm-tmw__share', HTMLElement);
  const skin = query(tm, '[data-dm-skin][aria-pressed="true"]', Element)?.getAttribute('data-dm-skin');
  if (share && skin != null && skin !== '') shareLink(share, skin);
}

const OPEN_MENUS = '.dm-sheet[open], .dm-nav__more[open]';

document.addEventListener('click', (e) => {
  if (closest(e.target, '[data-dm-toggle]', Element)) {
    const next: Mode = isDark() ? 'light' : 'dark';
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
  for (const d of queryAll(document, OPEN_MENUS, Element)) {
    if (!isNode(e.target) || !d.contains(e.target)) d.removeAttribute('open');
  }
});

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') for (const d of queryAll(document, OPEN_MENUS, Element)) d.removeAttribute('open');
});

// Header: "More" always holds the links the server put there (Japanese lessons, Capsule); links
// that don't fit on one line join them, so the bar never wraps.
let pinned: HTMLElement[] | null = null;
function fitNav(): void {
  const navList = query(document, '.dm-nav__links', HTMLElement);
  const navMore = query(document, '.dm-nav__more', HTMLElement);
  const menu = navMore && query(navMore, 'ul', HTMLElement);
  if (!navList || !navMore || !menu) return;
  pinned ??= queryAll(menu, ':scope > li', HTMLElement);
  navList.classList.add('is-managed');
  const items = queryAll(navList, ':scope > li', HTMLElement);
  for (const li of items) li.hidden = false;
  navMore.hidden = pinned.length === 0;
  navMore.removeAttribute('open');
  menu.textContent = '';
  if (navList.offsetParent && navList.scrollWidth > navList.clientWidth) {
    navMore.hidden = false;
    for (let i = items.length - 1; i > 0 && navList.scrollWidth > navList.clientWidth; i--) {
      const li = items[i];
      if (li) li.hidden = true;
    }
  }
  let current = false;
  for (const li of items) {
    if (li.hidden === false) continue;
    const copy = li.cloneNode(true);
    if (copy instanceof HTMLElement) { copy.hidden = false; menu.appendChild(copy); }
    if (li.querySelector('[aria-current]')) current = true;
  }
  for (const li of pinned) {
    menu.appendChild(li);
    if (li.querySelector('[aria-current]')) current = true;
  }
  navMore.classList.toggle('has-current', current);
}

let fitQueued = false;
window.addEventListener('resize', () => {
  if (fitQueued) return;
  fitQueued = true;
  requestAnimationFrame(() => { fitQueued = false; fitNav(); });
});

onReady(() => {
  label();
  // Measured in the stand-in font, the links can fold into More only to come back out when the
  // real font lands a moment later; that jump is worse than waiting for it.
  if (document.fonts.status !== 'loaded') void document.fonts.ready.then(fitNav);
  else fitNav();
});
media.addEventListener('change', label);
