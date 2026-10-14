/* Built from frontend/src by `npm run build`; edit the TypeScript there, not this file. */
"use strict";
(() => {
  // src/lib/dom.ts
  function query(root2, selector, type) {
    const el = root2.querySelector(selector);
    return el instanceof type ? el : null;
  }
  function queryAll(root2, selector, type) {
    const out = [];
    for (const el of root2.querySelectorAll(selector)) if (el instanceof type) out.push(el);
    return out;
  }
  function closest(target, selector, type) {
    if (!(target instanceof Element)) return null;
    const el = target.closest(selector);
    return el instanceof type ? el : null;
  }
  function isNode(target) {
    return target instanceof Node;
  }
  function cookieDomain() {
    return /(^|\.)darkmirage\.com$/.test(location.hostname) ? "; domain=.darkmirage.com" : "";
  }
  function onReady(fn) {
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", fn);
    else fn();
  }

  // src/chrome.ts
  var root = document.documentElement;
  var media = window.matchMedia("(prefers-color-scheme: dark)");
  function isDark() {
    const forced = root.getAttribute("data-theme");
    return forced !== null ? forced === "dark" : media.matches;
  }
  function label() {
    const text = isDark() ? "Switch to light mode" : "Switch to dark mode";
    for (const b of queryAll(document, "[data-dm-toggle]", Element)) b.setAttribute("aria-label", text);
  }
  function save(mode) {
    const value = mode ?? "system";
    document.cookie = `dm-theme=${value}; path=/; SameSite=Lax; max-age=31536000${cookieDomain()}`;
    try {
      localStorage.setItem("dm-theme", value);
    } catch {
    }
  }
  function sync() {
    const m = /(?:^|; )dm-theme=(light|dark|system)/.exec(document.cookie)?.[1];
    if (m === void 0) return;
    if (m === "system") root.removeAttribute("data-theme");
    else root.setAttribute("data-theme", m);
    label();
  }
  window.addEventListener("pageshow", sync);
  document.addEventListener("visibilitychange", () => {
    if (!document.hidden) sync();
  });
  var ERA = document.documentElement.getAttribute("data-skin") ?? "";
  function withEra(href, era) {
    const url = new URL(href, location.href);
    if (era !== "") url.searchParams.set("era", era);
    else url.searchParams.delete("era");
    return url;
  }
  function isSiteUrl(url) {
    return /(^|\.)darkmirage\.com$/.test(url.hostname) || url.hostname === location.hostname;
  }
  document.addEventListener("click", (e) => {
    const era = closest(e.target, "[data-dm-skin]", Element);
    if (!era) return;
    if (era.closest(".dm-tmw")) {
      try {
        sessionStorage.setItem("dm-tm-open", "1");
      } catch {
      }
    }
    location.assign(withEra(location.href, era.getAttribute("data-dm-skin") ?? "").toString());
  });
  if (ERA !== "") {
    document.addEventListener("click", (e) => {
      if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      const a = closest(e.target, "a[href]", HTMLAnchorElement);
      if (!a || a.target !== "" && a.target !== "_self" || a.hasAttribute("download")) return;
      const url = new URL(a.href, location.href);
      if (!isSiteUrl(url) || url.searchParams.has("era")) return;
      if (url.pathname === location.pathname && url.search === location.search && url.hash !== "") return;
      if (/\.(?:jpe?g|png|gif|webp|svg|mp3|zip|pdf|txt)$/i.test(url.pathname)) return;
      a.href = withEra(a.href, ERA).toString();
    });
    document.addEventListener("submit", (e) => {
      const form = e.target;
      if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== "get") return;
      if (!isSiteUrl(new URL(form.action, location.href)) || form.querySelector('input[name="era"]')) return;
      const input = document.createElement("input");
      input.type = "hidden";
      input.name = "era";
      input.value = ERA;
      form.appendChild(input);
    });
  }
  function timeMachine(tm2, btn) {
    let timer = 0;
    let pointer = "";
    const set = (open) => {
      clearTimeout(timer);
      if (open !== tm2.classList.contains("is-open")) tm2.classList.remove("is-still");
      tm2.classList.toggle("is-open", open);
      btn.setAttribute("aria-expanded", open ? "true" : "false");
    };
    tm2.addEventListener("pointerenter", (e) => {
      if (e.pointerType === "mouse") set(true);
    });
    tm2.addEventListener("pointerleave", (e) => {
      if (e.pointerType !== "mouse") return;
      clearTimeout(timer);
      timer = window.setTimeout(() => {
        set(false);
      }, 350);
    });
    tm2.addEventListener("pointerdown", (e) => {
      pointer = e.pointerType;
    });
    btn.addEventListener("click", (e) => {
      if (pointer === "mouse" && e.detail > 0) set(true);
      else set(!tm2.classList.contains("is-open"));
      pointer = "";
    });
    document.addEventListener("click", (e) => {
      if (!isNode(e.target) || !tm2.contains(e.target)) set(false);
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && tm2.classList.contains("is-open")) {
        set(false);
        btn.focus();
      }
    });
  }
  function shareLink(btn, skin) {
    const text = btn.textContent;
    btn.addEventListener("click", () => {
      const canon = query(document, 'link[rel="canonical"]', HTMLLinkElement);
      const url = new URL(canon ? canon.href : location.href);
      url.searchParams.set("era", skin);
      const link = url.toString();
      const done = (msg) => {
        btn.textContent = msg;
        setTimeout(() => {
          btn.textContent = text;
        }, 1800);
      };
      const fallback = () => {
        window.prompt("Copy this link:", link);
      };
      if ("share" in navigator && matchMedia("(pointer: coarse)").matches) {
        navigator.share({ title: document.title, url: link }).catch(() => {
        });
      } else if ("clipboard" in navigator) {
        navigator.clipboard.writeText(link).then(() => {
          done("Copied!");
        }, fallback);
      } else {
        fallback();
      }
    });
  }
  var tm = query(document, ".dm-tmw", HTMLElement);
  if (tm) {
    const previews = () => {
      for (const img of queryAll(tm, "img[data-dm-src]", HTMLImageElement)) {
        img.src = img.getAttribute("data-dm-src") ?? "";
        img.removeAttribute("data-dm-src");
      }
    };
    if (tm.classList.contains("is-open")) previews();
    else for (const type of ["pointerenter", "focusin", "click"]) tm.addEventListener(type, previews, { once: true });
    const btn = query(tm, ".dm-tmw__btn", HTMLElement);
    if (btn) timeMachine(tm, btn);
    const share = query(tm, ".dm-tmw__share", HTMLElement);
    const skin = query(tm, '[data-dm-skin][aria-pressed="true"]', Element)?.getAttribute("data-dm-skin");
    if (share && skin != null && skin !== "") shareLink(share, skin);
  }
  var OPEN_MENUS = ".dm-sheet[open], .dm-nav__more[open]";
  document.addEventListener("click", (e) => {
    if (closest(e.target, "[data-dm-toggle]", Element)) {
      const next = isDark() ? "light" : "dark";
      if (next === "dark" === media.matches) {
        root.removeAttribute("data-theme");
        save(null);
      } else {
        root.setAttribute("data-theme", next);
        save(next);
      }
      label();
      return;
    }
    for (const d of queryAll(document, OPEN_MENUS, Element)) {
      if (!isNode(e.target) || !d.contains(e.target)) d.removeAttribute("open");
    }
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") for (const d of queryAll(document, OPEN_MENUS, Element)) d.removeAttribute("open");
  });
  var pinned = null;
  function fitNav() {
    const navList = query(document, ".dm-nav__links", HTMLElement);
    const navMore = query(document, ".dm-nav__more", HTMLElement);
    const menu = navMore && query(navMore, "ul", HTMLElement);
    if (!navList || !navMore || !menu) return;
    pinned ?? (pinned = queryAll(menu, ":scope > li", HTMLElement));
    navList.classList.add("is-managed");
    const items = queryAll(navList, ":scope > li", HTMLElement);
    for (const li of items) li.hidden = false;
    navMore.hidden = pinned.length === 0;
    navMore.removeAttribute("open");
    menu.textContent = "";
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
      if (copy instanceof HTMLElement) {
        copy.hidden = false;
        menu.appendChild(copy);
      }
      if (li.querySelector("[aria-current]")) current = true;
    }
    for (const li of pinned) {
      menu.appendChild(li);
      if (li.querySelector("[aria-current]")) current = true;
    }
    navMore.classList.toggle("has-current", current);
  }
  var fitQueued = false;
  window.addEventListener("resize", () => {
    if (fitQueued) return;
    fitQueued = true;
    requestAnimationFrame(() => {
      fitQueued = false;
      fitNav();
    });
  });
  onReady(() => {
    label();
    if (document.fonts.status !== "loaded") void document.fonts.ready.then(fitNav);
    else fitNav();
  });
  media.addEventListener("change", label);
})();
