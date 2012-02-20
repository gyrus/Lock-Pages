jQuery( document ).ready( function($) {
	// Hide certain publishing controls
	if ( $( 'body' ).hasClass( 'page-locked' ) ) {
		if ( $( 'div#misc-publishing-actions' ).length && $.inArray( 'status', slt_lockpages_locked_items ) != -1 ) {
			$( 'div#misc-publishing-actions div#post-status-select' ).siblings( 'a' ).remove();
			$( 'div#misc-publishing-actions div#post-status-select' ).remove();
			$( 'div#misc-publishing-actions div#post-visibility-select' ).siblings( 'a' ).remove();
			$( 'div#misc-publishing-actions div#post-visibility-select' ).remove();
		}
		if ( $( 'select#page_template' ).length && $.inArray( 'template', slt_lockpages_locked_items ) != -1 ) {
			var template = $( 'select#page_template option:selected' ).text();
			$( 'select#page_template' ).replaceWith( '<p>' + template + '</p>' );
		}
		if ( $( 'select#parent_id' ).length && $.inArray( 'parent', slt_lockpages_locked_items ) != -1 ) {
			var parent = $( 'select#parent_id option:selected' ).text();
			$( 'select#parent_id' ).replaceWith( '<p>' + parent + '</p>' );
		}
	}
});