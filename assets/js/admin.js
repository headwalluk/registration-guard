/**
 * Registration Guard — Admin Tab Navigation
 *
 * Hash-based tab switching for the settings page.
 * Supports browser back/forward and deep linking.
 *
 * @package Registration_Guard
 * @since 1.0.0
 */

( function () {
	'use strict';

	var defaultTab = 'settings';

	function activateTab( tabName ) {
		var tabs = document.querySelectorAll( '.nav-tab[data-tab]' );
		var panels = document.querySelectorAll( '.regguard-tab-panel' );
		var i;

		for ( i = 0; i < tabs.length; i++ ) {
			tabs[ i ].classList.remove( 'nav-tab-active' );
		}

		for ( i = 0; i < panels.length; i++ ) {
			panels[ i ].style.display = 'none';
		}

		var activeTab = document.querySelector( '[data-tab="' + tabName + '"]' );
		var activePanel = document.getElementById( tabName + '-panel' );

		if ( activeTab ) {
			activeTab.classList.add( 'nav-tab-active' );
		}

		if ( activePanel ) {
			activePanel.style.display = 'block';
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var hash = window.location.hash.substring( 1 ) || defaultTab;
		activateTab( hash );

		var tabs = document.querySelectorAll( '.nav-tab[data-tab]' );
		for ( var i = 0; i < tabs.length; i++ ) {
			tabs[ i ].addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var tabName = this.getAttribute( 'data-tab' );
				window.location.hash = tabName;
				activateTab( tabName );
			} );
		}

		window.addEventListener( 'hashchange', function () {
			var tabName = window.location.hash.substring( 1 ) || defaultTab;
			activateTab( tabName );
		} );
	} );
} )();
