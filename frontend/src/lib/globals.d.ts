// Globals the PHP side puts on the page (wp_localize_script) and WordPress's editor API. They are
// typed `unknown` on purpose: every read goes through a runtime check in lib/data.ts.
declare global {
  interface Window {
    dmdune?: unknown;
    dmTimeline?: unknown;
    wp?: unknown;
  }
}
export {};
