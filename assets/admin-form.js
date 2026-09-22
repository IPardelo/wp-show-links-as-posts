/* Wordpress Show Links as Posts — formulario de ligazón. */
jQuery( function ( $ ) {
	'use strict';

	var $idInput  = $( '#wpslap-image-id' );
	var $libPrev  = $( '#wpslap-library-preview' );
	var $remove   = $( '#wpslap-remove' );
	var $urlInput = $( '#wpslap-image-url' );
	var $urlPrev  = $( '#wpslap-url-preview' );
	var frame;

	function setPreview( $box, src ) {
		$box.empty();
		if ( src ) {
			$( '<img>', { src: src, alt: '' } ).appendTo( $box );
		}
	}

	// Mostrar só o bloque da orixe escollida.
	function toggleSources() {
		var current = $( 'input[name="wpslap_image_source"]:checked' ).val();
		$( '.wpslap-source' ).each( function () {
			$( this ).toggle( $( this ).data( 'source' ) === current );
		} );
	}
	$( 'input[name="wpslap_image_source"]' ).on( 'change', toggleSources );
	toggleSources();

	// Mediateca.
	$( '#wpslap-pick' ).on( 'click', function ( e ) {
		e.preventDefault();
		if ( ! frame ) {
			frame = wp.media( {
				title: wpslapForm.frameTitle,
				button: { text: wpslapForm.frameButton },
				library: { type: 'image' },
				multiple: false
			} );
			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				var src = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
				$idInput.val( att.id );
				setPreview( $libPrev, src );
				$remove.prop( 'hidden', false );
			} );
		}
		frame.open();
	} );

	$remove.on( 'click', function ( e ) {
		e.preventDefault();
		$idInput.val( '' );
		setPreview( $libPrev, '' );
		$remove.prop( 'hidden', true );
	} );

	// Vista previa da URL.
	$urlInput.on( 'change input', function () {
		var v = $.trim( $urlInput.val() );
		setPreview( $urlPrev, /^https?:\/\/.+/i.test( v ) ? v : '' );
	} );
} );
