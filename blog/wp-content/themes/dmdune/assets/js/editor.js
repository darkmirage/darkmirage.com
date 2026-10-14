/* Built from frontend/src by `npm run build`; edit the TypeScript there, not this file. */
"use strict";
(() => {
  // src/lib/data.ts
  function isRecord(v) {
    return typeof v === "object" && v !== null && !Array.isArray(v);
  }
  function isFn(v) {
    return typeof v === "function";
  }
  function at(obj, path) {
    let v = obj;
    for (const key of path.split(".")) {
      if (!(isRecord(v) || isFn(v))) return void 0;
      v = Reflect.get(v, key);
    }
    return v;
  }

  // src/editor.ts
  var wp = window.wp;
  var el = at(wp, "element.createElement");
  var SSR = at(wp, "serverSideRender");
  var useBlockProps = at(wp, "blockEditor.useBlockProps");
  var getBlockType = at(wp, "blocks.getBlockType");
  var registerBlockType = at(wp, "blocks.registerBlockType");
  var SLUGS = [
    "site-header",
    "site-footer",
    "hero",
    "card",
    "tx",
    "post-meta",
    "byline",
    "related",
    "archive-head",
    "row",
    "channels",
    "year-strip",
    "archive-index",
    "most-discussed",
    "not-found"
  ];
  if (isFn(el) && isFn(useBlockProps) && isFn(getBlockType) && isFn(registerBlockType) && SSR !== void 0) {
    for (const slug of SLUGS) {
      const name = `dmdune/${slug}`;
      if (getBlockType(name) != null) continue;
      registerBlockType(name, {
        edit(props) {
          const postId = at(props, "context.postId");
          return el("div", useBlockProps(), el(SSR, {
            block: name,
            attributes: isRecord(props) ? props["attributes"] : void 0,
            urlQueryArgs: typeof postId === "number" || typeof postId === "string" ? { post_id: postId } : {}
          }));
        },
        save() {
          return null;
        }
      });
    }
  }
})();
