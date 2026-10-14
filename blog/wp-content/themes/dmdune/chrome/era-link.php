<?php
/**
 * Shareable era links: ?era=blocks (the theme's name; "dune" for today's) opens the page in that era and keeps
 * it for the rest of the visit, exactly as picking it in the time machine would. The canonical
 * URL stays without the parameter, so search engines still see one page.
 *
 * @package dmdune
 */

if ( isset( $_GET['era'] ) && ! headers_sent() ) {
	// Theme names rather than years, so the address doesn't carry a second, unrelated date.
	$want  = strtolower( trim( (string) $_GET['era'] ) );
	$alias = array( 'block' => 'blocks', 'kubrickdm' => 'kubrick', 'dmbrick' => 'kubrick', 'now' => '', 'today' => '', 'dune' => '' );
	$want  = $alias[ $want ] ?? $want;
	$skin  = ( '' === $want || isset( dmc_skins()[ $want ] ) ) ? $want : null;
	if ( null !== $skin ) {
		$host = (string) ( $_SERVER['HTTP_HOST'] ?? '' );
		setcookie(
			'dm-skin',
			$skin,
			array(
				'expires'  => $skin ? time() + 31536000 : time() - 3600,
				'path'     => '/',
				'domain'   => preg_match( '/(^|\.)darkmirage\.com$/', $host ) ? '.darkmirage.com' : '',
				'samesite' => 'Lax',
			)
		);
		$_COOKIE['dm-skin'] = $skin;
	}
}
