/* Built from frontend/src by `npm run build`; edit the TypeScript there, not this file. */
"use strict";
(() => {
  // src/lib/data.ts
  function isRecord(v) {
    return typeof v === "object" && v !== null && !Array.isArray(v);
  }
  function str(obj, key, fallback) {
    if (!isRecord(obj)) return fallback;
    const v = obj[key];
    return typeof v === "string" ? v : fallback;
  }
  function parseJson(text) {
    const v = JSON.parse(text);
    return v;
  }
  function isArray(v) {
    return Array.isArray(v);
  }

  // src/lib/dom.ts
  function query(root, selector, type) {
    const el = root.querySelector(selector);
    return el instanceof type ? el : null;
  }
  function queryAll(root, selector, type) {
    const out = [];
    for (const el of root.querySelectorAll(selector)) if (el instanceof type) out.push(el);
    return out;
  }
  function closest(target, selector, type) {
    if (!(target instanceof Element)) return null;
    const el = target.closest(selector);
    return el instanceof type ? el : null;
  }

  // src/dmdune.ts
  var t = (key, fallback) => str(window.dmdune, key, fallback);
  document.addEventListener("click", (e) => {
    const play = closest(e.target, "[data-yt]", HTMLElement);
    if (play) {
      const frame = document.createElement("iframe");
      frame.src = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(play.getAttribute("data-yt") ?? "")}?autoplay=1&rel=0`;
      frame.title = t("playLabel", "Play video");
      frame.allow = "autoplay; encrypted-media; picture-in-picture; fullscreen";
      frame.allowFullscreen = true;
      play.replaceWith(frame);
      return;
    }
    const link = closest(e.target, "a[data-dm-lightbox], .dm-prose a[href]", HTMLAnchorElement);
    if (link && isImageLink(link)) {
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.button > 0) return;
      e.preventDefault();
      openViewer(groupOf(link), link);
    }
  });
  function isImageLink(a) {
    return a.querySelector("img") !== null && /\.(jpe?g|png|gif|webp)(\?.*)?$/i.test(a.getAttribute("href") ?? "");
  }
  function groupOf(link) {
    const rel = link.getAttribute("rel") ?? "";
    const scope = link.closest(".dm-prose") ?? document;
    const all = queryAll(scope, "a[href]", HTMLAnchorElement).filter(isImageLink);
    if (rel !== "" && rel !== "lightbox") return all.filter((a) => a.getAttribute("rel") === rel);
    const p = link.closest(".thumbnails, .dm-figure, p");
    if (p?.classList.contains("thumbnails") === true) return all.filter((a) => p.contains(a));
    return [link];
  }
  function captionOf(a) {
    const title = a.getAttribute("title");
    if (title !== null && title !== "") return title;
    const cap = a.closest("figure")?.querySelector("figcaption")?.textContent.trim();
    if (cap !== void 0 && cap !== "") return cap;
    return a.querySelector("img")?.getAttribute("alt") ?? "";
  }
  function openViewer(set, start) {
    let index = Math.max(0, set.indexOf(start));
    const dlg = document.createElement("dialog");
    dlg.className = "dm-lightbox";
    dlg.setAttribute("aria-label", t("viewer", "Picture viewer"));
    const fig = document.createElement("figure");
    const img = document.createElement("img");
    const cap = document.createElement("figcaption");
    fig.appendChild(img);
    fig.appendChild(cap);
    function button(cls, label, text, fn) {
      const b = document.createElement("button");
      b.type = "button";
      b.className = cls;
      b.setAttribute("aria-label", label);
      b.textContent = text;
      b.addEventListener("click", (ev) => {
        ev.stopPropagation();
        fn();
      });
      dlg.appendChild(b);
    }
    function show2(i) {
      index = (i + set.length) % set.length;
      const a = set[index];
      if (!a) return;
      img.src = a.href;
      img.alt = captionOf(a);
      const counter = set.length > 1 ? `${String(index + 1)} / ${String(set.length)}` : "";
      cap.textContent = [img.alt, counter].filter((s) => s !== "").join("  ·  ");
      const after = set[(index + 1) % set.length];
      if (set.length > 1 && after) new Image().src = after.href;
    }
    button("dm-lightbox__close", t("close", "Close"), "×", () => {
      dlg.close();
    });
    if (set.length > 1) {
      button("dm-lightbox__prev", t("prev", "Previous picture"), "‹", () => {
        show2(index - 1);
      });
      button("dm-lightbox__next", t("next", "Next picture"), "›", () => {
        show2(index + 1);
      });
    }
    dlg.appendChild(fig);
    dlg.addEventListener("click", (ev) => {
      if (ev.target === dlg || ev.target === fig) dlg.close();
    });
    dlg.addEventListener("keydown", (ev) => {
      if (ev.key === "ArrowLeft") show2(index - 1);
      if (ev.key === "ArrowRight") show2(index + 1);
    });
    let x0 = null;
    fig.addEventListener("pointerdown", (ev) => {
      x0 = ev.clientX;
    });
    fig.addEventListener("pointerup", (ev) => {
      if (x0 !== null && set.length > 1 && Math.abs(ev.clientX - x0) > 50) show2(index + (ev.clientX < x0 ? 1 : -1));
      x0 = null;
    });
    dlg.addEventListener("close", () => {
      dlg.remove();
    });
    document.body.appendChild(dlg);
    show2(index);
    dlg.showModal();
  }
  function markGone(img) {
    img.closest(".dm-video")?.classList.add("is-gone");
  }
  for (const img of queryAll(document, ".dm-video__play img", HTMLImageElement)) {
    if (img.complete && img.naturalWidth === 0) markGone(img);
    img.addEventListener("error", () => {
      markGone(img);
    });
    img.addEventListener("load", () => {
      if (img.naturalWidth <= 120) markGone(img);
    });
  }
  var tag = query(document, ".dm-hero__tag[data-taglines]", HTMLElement);
  if (tag) {
    let lines = null;
    try {
      lines = parseJson(tag.getAttribute("data-taglines") ?? "");
    } catch {
    }
    const usable = isArray(lines) ? lines.filter((l) => typeof l === "string") : [];
    const line = usable[Math.floor(Math.random() * usable.length)];
    if (line !== void 0) {
      tag.textContent = line;
      tag.lang = /[぀-ヿ一-鿿]/.test(line) ? "ja" : "en";
    }
  }
  var show = (box) => {
    for (const img of queryAll(box, "img[data-dm-src]", HTMLImageElement)) {
      const src = img.getAttribute("data-dm-src");
      img.removeAttribute("data-dm-src");
      if (src !== null) img.src = src;
    }
  };
  var late = queryAll(document, ".dm-media__img[data-dm-src]", HTMLImageElement);
  if ("IntersectionObserver" in window) {
    const io = new IntersectionObserver((entries) => {
      for (const e of entries) {
        if (!e.isIntersecting) continue;
        io.unobserve(e.target);
        show(e.target.closest(".dm-media") ?? e.target.parentElement ?? document);
      }
    }, { rootMargin: "600px 0px" });
    for (const img of late) io.observe(img);
  } else {
    for (const img of late) show(img.closest(".dm-media") ?? img.parentElement ?? document);
  }
  function fitMedia(img) {
    const box = img.parentElement;
    if (!box || box.classList.contains("dm-media--yt")) return;
    const bw = box.clientWidth, bh = box.clientHeight, nw = img.naturalWidth, nh = img.naturalHeight;
    if (bw === 0 || bh === 0 || nw === 0 || nh === 0) return;
    const upscale = Math.max(bw / nw, bh / nh);
    const mismatch = Math.abs(Math.log(nw / nh / (bw / bh)));
    const wider = nw / nh > bw / bh;
    const fit = upscale > 1.6 || mismatch > Math.log(1.7) && !(wider && nw / nh <= 5 && upscale <= 1.6);
    box.classList.toggle("is-fit", fit);
    box.style.setProperty("--nw", `${String(nw)}px`);
    box.style.setProperty("--nh", `${String(nh)}px`);
  }
  for (const img of queryAll(document, ".dm-media__img", HTMLImageElement)) {
    if (img.complete && img.naturalWidth > 0) fitMedia(img);
    else img.addEventListener("load", () => {
      fitMedia(img);
    });
  }
  var mediaQueued = false;
  window.addEventListener("resize", () => {
    if (mediaQueued) return;
    mediaQueued = true;
    requestAnimationFrame(() => {
      mediaQueued = false;
      for (const img of queryAll(document, ".dm-media__img", HTMLImageElement)) if (img.naturalWidth > 0) fitMedia(img);
    });
  });
})();
