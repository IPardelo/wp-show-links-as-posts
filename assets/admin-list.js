/* Wordpress Show Links as Posts — botón «Engadir ligazón» na lista de entradas. */
( function () {
	'use strict';

	if ( typeof wpslapList === 'undefined' ) {
		return;
	}

	var addNew = document.querySelector( '.wrap .page-title-action' );
	if ( ! addNew ) {
		return;
	}

	var button = document.createElement( 'a' );
	button.href = wpslapList.url;
	button.className = 'page-title-action wpslap-add-link';
	button.textContent = wpslapList.label;

	addNew.insertAdjacentElement( 'afterend', button );
} )();
