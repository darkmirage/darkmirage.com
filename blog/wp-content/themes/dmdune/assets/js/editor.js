/* Editor previews for the theme's server-rendered blocks (no build step needed).
   Titles and attributes come from the PHP registration in inc/blocks.php. */
(function (wp) {
	var el = wp.element.createElement;
	var SSR = wp.serverSideRender;
	var useBlockProps = wp.blockEditor.useBlockProps;
	[
		'site-header', 'site-footer', 'hero', 'card', 'tx', 'post-meta', 'byline', 'related', 'archive-head',
		'year-strip', 'archive-index', 'most-discussed', 'not-found'
	].forEach(function (slug) {
		var name = 'dmdune/' + slug;
		if (wp.blocks.getBlockType(name)) return;
		wp.blocks.registerBlockType(name, {
			edit: function (props) {
				return el('div', useBlockProps(), el(SSR, {
					block: name,
					attributes: props.attributes,
					urlQueryArgs: props.context && props.context.postId ? { post_id: props.context.postId } : {}
				}));
			},
			save: function () { return null; }
		});
	});
})(window.wp);
