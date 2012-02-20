jQuery( document ).ready( function($) {
	// Hide certain publishing controls
	if ( $( 'body' ).hasClass( 'page-locked' ) ) {
		if ( $( 'div#misc-publishing-actions' ).length ) {
			$( 'div#misc-publishing-actions div#post-status-select' ).siblings( 'a' ).remove();
			$( 'div#misc-publishing-actions div#post-status-select' ).remove();
			$( 'div#misc-publishing-actions div#post-visibility-select' ).siblings( 'a' ).remove();
			$( 'div#misc-publishing-actions div#post-visibility-select' ).remove();
		}
		if ( $( 'select#page_template' ).length ) {
			var template = $( 'select#page_template option:selected' ).text();
			$( 'select#page_template' ).replaceWith( '<p>' + template + '</p>' );
		}
	}
});