/**
 * Registration Guard — Nonce Challenge
 *
 * Fetches a time-delayed nonce via AJAX and injects it into
 * the registration form hidden field. Bots that POST directly
 * without loading the page will not have a valid nonce.
 *
 * @package Registration_Guard
 * @since 1.0.0
 */

( function () {
	'use strict';

	var config = window.regguardNonce || {};
	var ajaxUrl = config.ajaxUrl || '';
	var action = config.action || '';
	var fieldName = config.fieldName || '';
	var delay = ( parseInt( config.delay, 10 ) || 1 ) * 1000;

	if ( ! ajaxUrl || ! action || ! fieldName ) {
		return;
	}

	function fetchNonce() {
		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', ajaxUrl, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
		xhr.onreadystatechange = function () {
			if ( xhr.readyState !== 4 ) {
				return;
			}

			if ( xhr.status !== 200 ) {
				return;
			}

			var response;
			try {
				response = JSON.parse( xhr.responseText );
			} catch ( e ) {
				return;
			}

			if ( ! response.success || ! response.data || ! response.data.nonce ) {
				return;
			}

			var fields = document.querySelectorAll( 'input[name="' + fieldName + '"]' );
			for ( var i = 0; i < fields.length; i++ ) {
				fields[ i ].value = response.data.nonce;
			}
		};
		xhr.send( 'action=' + encodeURIComponent( action ) );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		setTimeout( fetchNonce, delay );
	} );
} )();
