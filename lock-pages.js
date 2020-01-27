
jQuery( document ).ready( function($) {

	var b  = $( 'body' );
	var ol = $( '#sltlp-optional-locks' );

	// Hide certain publishing controls if page is locked
	if ( b.hasClass( 'page-locked' ) ) {

		// Status and visibility
		if ( $( '#misc-publishing-actions' ).length ) {
			$( '#post-status-select' ).siblings( 'a' ).remove().end().remove();
			$( '#post-visibility-select' ).siblings( 'a' ).remove().end().remove();
		}

		// Page parent
		var p = $( '#parent_id' );
		if ( p.length ) {
			var parent = p.find( 'option:selected' ).text();
			p.replaceWith( '<p>' + parent + '</p>' );
		}

		// Page template
		var pt = $( '#page_template' );
		if ( pt.length ) {
			var template = pt.find( 'option:selected' ).text();
			pt.replaceWith( '<p>' + template + '</p>' );
		}

	}

	// Title locked?
	if ( b.hasClass( 'page-title-locked' ) ) {
		$( '#title' ).attr( 'readonly', true );
	}

	// Show / hide options locks
	b.on( 'change', '#slt_lockpages_locked', function() {
		var el = $( this );
		if ( el.is( ':checked' ) ) {
			ol.slideDown();
		} else {
			ol.slideUp();
		}
	});

});