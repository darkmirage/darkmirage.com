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
  function num(obj, key, fallback) {
    if (!isRecord(obj)) return fallback;
    const v = obj[key];
    const n = typeof v === "number" ? v : typeof v === "string" && v.trim() !== "" ? Number(v) : NaN;
    return Number.isFinite(n) ? n : fallback;
  }
  function parseJson(text) {
    const v = JSON.parse(text);
    return v;
  }
  async function fetchJson(url, init) {
    const r = await fetch(url, init);
    if (!r.ok) throw new Error(`HTTP ${String(r.status)}`);
    return parseJson(await r.text());
  }
  function isArray(v) {
    return Array.isArray(v);
  }
  function orIfZero(n, fallback) {
    return Number.isFinite(n) && n !== 0 ? n : fallback;
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

  // src/timeline.ts
  var LEVELS = ["Years", "Months", "Titles", "Cards", "Posts"];
  var MON = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
  var JA = { anime: "アニメ", blog: "ブログ", otaku: "オタク", games: "ゲーム", music: "音楽", money: "お金", reviews: "レビュー", tech: "技術", rant: "愚痴", figures: "フィギュア", manga: "漫画", world: "世界", japanese: "日本語", news: "ニュース" };
  function toLevel(n) {
    return n <= 0 ? 0 : n >= 4 ? 4 : n === 1 ? 1 : n === 2 ? 2 : 3;
  }
  function mon(m) {
    return MON[m] ?? "";
  }
  function esc(s) {
    return String(s).replace(/[&<>"]/g, (c) => c === "&" ? "&amp;" : c === "<" ? "&lt;" : c === ">" ? "&gt;" : "&quot;");
  }
  function pad(n) {
    return n < 10 ? `0${String(n)}` : String(n);
  }
  function docTop(start) {
    let y = 0;
    for (let el = start; el instanceof HTMLElement; el = el.offsetParent) y += el.offsetTop;
    return y;
  }
  function parsePost(r, tz2) {
    if (!isArray(r)) return null;
    const [id, ts, title, cat, era, n, locked, thumb, url] = r;
    if (typeof id !== "number" || typeof ts !== "number" || typeof title !== "string" || typeof cat !== "string" || typeof era !== "string" || typeof n !== "number" || typeof thumb !== "string" || typeof url !== "string") return null;
    const d = new Date(ts * 1e3 + tz2);
    return {
      id,
      title: title !== "" ? title : "(untitled)",
      cat,
      era,
      n,
      locked: locked === 1 || locked === true,
      thumb,
      url,
      y: d.getUTCFullYear(),
      m: d.getUTCMonth(),
      d: d.getUTCDate(),
      date: `${String(d.getUTCFullYear())}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())}`,
      t: d.getTime()
    };
  }
  function parseExcerpts(x) {
    const out = /* @__PURE__ */ new Map();
    if (!isRecord(x)) return out;
    for (const [k, v] of Object.entries(x)) if (typeof v === "string") out.set(Number(k), v);
    return out;
  }
  function run(api2, feed2, log2, posts) {
    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const post = (i) => {
      const p = posts[i];
      if (!p) throw new RangeError(`no post ${String(i)}`);
      return p;
    };
    const arts = queryAll(log2, ".dm-tx", HTMLElement);
    const ok = arts.length > 0 && arts.every((a, k) => {
      const no = query(a, ".dm-tx__no", HTMLElement);
      return no !== null && posts[k]?.id === parseInt(no.textContent.replace(/\D/g, ""), 10);
    });
    const head = ok ? arts : [];
    const headEl = (k) => {
      const a = head[k];
      if (!a) throw new RangeError(`no server row ${String(k)}`);
      return a.closest("li") ?? a;
    };
    let level = 4;
    let rows = [];
    let offs = [0];
    let postRow = [];
    let total = 0;
    const nodes = /* @__PURE__ */ new Map();
    const excerpts = /* @__PURE__ */ new Map();
    const asked = /* @__PURE__ */ new Set();
    let canvasTop = 0;
    let queued = false;
    let settleTimer = 0;
    let highlightId = 0;
    let focus = -1;
    const row = (i) => {
      const r = rows[i];
      if (!r) throw new RangeError(`no row ${String(i)}`);
      return r;
    };
    const off = (i) => offs[i] ?? total;
    const root = document.createElement("div");
    const canvas = document.createElement("div");
    canvas.className = "tl-canvas";
    root.appendChild(canvas);
    const ui = document.createElement("div");
    ui.className = "tl-ui";
    const stickyYear = document.createElement("div");
    stickyYear.className = "tl-year dm-tx-year";
    stickyYear.setAttribute("aria-hidden", "true");
    const stickyLink = document.createElement("a");
    stickyLink.tabIndex = -1;
    stickyLink.href = "#";
    stickyYear.appendChild(stickyLink);
    ui.appendChild(stickyYear);
    function icon(d) {
      return `<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${d}</svg>`;
    }
    function ctlButton(cls, labelText, title2, svg) {
      const b = document.createElement("button");
      b.type = "button";
      b.className = cls;
      b.setAttribute("aria-label", labelText);
      b.title = title2;
      b.innerHTML = icon(svg);
      return b;
    }
    const ctl = document.createElement("div");
    ctl.className = "tl-ctl";
    ctl.setAttribute("role", "toolbar");
    ctl.setAttribute("aria-label", "Timeline zoom");
    const btnOut = ctlButton("tl-ctl__out", "Zoom out", "Zoom out (−)", '<path d="M5 12h14"/>');
    const label = document.createElement("span");
    label.className = "tl-ctl__level";
    label.setAttribute("aria-live", "polite");
    const btnIn = ctlButton("tl-ctl__in", "Zoom in", "Zoom in (+)", '<path d="M12 5v14M5 12h14"/>');
    const btnRand = ctlButton("tl-ctl__rand", "Random post", "Random post (R)", '<rect x="4" y="4" width="16" height="16" rx="3"/><circle cx="9" cy="9" r="1.2" fill="currentColor"/><circle cx="15" cy="15" r="1.2" fill="currentColor"/><circle cx="15" cy="9" r="1.2" fill="currentColor"/><circle cx="9" cy="15" r="1.2" fill="currentColor"/>');
    ctl.append(btnOut, label, btnIn, btnRand);
    const scrub = document.createElement("div");
    scrub.className = "tl-scrub";
    scrub.setAttribute("aria-hidden", "true");
    const track = document.createElement("div");
    track.className = "tl-scrub__track";
    const scrubThumb = document.createElement("span");
    scrubThumb.className = "tl-scrub__thumb";
    const bubble = document.createElement("span");
    bubble.className = "tl-scrub__bubble";
    const ghost = document.createElement("span");
    ghost.className = "tl-scrub__ghost";
    scrub.appendChild(track);
    let H = { yhdr: 0, post: 0, postOne: 0, titleLine: 0, titleFont: "", titleWidth: 0, card: 0, title: 0, mhdr: 0, band: 0, year0: 0, end: 150 };
    function heights() {
      const probe = document.createElement("div");
      probe.className = `tl-probe tl tl--l${String(level)}`;
      probe.style.width = `${String(canvas.clientWidth)}px`;
      canvas.appendChild(probe);
      function measureHtml(r) {
        const box = document.createElement("div");
        box.className = `tl-row tl-row--${r.t}`;
        box.style.position = "static";
        box.innerHTML = html(r);
        probe.replaceChildren(box);
        const x = query(probe, "[data-x]", HTMLElement);
        if (x) x.textContent = "wwww ".repeat(59);
        return box.offsetHeight;
      }
      const sample = { id: 0, title: "Wwww ".repeat(29), cat: "blog", era: "now", n: 9, locked: false, thumb: "", url: "#", y: 2013, m: 0, d: 1, date: "2013-01-01", t: 0 };
      posts.push(sample);
      const p = posts.length - 1;
      const yhdr = measureHtml({ t: "yhdr", y: 2013, p, h: 0 });
      const full = measureHtml({ t: "post", p, h: 0 });
      const titleEl = query(probe, ".dm-tx__title", HTMLElement);
      const tc = titleEl ? getComputedStyle(titleEl) : null;
      const h = {
        yhdr,
        post: full,
        postOne: full,
        titleLine: 0,
        titleFont: tc ? `${tc.fontWeight} ${tc.fontSize} ${tc.fontFamily}` : "",
        titleWidth: titleEl ? titleEl.clientWidth : 0,
        card: measureHtml({ t: "card", p, h: 0 }),
        title: measureHtml({ t: "title", p, h: 0 }),
        mhdr: measureHtml({ t: "mhdr", y: 2013, m: 0, p, h: 0 }),
        band: measureHtml({ t: "band", y: 2013, m: 0, p, to: p + 1, h: 0 }),
        year0: measureHtml({ t: "year0", y: 2013, p, to: p + 1, months: [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], top: p, h: 0 }),
        end: 150
      };
      sample.title = "W";
      h.postOne = measureHtml({ t: "post", p, h: 0 });
      h.titleLine = tc && h.post > h.postOne ? orIfZero(parseFloat(tc.lineHeight), 0) : 0;
      posts.pop();
      probe.remove();
      if (level === 4 && head.length > 0) {
        const hs = head.map((a) => a.offsetHeight);
        const first = hs[0];
        if (h.titleLine === 0 && first !== void 0 && hs.every((v) => v === first)) h.post = first;
      }
      return h;
    }
    let ctx2d = null;
    function titleLines(p) {
      ctx2d ?? (ctx2d = document.createElement("canvas").getContext("2d"));
      if (!ctx2d) return 1;
      const c2 = ctx2d;
      c2.font = H.titleFont;
      const max = H.titleWidth;
      let lines = 1, x = p.locked ? 19 : 0;
      const space = c2.measureText(" ").width;
      for (const word of p.title.split(/\s+/)) {
        const w = c2.measureText(word).width;
        if (w > max) {
          for (const ch of word) {
            const cw = c2.measureText(ch).width;
            if (x + cw > max) {
              lines++;
              x = 0;
            }
            x += cw;
          }
          x += space;
          continue;
        }
        if (x > 0 && x + w > max) {
          lines++;
          x = 0;
        }
        x += w + space;
      }
      return Math.min(3, lines);
    }
    function postHeight(k) {
      return H.titleLine > 0 ? H.postOne + (titleLines(post(k)) - 1) * H.titleLine : H.post;
    }
    function postLevel() {
      return level === 4 && head.length > 0;
    }
    function build() {
      root.className = `tl tl--l${String(level)}`;
      ui.setAttribute("data-level", String(level));
      feed2.classList.toggle("tl-zoomed", !postLevel());
      const h = H = heights();
      rows = [];
      postRow = new Array(posts.length);
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
          rows.push({ t: "year0", y, p: i, to: j, months, top, h: h.year0 });
          for (let k = i; k < j; k++) postRow[k] = rows.length - 1;
        } else {
          if (!(i > 0 && i === start && post(i - 1).y === y)) rows.push({ t: "yhdr", y, p: i, h: h.yhdr });
          let a = i;
          while (a < j) {
            const m = post(a).m;
            let b = a;
            while (b < j && post(b).m === m) b++;
            if (level === 1) {
              rows.push({ t: "band", y, m, p: a, to: b, h: h.band });
              for (let k = a; k < b; k++) postRow[k] = rows.length - 1;
            } else {
              if (level === 2) rows.push({ t: "mhdr", y, m, p: a, h: h.mhdr });
              for (let k = a; k < b; k++) {
                rows.push(level === 2 ? { t: "title", p: k, h: h.title } : level === 3 ? { t: "card", p: k, h: h.card } : { t: "post", p: k, h: postHeight(k) });
                postRow[k] = rows.length - 1;
              }
            }
            a = b;
          }
        }
        i = j;
      }
      rows.push({ t: "end", p: posts.length - 1, h: h.end });
      offs = [0];
      let sum = 0;
      for (const r of rows) {
        sum += r.h;
        offs.push(sum);
      }
      total = sum;
      canvas.style.height = `${String(total)}px`;
      label.textContent = LEVELS[level];
      btnOut.disabled = level === 0;
      btnIn.disabled = level === 4;
      for (const n of nodes.values()) n.remove();
      nodes.clear();
      measure();
    }
    function rowAt(y) {
      let lo = 0, hi = rows.length - 1;
      while (lo < hi) {
        const mid = lo + hi + 1 >> 1;
        if (off(mid) <= y) lo = mid;
        else hi = mid - 1;
      }
      return lo;
    }
    function postTop(i) {
      if (postLevel() && i < head.length) return docTop(headEl(i));
      return canvasTop + off(postRow[i] ?? 0);
    }
    function postH(i) {
      if (postLevel() && i < head.length) return headEl(i).offsetHeight;
      return rows[postRow[i] ?? -1]?.h ?? 0;
    }
    const LOCK = '<svg class="tl-lock" viewBox="0 0 24 24" width="13" height="13" aria-label="Locked" role="img"><rect x="5" y="11" width="14" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
    function catLabel(p) {
      if (p.cat === "") return "";
      const name = p.cat.charAt(0).toUpperCase() + p.cat.slice(1);
      return `<span class="dm-cat" data-cat="${esc(p.cat)}">${esc(name)}</span>`;
    }
    function title(p) {
      return (p.locked ? LOCK : "") + esc(p.title);
    }
    function excerpt(p) {
      if (p.locked || p.id === 0) return "";
      const x = excerpts.get(p.y);
      if (!x) {
        want(p.y);
        return "";
      }
      return esc(x.get(p.id) ?? "");
    }
    function signal(n) {
      const bars = n > 0 ? Math.min(5, Math.max(1, Math.round(Math.log(n + 1) / Math.LN2))) : 0;
      let s = "";
      for (let i = 0; i < 5; i++) s += `<b class="${i < bars ? "on" : ""}" style="height:${String(4 + i * 3)}px"></b>`;
      return s;
    }
    function frame(p, cls) {
      if (p.locked) return `<div class="${cls} tl-locked"><span>${LOCK} LOCKED</span></div>`;
      if (p.thumb === "") return `<div class="${cls} dm-tx__nosignal"><span>NO SIGNAL</span></div>`;
      return `<div class="${cls} dm-media"><img class="dm-media__img" src="${esc(p.thumb)}" alt="" loading="lazy" decoding="async"></div>`;
    }
    function html(r) {
      var _a;
      const p = post(r.p);
      switch (r.t) {
        case "year0": {
          const max = orIfZero(Math.max(...r.months), 1);
          const eras = [];
          for (let q2 = r.p; q2 < r.to; q2++) {
            const e = post(q2);
            eras[_a = e.m] ?? (eras[_a] = e.era);
          }
          let era = post(r.to - 1).era;
          const bars = r.months.map((n, m) => {
            era = eras[m] ?? era;
            return `<i data-era="${esc(era)}" style="--h:${(n / max).toFixed(3)}" title="${mon(m)} ${String(r.y)}: ${String(n)}"></i>`;
          }).join("");
          const count = r.to - r.p;
          return `<button type="button" class="tl-y0" data-zoom="1" data-p="${String(r.p)}"><span class="tl-y0__year">${String(r.y)}</span><span class="tl-y0__spark" aria-hidden="true">${bars}</span><span class="tl-y0__meta"><b>${String(count)}</b> ${count === 1 ? "post" : "posts"}<br><span>Most replies: ${esc(post(r.top).title)}</span></span></button>`;
        }
        case "yhdr":
          return `<div class="dm-tx-year tl-yhdr" data-era="${esc(p.era)}"><a href="/${String(r.y)}/">${String(r.y)}</a><i aria-hidden="true"></i></div>`;
        case "mhdr":
          return `<div class="tl-mhdr"><span>${mon(r.m).toUpperCase()} ${String(r.y)}</span><i aria-hidden="true"></i></div>`;
        case "band": {
          const titles = [];
          for (let k = r.p; k < r.to && titles.length < 3; k++) titles.push(title(post(k)));
          const n = r.to - r.p;
          return `<button type="button" class="tl-band" data-zoom="2" data-era="${esc(p.era)}" data-p="${String(r.p)}"><span class="tl-band__m">${mon(r.m).toUpperCase()}</span><span class="tl-band__bar" style="--w:${Math.min(1, n / 60).toFixed(3)}"></span><span class="tl-band__n">${String(n)}</span><span class="tl-band__titles">${titles.join('<span class="dm-sep"> · </span>')}</span></button>`;
        }
        case "title":
          return `<a class="tl-title" data-era="${esc(p.era)}" href="${esc(p.url)}"><time>${pad(p.d)}</time><span class="tl-dot" data-cat="${esc(p.cat)}"></span><span class="tl-title__t">${title(p)}</span>` + (p.n > 0 ? `<span class="tl-title__n">${String(p.n)}</span>` : "") + "</a>";
        case "card":
          return `<article class="tl-card" data-era="${esc(p.era)}"><div class="tl-card__rail"><span class="tl-card__d">${pad(p.d)}</span><span class="tl-card__m">${mon(p.m).toUpperCase()}</span></div><a class="tl-card__frame" href="${esc(p.url)}" tabindex="-1" aria-hidden="true">${frame(p, "tl-card__media")}</a><div class="tl-card__body"><p class="dm-tx__cat">${catLabel(p)}</p><h3 class="tl-card__title"><a href="${esc(p.url)}">${title(p)}</a></h3><p class="tl-card__ex" data-x="${String(p.id)}">${excerpt(p)}</p></div></article>`;
        case "post": {
          const ja = JA[p.cat];
          return `<article class="dm-tx tl-post" data-era="${esc(p.era)}"><div class="dm-tx__rail"><time datetime="${p.date}"><span class="dm-tx__day">${pad(p.d)}</span><span class="dm-tx__mon">${mon(p.m).toUpperCase()}</span></time><span class="dm-tx__no">TX·${String(p.id)}</span></div><a class="dm-tx__frame" href="${esc(p.url)}" tabindex="-1" aria-hidden="true">${frame(p, "dm-tx__media")}<span class="dm-tx__scan"></span><span class="dm-tx__rec">REC ${p.date.replace(/-/g, ".")}</span></a><div class="dm-tx__body"><p class="dm-tx__cat">${catLabel(p)}${ja !== void 0 ? `<span class="dm-tx__ja" lang="ja">${ja}</span>` : ""}</p><h2 class="dm-tx__title"><a href="${esc(p.url)}">${title(p)}</a></h2><p class="dm-tx__excerpt" data-x="${String(p.id)}">${excerpt(p)}</p><p class="dm-tx__meta"><span class="dm-tx__sig" aria-hidden="true">${signal(p.n)}</span><a href="${esc(p.url)}#comments">${p.n > 0 ? `${String(p.n)}${p.n === 1 ? " reply" : " replies"}` : "No replies"}</a></p></div></article>`;
        }
        case "end":
          return '<div class="tl-end"><b>2003.04.01</b> “This blog is live!” The first transmission. That’s everything.</div>';
      }
    }
    function want(y) {
      if (asked.has(y)) return;
      asked.add(y);
      const sep = api2.includes("?") ? "/excerpts&year=" : "/excerpts?year=";
      fetchJson(api2 + sep + String(y)).then((data) => {
        const x = parseExcerpts(data);
        excerpts.set(y, x);
        for (const el of queryAll(canvas, "[data-x]", HTMLElement)) {
          const text = x.get(Number(el.getAttribute("data-x")));
          if (text !== void 0 && el.textContent === "") el.textContent = text;
        }
      }).catch(() => {
        asked.delete(y);
      });
    }
    function measure() {
      canvasTop = docTop(canvas);
    }
    function render() {
      queued = false;
      const top = window.scrollY - canvasTop;
      const vh = window.innerHeight;
      const a = rowAt(Math.max(0, top - vh));
      const b = top + vh * 2 < 0 ? -1 : rowAt(top + vh * 2);
      for (const [i, n] of nodes) {
        if (i < a || i > b) {
          n.remove();
          nodes.delete(i);
        }
      }
      const frag = document.createDocumentFragment();
      for (let i = a; i <= b; i++) {
        if (nodes.has(i)) continue;
        const r = row(i);
        const el = document.createElement("div");
        el.className = `tl-row tl-row--${r.t}`;
        el.style.height = `${String(r.h)}px`;
        el.style.transform = `translateY(${String(off(i))}px)`;
        el.innerHTML = html(r);
        if (r.t !== "yhdr" && r.t !== "mhdr" && r.t !== "end" && post(r.p).id === highlightId) el.classList.add("is-hit");
        nodes.set(i, el);
        frag.appendChild(el);
      }
      canvas.appendChild(frag);
      updateUi();
    }
    function queue() {
      if (!queued) {
        queued = true;
        requestAnimationFrame(render);
      }
      clearTimeout(settleTimer);
      settleTimer = window.setTimeout(settle, 350);
      flag("is-scrolling", true);
    }
    function postAt(y) {
      if (postLevel() && y < canvasTop) {
        for (let s = head.length - 1; s > 0; s--) if (docTop(headEl(s)) <= y) return s;
        return 0;
      }
      return rows[rowAt(Math.max(0, Math.min(total - 1, y - canvasTop)))]?.p ?? 0;
    }
    function centerPost() {
      return postAt(window.scrollY + window.innerHeight / 2);
    }
    function scrollToPost(i, screenY) {
      window.scrollTo(0, Math.max(0, postTop(i) - screenY));
    }
    function fade(fn) {
      if (reduce) {
        fn();
        return;
      }
      const els = [log2, canvas];
      const set = (transition, opacity) => {
        for (const e of els) {
          e.style.transition = transition;
          e.style.opacity = opacity;
        }
      };
      set("opacity 0.09s ease-in", "0");
      setTimeout(() => {
        fn();
        set("none", "0");
        canvas.getBoundingClientRect();
        set("opacity 0.18s ease-out", "");
      }, 90);
    }
    let morphing = null;
    function snap() {
      return { rows, offs, postRow, top: canvasTop, scroll: window.scrollY, head: postLevel() };
    }
    function slice(L, k) {
      if (L.head && k < head.length) {
        const a = headEl(k);
        return { top: docTop(a) - L.scroll, h: a.offsetHeight };
      }
      const i = L.postRow[k];
      const r = i === void 0 ? void 0 : L.rows[i];
      if (i === void 0 || !r) return null;
      const y = L.top + (L.offs[i] ?? 0) - L.scroll;
      if ((r.t === "band" || r.t === "year0") && r.to - r.p > 1) {
        const h = r.h / (r.to - r.p);
        return { top: y + h * (k - r.p), h };
      }
      return { top: y, h: r.h };
    }
    function toward(L, r, top, h, vh, lo, hi) {
      const a = slice(L, r.p);
      if (!a) return null;
      const single = r.t === "yhdr" || r.t === "mhdr" || r.t === "end";
      const last = r.t === "band" || r.t === "year0" ? slice(L, r.to - 1) : null;
      const b = last ?? a;
      const y = single ? a.top - (r.t === "end" ? 0 : h) : a.top;
      const s = single ? 1 : Math.max(lo, Math.min(hi, (b.top + b.h - a.top) / h));
      return { d: Math.max(-1.2 * vh, Math.min(1.2 * vh, y - top)), s };
    }
    function endMorph() {
      if (!morphing) return;
      for (const a of morphing.anims) a.cancel();
      morphing.ov.remove();
      morphing = null;
    }
    function morph(fn) {
      endMorph();
      const vh = window.innerHeight;
      const A = snap();
      const ov = document.createElement("div");
      ov.className = `${root.className} tl-morph`;
      ov.setAttribute("aria-hidden", "true");
      const ghosts = [];
      for (const [i, el] of nodes) {
        const b = el.getBoundingClientRect();
        const r = A.rows[i];
        if (b.bottom < 0 || b.top > vh || !r) continue;
        nodes.delete(i);
        el.style.left = `${String(b.left)}px`;
        el.style.width = `${String(b.width)}px`;
        el.style.right = "auto";
        el.style.transform = `translateY(${String(b.top)}px)`;
        el.classList.remove("is-hit");
        ov.appendChild(el);
        ghosts.push({ el, r, top: b.top, h: b.height });
      }
      if (A.head) {
        const wrap = document.createElement("ol");
        wrap.className = log2.className;
        wrap.style.cssText = "position:absolute;inset:0;margin:0;display:block!important";
        ov.appendChild(wrap);
        head.forEach((_, k) => {
          const li = headEl(k);
          const b = li.getBoundingClientRect();
          if (b.bottom < 0 || b.top > vh) return;
          const c = li.cloneNode(true);
          if (!(c instanceof HTMLElement)) return;
          c.style.cssText = `position:absolute;margin:0;top:0;left:${String(b.left)}px;width:${String(b.width)}px;transform:translateY(${String(b.top)}px)`;
          wrap.appendChild(c);
          ghosts.push({ el: c, r: { t: "post", p: k, h: b.height }, top: b.top, h: b.height });
        });
      }
      document.body.appendChild(ov);
      fn();
      const B = snap();
      const anims = [];
      const ease = "cubic-bezier(.2,.75,.25,1)";
      for (const g of ghosts) {
        const t = toward(B, g.r, g.top, g.h, vh, 0.2, 2.5);
        g.el.style.transformOrigin = "0 0";
        const base = `translateY(${String(g.top)}px)`;
        const end = t ? `${base} translateY(${String(t.d)}px) scaleY(${String(t.s)})` : base;
        anims.push(g.el.animate([{ transform: base, opacity: 1 }, { transform: end, opacity: 0 }], { duration: 340, easing: ease, fill: "forwards" }));
      }
      const enter = (el, r, top, h, base) => {
        if (top + h < 0 || top > vh) return;
        const t = toward(A, r, top, h, vh, 0.35, 2);
        if (!t) return;
        el.style.transformOrigin = "0 0";
        const delay = Math.round(Math.min(1, Math.abs(top + h / 2 - vh / 2) / vh) * 90);
        anims.push(el.animate([
          { transform: `${base} translateY(${String(t.d)}px) scaleY(${String(t.s)})`, opacity: 0 },
          { opacity: 1, offset: 0.55 },
          { transform: `${base} translateY(0) scaleY(1)`, opacity: 1 }
        ], { duration: 480, delay, easing: ease, fill: "backwards" }));
      };
      for (const [i, el] of nodes) {
        const r = B.rows[i];
        const o = B.offs[i];
        if (r && o !== void 0) enter(el, r, B.top + o - B.scroll, r.h, `translateY(${String(o)}px)`);
      }
      if (B.head) {
        head.forEach((_, k) => {
          const li = headEl(k);
          const b = li.getBoundingClientRect();
          enter(li, { t: "post", p: k, h: b.height }, b.top, b.height, "");
        });
      }
      const m = morphing = { ov, anims };
      void Promise.all(anims.map((a) => a.finished.catch(() => null))).then(() => {
        if (morphing === m) {
          ov.remove();
          morphing = null;
        }
      });
    }
    function zoom(toRaw, anchorIn) {
      const to = toLevel(toRaw);
      let anchor = anchorIn;
      if (to === level && anchor === void 0) return;
      let i = anchor ?? centerPost();
      if (anchor === void 0 && focus >= 0 && postRow[focus] === postRow[i] && !(postLevel() && i < head.length)) i = focus;
      else if (anchor !== void 0 && focus >= 0 && postRow[focus] === postRow[anchor]) i = anchor = focus;
      focus = i;
      let screenY = postTop(i) - window.scrollY;
      let frac = (window.innerHeight / 2 - screenY) / orIfZero(postH(i), 1);
      if (anchor !== void 0) {
        screenY = window.innerHeight * 0.3;
        frac = 0;
      }
      const target = i;
      const apply = () => {
        level = to;
        build();
        const y = anchor !== void 0 ? screenY : window.innerHeight / 2 - Math.max(0.05, Math.min(0.95, frac)) * postH(target);
        scrollToPost(target, y);
        render();
        settle();
      };
      if (reduce) apply();
      else morph(apply);
    }
    function random() {
      const pool = [];
      posts.forEach((p, i) => {
        if (!p.locked) pool.push(i);
      });
      const pick = pool[Math.floor(Math.random() * pool.length)];
      if (pick === void 0) return;
      highlightId = post(pick).id;
      const to = level === 3 ? 3 : 4;
      fade(() => {
        if (to !== level) {
          level = to;
          build();
        }
        for (const n of nodes.values()) n.remove();
        nodes.clear();
        scrollToPost(pick, window.innerHeight * 0.28);
        render();
        if (postLevel() && pick < head.length) headEl(pick).classList.add("is-hit");
        settle();
      });
      setTimeout(() => {
        highlightId = 0;
        for (const n of queryAll(document, ".is-hit", Element)) n.classList.remove("is-hit");
      }, 2400);
    }
    function step(dir) {
      const i = Math.max(0, Math.min(posts.length - 1, centerPost() + dir));
      scrollToPost(i, window.innerHeight / 2 - postH(i) / 2);
    }
    const GAP = 0.03;
    const SILENCE = 3 * 365.25 * 864e5;
    const spans = [];
    {
      const runs = [];
      let from = post(0).t;
      let prev = from;
      for (let i = 1; i < posts.length; i++) {
        const t = post(i).t;
        if (prev - t >= SILENCE) {
          runs.push([from, prev]);
          from = t;
        }
        prev = t;
      }
      runs.push([from, prev]);
      const total2 = Math.max(1, runs.reduce((sum, [a, b]) => sum + a - b, 0));
      const room = 1 - GAP * (runs.length - 1);
      let at2 = 0;
      for (const [a, b] of runs) {
        const len = (a - b) / total2 * room;
        spans.push({ from: a, to: b, at: at2, len });
        at2 += len + GAP;
      }
    }
    function railAt(t) {
      for (const sp of spans) {
        if (t >= sp.to) return sp.from === sp.to ? sp.at : sp.at + (sp.from - Math.min(t, sp.from)) / (sp.from - sp.to) * sp.len;
      }
      return 1;
    }
    function timeAt(f) {
      let prev = null;
      for (const sp of spans) {
        if (f < sp.at) return prev && f - (prev.at + prev.len) < sp.at - f ? prev.to : sp.from;
        if (f <= sp.at + sp.len) return sp.len > 0 ? sp.from - (f - sp.at) / sp.len * (sp.from - sp.to) : sp.from;
        prev = sp;
      }
      return prev ? prev.to : 0;
    }
    function postNear(t) {
      let lo = 0;
      let hi = posts.length - 1;
      while (lo < hi) {
        const mid = lo + hi >> 1;
        if (post(mid).t > t) lo = mid + 1;
        else hi = mid;
      }
      return lo > 0 && post(lo - 1).t - t < t - post(lo).t ? lo - 1 : lo;
    }
    function buildScrub() {
      let marks = "";
      const seen = /* @__PURE__ */ new Set();
      let last = -1;
      posts.forEach((p, i) => {
        if (seen.has(p.y)) return;
        seen.add(p.y);
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
      const pointAt = (e) => {
        const b = track.getBoundingClientRect();
        const f = Math.max(0, Math.min(1, (e.clientY - b.top) / b.height));
        const i = postNear(timeAt(f));
        const p = post(i);
        ghost.style.top = bubble.style.top = `${String(f * 100)}%`;
        if (ghost.getAttribute("data-era") !== p.era) ghost.setAttribute("data-era", p.era);
        bubble.textContent = `${mon(p.m)} ${String(p.y)}`;
        return i;
      };
      track.addEventListener("pointermove", (e) => {
        if (e.pointerType !== "mouse") return;
        scrub.classList.add("is-pointing");
        pointAt(e);
      });
      track.addEventListener("pointerleave", () => {
        scrub.classList.remove("is-pointing");
      });
      track.addEventListener("click", (e) => {
        const y = closest(e.target, ".tl-scrub__y", HTMLElement);
        const yi = y ? Number(y.getAttribute("data-p")) : NaN;
        if (Number.isInteger(yi) && posts[yi]) {
          scrollToPost(yi, Math.max(90, window.innerHeight * 0.18));
          return;
        }
        const i = pointAt(e);
        scrollToPost(i, window.innerHeight / 2 - postH(i) / 2);
      });
    }
    function flag(name, on) {
      ui.classList.toggle(name, on);
      ctl.classList.toggle(name, on);
    }
    function headerBottom() {
      const hdr = query(document, ".dm-header", HTMLElement);
      return hdr ? Math.max(0, hdr.getBoundingClientRect().bottom) : 0;
    }
    function updateUi() {
      const i = centerPost();
      const p = post(i);
      const firstRow = postTop(Math.min(3, posts.length - 1));
      flag("is-deep", level !== 4 || window.scrollY + window.innerHeight / 2 > firstRow);
      flag("is-in", level !== 4 || docTop(log2) - window.scrollY < window.innerHeight * 0.4);
      flag("is-off", window.scrollY + window.innerHeight < docTop(postLevel() ? log2 : canvas));
      scrubThumb.style.top = `${String(railAt(p.t) * 100)}%`;
      if (scrubThumb.getAttribute("data-era") !== p.era) scrubThumb.setAttribute("data-era", p.era);
      const hb = headerBottom();
      const y = window.scrollY + hb + 8;
      const listTop = postLevel() ? docTop(log2) : canvasTop;
      let show = level !== 0 && y > listTop + 40;
      if (show) {
        const at2 = post(postAt(y));
        if (stickyLink.textContent !== String(at2.y)) {
          stickyLink.textContent = String(at2.y);
          stickyLink.href = `/${String(at2.y)}/`;
        }
        stickyYear.setAttribute("data-era", level === 1 ? "" : at2.era);
        stickyYear.style.top = `${String(hb)}px`;
        stickyYear.style.left = `${String(Math.round(root.getBoundingClientRect().left))}px`;
        const marks = [...queryAll(canvas, ".tl-yhdr, .dm-tx-year", Element), ...postLevel() ? queryAll(log2, ".dm-tx-year", Element) : []];
        for (const m of marks) {
          const b = m.getBoundingClientRect();
          if (b.bottom > hb - 10 && b.top < hb + stickyYear.offsetHeight + 24) show = false;
        }
      }
      stickyYear.classList.toggle("is-on", show);
    }
    function settle() {
      flag("is-scrolling", false);
      const url = new URL(location.href);
      const i = centerPost();
      if (level === 4 && (i < Math.min(head.length, 12) || window.scrollY + window.innerHeight / 2 < docTop(log2))) {
        url.searchParams.delete("at");
        url.searchParams.delete("z");
      } else {
        url.searchParams.set("at", post(i).date);
        url.searchParams.set("z", String(level));
      }
      if (url.href !== location.href) history.replaceState(history.state, "", url.href);
    }
    function placeControls() {
      const tools = query(document, ".dm-nav__tools", HTMLElement);
      const wide = window.matchMedia("(min-width: 1240px)");
      const place = () => {
        if (wide.matches && tools) {
          tools.insertBefore(ctl, tools.firstChild);
          ctl.classList.add("tl-ctl--nav");
        } else {
          ui.appendChild(ctl);
          ctl.classList.remove("tl-ctl--nav");
        }
      };
      place();
      wide.addEventListener("change", place);
      btnOut.addEventListener("click", () => {
        zoom(level - 1);
      });
      btnIn.addEventListener("click", () => {
        zoom(level + 1);
      });
      btnRand.addEventListener("click", random);
    }
    const q = new URLSearchParams(location.search);
    const z = q.get("z");
    if (z !== null) level = toLevel(orIfZero(parseInt(z, 10), 0));
    const at = q.get("at");
    placeControls();
    buildScrub();
    document.body.appendChild(ui);
    log2.after(root);
    feed2.classList.add("tl-host");
    build();
    if (at !== null) {
      let i = posts.findIndex((p) => p.date <= at);
      if (i < 0) i = posts.length - 1;
      scrollToPost(i, window.innerHeight / 2 - postH(i) / 2);
    }
    render();
    window.addEventListener("scroll", queue, { passive: true });
    let rt = 0;
    let lastW = window.innerWidth;
    window.addEventListener("resize", () => {
      if (window.innerWidth === lastW) {
        queue();
        return;
      }
      lastW = window.innerWidth;
      clearTimeout(rt);
      rt = window.setTimeout(() => {
        const i = centerPost();
        build();
        scrollToPost(i, window.innerHeight / 2 - postH(i) / 2);
        render();
      }, 150);
    });
    root.addEventListener("click", (e) => {
      const zb = closest(e.target, "[data-zoom]", HTMLElement);
      if (!zb) return;
      const toZ = Number(zb.getAttribute("data-zoom"));
      const p = Number(zb.getAttribute("data-p"));
      if (Number.isInteger(toZ) && Number.isInteger(p) && posts[p]) zoom(toZ, p);
    });
    document.addEventListener("keydown", (e) => {
      const t = e.target;
      if (e.metaKey || e.ctrlKey || e.altKey) return;
      if (t instanceof HTMLElement && (/^(input|textarea|select)$/i.test(t.tagName) || t.isContentEditable)) return;
      if (window.scrollY + window.innerHeight < docTop(postLevel() ? log2 : canvas)) return;
      const k = e.key;
      if (k === "+" || k === "=") zoom(level + 1);
      else if (k === "-" || k === "_") zoom(level - 1);
      else if (k === "r" || k === "R") random();
      else if (k === "j" || k === "J") step(1);
      else if (k === "k" || k === "K") step(-1);
      else return;
      e.preventDefault();
    });
    document.dispatchEvent(new CustomEvent("dm:timeline-ready", { detail: { posts: posts.length } }));
  }
  var cfg = window.dmTimeline;
  var api = str(cfg, "api", "");
  var tz = num(cfg, "tz", 0) * 1e3;
  var feed = query(document, ".dm-feed", HTMLElement);
  var log = feed && query(feed, ".dm-log", HTMLElement);
  if (api !== "" && feed && log) {
    fetchJson(api, { credentials: "same-origin" }).then(async (data) => {
      const posts = isArray(data) ? data.map((r) => parsePost(r, tz)).filter((p) => p !== null) : [];
      await document.fonts.ready;
      if (posts.length > 0) run(api, feed, log, posts);
    }).catch(() => {
    });
  }
})();
