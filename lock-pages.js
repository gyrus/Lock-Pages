
jQuery( document ).ready( function($) {

	// Hide certain publishing controls
	if ( $( 'body' ).hasClass( 'page-locked' ) ) {

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

});