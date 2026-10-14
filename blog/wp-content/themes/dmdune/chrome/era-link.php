<?php
/**
 * The era a page is shown in comes from its address: ?era=blocks (the theme's name; no parameter,
 * or "dune", for today's). chrome.js carries the parameter along as the reader clicks around in
 * the same tab, so an era sticks for that tab only; a page opened from anywhere else, or in a new
 * tab, starts in today's design. The canonical URL stays without the parameter, so search engines
 * still see one page.
 *
 * Until 1.17 the choice was a year-long cookie shared by every tab; it is ignored and cleared.
 *
 * @package dmdune
 */

/**
 * The era asked for in the address, as a skin key ('' for today's), or '' when there is none or it
 * isn't one of the eras.
 */
function dmc_requested_skin(): string {
	// Theme names rather than years, so the address doesn't carry a second, unrelated date.
	$want  = strtolower( trim( (string) ( $_GET['era'] ?? '' ) ) );
	$alias = array( 'block' => 'blocks', 'kubrickdm' => 'kubrick', 'dmbrick' => 'kubrick', 'now' => '', 'today' => '', 'dune' => '' );
	$want  = dmc_skin_key( $alias[ $want ] ?? $want );
	return isset( dmc_skins()[ $want ] ) ? $want : '';
}

if ( isset( $_COOKIE['dm-skin'] ) && ! headers_sent() ) {
	$host = (string) ( $_SERVER['HTTP_HOST'] ?? '' );
	setcookie(
		'dm-skin',
		'',
		array(
			'expires'  => time() - 3600,
			'path'     => '/',
			'domain'   => preg_match( '/(^|\.)darkmirage\.com$/', $host ) ? '.darkmirage.com' : '',
			'samesite' => 'Lax',
		)
	);
}
