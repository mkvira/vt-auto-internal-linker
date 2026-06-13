/* global vtailAdmin */
jQuery( function ( $ ) {
	var $form      = $( '#vtail-keyword-form-wrap' );
	var $formTitle = $( '#vtail-keyword-form-title' );
	var $table     = $( '#vtail-keywords-table tbody' );

	// --- Show form for new keyword ---
	$( '#vtail-add-keyword' ).on( 'click', function ( e ) {
		e.preventDefault();
		resetForm();
		$formTitle.text( vtailAdmin.i18n.addKeyword );
		$form.slideDown( 200 );
		scrollToForm();
	} );

	// --- Populate and show form for editing ---
	$( document ).on( 'click', '.vtail-edit-keyword', function ( e ) {
		e.preventDefault();
		var data = $( this ).data();
		populateForm( data );
		$formTitle.text( vtailAdmin.i18n.editKeyword );
		$form.slideDown( 200 );
		scrollToForm();
	} );

	// --- Cancel ---
	$( '#vtail-keyword-cancel' ).on( 'click', function ( e ) {
		e.preventDefault();
		$form.slideUp( 200 );
	} );

	// --- Save keyword via AJAX ---
	$( '#vtail-keyword-form' ).on( 'submit', function ( e ) {
		e.preventDefault();
		var $submit = $( '#vtail-keyword-submit' );
		$submit.val( $submit.data( 'saving' ) ).prop( 'disabled', true );

		$.post(
			vtailAdmin.ajaxUrl,
			$( this ).serialize() + '&nonce=' + vtailAdmin.nonce,
			function ( resp ) {
				$submit.val( $submit.data( 'original' ) ).prop( 'disabled', false );
				if ( ! resp.success ) {
					alert( ( resp.data && resp.data.message ) || vtailAdmin.i18n.error );
					return;
				}
				var keywordId = resp.data.keyword_id;
				var $existing = $( '#vtail-kw-row-' + keywordId );
				if ( $existing.length ) {
					$existing.replaceWith( resp.data.html );
				} else {
					$table.find( '.vtail-no-keywords' ).remove();
					$table.append( resp.data.html );
				}
				$form.slideUp( 200 );
				resetForm();
			}
		).fail( function () {
			$submit.val( $submit.data( 'original' ) ).prop( 'disabled', false );
			alert( vtailAdmin.i18n.error );
		} );
	} );

	// --- Delete keyword via AJAX ---
	$( document ).on( 'click', '.vtail-delete-keyword', function ( e ) {
		e.preventDefault();
		if ( ! window.confirm( vtailAdmin.i18n.confirmDelete ) ) {
			return;
		}
		var $btn       = $( this );
		var keywordId  = $btn.data( 'id' );
		$btn.prop( 'disabled', true );

		$.post(
			vtailAdmin.ajaxUrl,
			{ action: 'vtail_delete_keyword', nonce: vtailAdmin.nonce, keyword_id: keywordId },
			function ( resp ) {
				if ( resp.success ) {
					$( '#vtail-kw-row-' + keywordId ).fadeOut( 300, function () {
						$( this ).remove();
						if ( $table.find( 'tr' ).length === 0 ) {
							$table.append( '<tr class="vtail-no-keywords"><td colspan="9">' + vtailAdmin.i18n.noKeywords + '</td></tr>' );
						}
					} );
				} else {
					$btn.prop( 'disabled', false );
					alert( ( resp.data && resp.data.message ) || vtailAdmin.i18n.error );
				}
			}
		).fail( function () {
			$btn.prop( 'disabled', false );
			alert( vtailAdmin.i18n.error );
		} );
	} );

	function resetForm() {
		$( '#vtail-keyword-form' )[ 0 ].reset();
		$( '#vtail-kw-id' ).val( '0' );
		$( '#vtail-kw-max-per-post' ).val( '1' );
		$( '#vtail-kw-priority' ).val( '10' );
		$( '#vtail-kw-total-limit' ).val( '0' );
		$( '#vtail-kw-active' ).prop( 'checked', true );
	}

	function populateForm( data ) {
		$( '#vtail-kw-id' ).val( data.id || 0 );
		$( '#vtail-kw-keyword' ).val( data.keyword || '' );
		$( '#vtail-kw-max-per-post' ).val( data.maxPerPost || 1 );
		$( '#vtail-kw-priority' ).val( data.priority || 10 );
		$( '#vtail-kw-total-limit' ).val( data.totalLimit || 0 );
		$( '#vtail-kw-anchor' ).val( data.anchor || '' );
		$( '#vtail-kw-case-sensitive' ).prop( 'checked', data.caseSensitive == 1 );
		$( '#vtail-kw-nofollow' ).prop( 'checked', data.nofollow == 1 );
		$( '#vtail-kw-new-tab' ).prop( 'checked', data.newTab == 1 );
		$( '#vtail-kw-active' ).prop( 'checked', data.active == 1 );
	}

	function scrollToForm() {
		$( 'html, body' ).animate( { scrollTop: $form.offset().top - 60 }, 300 );
	}
} );
