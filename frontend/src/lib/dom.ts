// Typed DOM lookups that check what they find at runtime, so no cast is ever needed.

type Ctor<T> = abstract new (...args: never[]) => T;

export function query<T extends Element>(root: ParentNode, selector: string, type: Ctor<T>): T | null {
  const el = root.querySelector(selector);
  return el instanceof type ? el : null;
}

export function queryAll<T extends Element>(root: ParentNode, selector: string, type: Ctor<T>): T[] {
  const out: T[] = [];
  for (const el of root.querySelectorAll(selector)) if (el instanceof type) out.push(el);
  return out;
}

/** The nearest element matching `selector` from an event's target, if it is of `type`. */
export function closest<T extends Element>(target: EventTarget | null, selector: string, type: Ctor<T>): T | null {
  if (!(target instanceof Element)) return null;
  const el = target.closest(selector);
  return el instanceof type ? el : null;
}

export function isNode(target: EventTarget | null): target is Node {
  return target instanceof Node;
}

/** Cookies set on any darkmirage.com site apply to all of them. */
export function cookieDomain(): string {
  return /(^|\.)darkmirage\.com$/.test(location.hostname) ? '; domain=.darkmirage.com' : '';
}

export function onReady(fn: () => void): void {
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
  else fn();
}
