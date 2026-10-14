/* Editor previews for the theme's server-rendered blocks. Titles and attributes come from the
   PHP registration in inc/blocks.php; this only gives each block an edit view that renders it on
   the server. WordPress's editor API arrives as the untyped global `wp`, so each piece is checked
   before use. */
import { at, isFn, isRecord } from './lib/data';

const wp = window.wp;
const el = at(wp, 'element.createElement');
const SSR = at(wp, 'serverSideRender');
const useBlockProps = at(wp, 'blockEditor.useBlockProps');
const getBlockType = at(wp, 'blocks.getBlockType');
const registerBlockType = at(wp, 'blocks.registerBlockType');

const SLUGS = [
  'site-header', 'site-footer', 'hero', 'card', 'tx', 'post-meta', 'byline', 'related', 'archive-head', 'row', 'channels',
  'year-strip', 'archive-index', 'most-discussed', 'not-found',
];

if (isFn(el) && isFn(useBlockProps) && isFn(getBlockType) && isFn(registerBlockType) && SSR !== undefined) {
  for (const slug of SLUGS) {
    const name = `dmdune/${slug}`;
    if (getBlockType(name) != null) continue;
    registerBlockType(name, {
      edit(props: unknown) {
        const postId = at(props, 'context.postId');
        return el('div', useBlockProps(), el(SSR, {
          block: name,
          attributes: isRecord(props) ? props['attributes'] : undefined,
          urlQueryArgs: typeof postId === 'number' || typeof postId === 'string' ? { post_id: postId } : {},
        }));
      },
      save() { return null; },
    });
  }
}
