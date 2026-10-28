( function ( $ ) {
	'use strict';

	$( function () {
		const $field = $( '.ospg-gallery-field' );

		if ( ! $field.length || 'undefined' === typeof wp || ! wp.media ) {
			return;
		}

		const strings = window.ospgAdmin || {};
		const $input = $field.find( '.ospg-gallery-ids' );
		const $list = $field.find( '.ospg-gallery-list' );
		let frame = null;

		const sync = () => {
			const ids = [];

			$list.find( 'li' ).each( function () {
				ids.push( $( this ).attr( 'data-id' ) );
			} );

			$input.val( ids.join( ',' ) );
		};

		$list.sortable( { update: sync } );

		$field.on( 'click', '.ospg-gallery-add', function ( event ) {
			event.preventDefault();

			if ( ! frame ) {
				frame = wp.media( {
					title: strings.title,
					button: { text: strings.button },
					library: { type: 'image' },
					multiple: 'add',
				} );

				frame.on( 'select', function () {
					frame
						.state()
						.get( 'selection' )
						.each( function ( attachment ) {
							const data = attachment.toJSON();

							if ( $list.find( 'li[data-id="' + data.id + '"]' ).length ) {
								return;
							}

							const url = data.sizes && data.sizes.thumbnail ? data.sizes.thumbnail.url : data.url;

							$list.append(
								$( '<li/>', { 'data-id': data.id } )
									.append( $( '<img/>', { src: url, alt: '' } ) )
									.append(
										$( '<button/>', {
											type: 'button',
											class: 'ospg-gallery-remove',
											'aria-label': strings.remove,
											text: '\u00d7',
										} )
									)
							);
						} );

					sync();
				} );
			}

			frame.open();
		} );

		$field.on( 'click', '.ospg-gallery-remove', function () {
			$( this ).closest( 'li' ).remove();
			sync();
		} );
	} );
} )( jQuery );