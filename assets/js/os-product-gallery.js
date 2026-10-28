( function ( $ ) {
	'use strict';

	var settings = window.ospgData || {};
	var i18n = settings.i18n || {};
	var hoverCache = {};

	/**
	 * POST to admin-ajax.php.
	 *
	 * @param {string} action Ajax action.
	 * @param {Object} data   Request data.
	 * @return {jqXHR}
	 */
	function request( action, data ) {
		return $.ajax( {
			url: settings.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: $.extend( { action: action, nonce: settings.nonce }, data ),
		} );
	}

	/**
	 * Get the hover image for a product. One request per product, cached
	 * (including the "no second image" result).
	 *
	 * @param {string} id Product ID.
	 * @return {Promise} Resolves with {url, alt} or null.
	 */
	function getHoverImage( id ) {
		if ( ! hoverCache[ id ] ) {
			hoverCache[ id ] = request( 'ospg_get_hover_image', { product_id: id } ).then(
				function ( response ) {
					return response && response.success && response.data.url ? response.data : null;
				},
				function () {
					delete hoverCache[ id ]; // Allow a retry after a network error.
					return $.Deferred().resolve( null ).promise();
				}
			);
		}

		return hoverCache[ id ];
	}

	/* ------------------------------------------------------------------
	 * Popup gallery
	 * ---------------------------------------------------------------- */
	function Lightbox() {
		this.$root = null;
		this.images = [];
		this.index = 0;
		this.token = 0;
		this.$trigger = null;
		this.onKey = this.onKey.bind( this );
	}

	Lightbox.prototype.mount = function () {
		var self = this;

		if ( this.$root ) {
			return;
		}

		this.$root = $(
			'<div class="ospg-popup" role="dialog" aria-modal="true" aria-hidden="true">' +
				'<div class="ospg-popup__overlay" data-ospg-close></div>' +
				'<div class="ospg-popup__dialog">' +
				'<button type="button" class="ospg-popup__close" data-ospg-close>&times;</button>' +
				'<div class="ospg-popup__stage">' +
				'<button type="button" class="ospg-popup__nav" data-ospg-prev>&#8249;</button>' +
				'<div class="ospg-popup__media"></div>' +
				'<button type="button" class="ospg-popup__nav" data-ospg-next>&#8250;</button>' +
				'</div>' +
				'<div class="ospg-popup__thumbs"></div>' +
				'<h3 class="ospg-popup__title"></h3>' +
				'<div class="ospg-popup__desc"></div>' +
				'<div class="ospg-popup__video"></div>' +
				'</div>' +
				'</div>'
		).appendTo( document.body );

		this.$close = this.$root.find( '.ospg-popup__close' ).attr( 'aria-label', i18n.close || 'Close' );
		this.$prev = this.$root.find( '[data-ospg-prev]' ).attr( 'aria-label', i18n.prev || 'Previous image' );
		this.$next = this.$root.find( '[data-ospg-next]' ).attr( 'aria-label', i18n.next || 'Next image' );
		this.$media = this.$root.find( '.ospg-popup__media' );
		this.$thumbs = this.$root.find( '.ospg-popup__thumbs' );
		this.$title = this.$root.find( '.ospg-popup__title' );
		this.$desc = this.$root.find( '.ospg-popup__desc' );
		this.$video = this.$root.find( '.ospg-popup__video' );

		this.$root
			.on( 'click', '[data-ospg-close]', function () {
				self.close();
			} )
			.on( 'click', '[data-ospg-prev]', function () {
				self.show( self.index - 1 );
			} )
			.on( 'click', '[data-ospg-next]', function () {
				self.show( self.index + 1 );
			} )
			.on( 'click', '[data-ospg-index]', function () {
				self.show( parseInt( $( this ).attr( 'data-ospg-index' ), 10 ) );
			} );
	};

	Lightbox.prototype.clear = function () {
		this.images = [];
		this.$media.empty();
		this.$thumbs.empty();
		this.$video.empty();
		this.$desc.empty();
		this.$title.text( '' );
		this.$prev.prop( 'hidden', true );
		this.$next.prop( 'hidden', true );
	};

	Lightbox.prototype.open = function ( productId, $trigger ) {
		var self = this;
		var token = ++this.token;

		this.$trigger = $trigger;
		this.mount();
		this.clear();

		this.$root.addClass( 'is-open is-loading' ).attr( 'aria-hidden', 'false' );
		$( 'body' ).addClass( 'ospg-popup-open' );
		$( document ).on( 'keydown.ospg', this.onKey );
		this.$close.trigger( 'focus' );

		request( 'ospg_get_product', { product_id: productId } )
			.done( function ( response ) {
				if ( token !== self.token ) {
					return;
				}
				if ( ! response || ! response.success ) {
					self.$title.text( i18n.error || '' );
					return;
				}
				self.render( response.data );
			} )
			.fail( function () {
				if ( token === self.token ) {
					self.$title.text( i18n.error || '' );
				}
			} )
			.always( function () {
				if ( token === self.token ) {
					self.$root.removeClass( 'is-loading' );
				}
			} );
	};

	Lightbox.prototype.render = function ( data ) {
		var self = this;

		this.images = data.images || [];
		this.$title.text( data.title || '' );
		this.$desc.html( data.description || '' ); // Sanitized with wp_kses_post() on the server.
		this.$video.html( data.video || '' ); // Built from wp_oembed_get() / escaped URL on the server.

		$.each( this.images, function ( index, image ) {
			$( '<button/>', { type: 'button', 'data-ospg-index': index } )
				.append( $( '<img/>', { src: image.thumb, alt: image.alt } ) )
				.appendTo( self.$thumbs );
		} );

		var multiple = this.images.length > 1;

		this.$prev.prop( 'hidden', ! multiple );
		this.$next.prop( 'hidden', ! multiple );
		this.$thumbs.prop( 'hidden', ! multiple );

		this.show( 0 );
	};

	Lightbox.prototype.show = function ( index ) {
		var total = this.images.length;

		if ( ! total ) {
			return;
		}

		this.index = ( index + total ) % total;

		var image = this.images[ this.index ];

		this.$media.empty().append( $( '<img/>', { src: image.full, alt: image.alt } ) );

		var current = this.index;

		this.$thumbs.children( 'button' ).each( function ( i ) {
			$( this ).toggleClass( 'is-active', i === current );
		} );
	};

	Lightbox.prototype.close = function () {
		this.token++;
		this.$root.removeClass( 'is-open is-loading' ).attr( 'aria-hidden', 'true' );
		this.$video.empty(); // Stops video playback.
		$( 'body' ).removeClass( 'ospg-popup-open' );
		$( document ).off( 'keydown.ospg' );

		if ( this.$trigger && this.$trigger.length ) {
			this.$trigger.trigger( 'focus' );
		}
	};

	Lightbox.prototype.onKey = function ( event ) {
		switch ( event.key ) {
			case 'Escape':
				this.close();
				break;
			case 'ArrowLeft':
				this.show( this.index - 1 );
				break;
			case 'ArrowRight':
				this.show( this.index + 1 );
				break;
			case 'Tab':
				this.trapFocus( event );
				break;
		}
	};

	Lightbox.prototype.trapFocus = function ( event ) {
		var $focusable = this.$root.find( 'button:not([hidden]), iframe, video[controls]' );

		if ( ! $focusable.length ) {
			return;
		}

		var first = $focusable.get( 0 );
		var last = $focusable.get( $focusable.length - 1 );

		if ( event.shiftKey && document.activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	};

	/* ------------------------------------------------------------------
	 * Filterable gallery with numbered pagination
	 * ---------------------------------------------------------------- */
	function Gallery( $el, lightbox ) {
		this.$el = $el;
		this.lightbox = lightbox;
		this.$grid = $el.find( '.ospg__grid' );
		this.$pagination = $el.find( '.ospg__pagination' );
		this.state = {
			category: '',
			paged: 1,
			maxPages: parseInt( $el.attr( 'data-max-pages' ), 10 ) || 1,
			busy: false,
		};

		this.bind();
		this.renderPagination();
	}

	Gallery.prototype.bind = function () {
		var self = this;

		this.$el
			.on( 'click', '.ospg__filter-btn', function () {
				self.setFilter( $( this ) );
			} )
			.on( 'click', '.ospg__page[data-page]', function () {
				var page = parseInt( $( this ).attr( 'data-page' ), 10 );

				if ( page && page !== self.state.paged ) {
					self.fetch( page );
				}
			} )
			.on( 'click', '.ospg-item__link', function ( event ) {
				event.preventDefault();
				self.lightbox.open( $( this ).attr( 'data-product-id' ), $( this ) );
			} );
	};

	Gallery.prototype.setFilter = function ( $button ) {
		this.$el
			.find( '.ospg__filter-btn' )
			.removeClass( 'is-active' )
			.attr( 'aria-pressed', 'false' );

		$button.addClass( 'is-active' ).attr( 'aria-pressed', 'true' );

		this.state.category = $button.attr( 'data-category' ) || '';
		this.fetch( 1 );
	};

	Gallery.prototype.fetch = function ( page ) {
		var self = this;

		if ( this.state.busy ) {
			return;
		}

		this.$el.find( '.ospg__error' ).remove();
		this.state.busy = true;
		this.$el.addClass( 'is-loading' );

		request( 'ospg_filter_products', {
			category: this.state.category,
			categories: this.$el.attr( 'data-categories' ) || '',
			per_page: this.$el.attr( 'data-per-page' ) || 9,
			orderby: this.$el.attr( 'data-orderby' ) || 'date',
			order: this.$el.attr( 'data-order' ) || 'DESC',
			paged: page,
		} )
			.done( function ( response ) {
				if ( ! response || ! response.success ) {
					self.showError();
					return;
				}

				self.$grid.html( response.data.html ); // Escaped on the server.
				self.state.paged = page;
				self.state.maxPages = parseInt( response.data.max_pages, 10 ) || 1;
				self.renderPagination();
				self.scrollToTop();
			} )
			.fail( function () {
				self.showError();
			} )
			.always( function () {
				self.state.busy = false;
				self.$el.removeClass( 'is-loading' );
			} );
	};

	Gallery.prototype.showError = function () {
		$( '<p/>', { class: 'ospg__error', role: 'alert', text: i18n.error || '' } ).appendTo( this.$el );
	};

	/**
	 * Keep the top of the gallery in view after changing page.
	 */
	Gallery.prototype.scrollToTop = function () {
		var top = this.$el.offset().top - 80;

		if ( $( window ).scrollTop() > top ) {
			$( 'html, body' ).stop().animate( { scrollTop: top }, 300 );
		}
	};

	/**
	 * Build the list of page numbers, e.g. 1 2 3 … 10.
	 *
	 * @param {number} current Current page.
	 * @param {number} total   Total pages.
	 * @return {Array} Numbers and 'dots' markers.
	 */
	Gallery.prototype.getPages = function ( current, total ) {
		var set = {};
		var pages = [];
		var result = [];
		var i;

		set[ 1 ] = set[ total ] = set[ current ] = true;
		set[ current - 1 ] = set[ current + 1 ] = true;

		if ( current <= 2 ) {
			set[ 2 ] = set[ 3 ] = true;
		}
		if ( current >= total - 1 ) {
			set[ total - 1 ] = set[ total - 2 ] = true;
		}

		for ( i = 1; i <= total; i++ ) {
			if ( set[ i ] ) {
				pages.push( i );
			}
		}

		$.each( pages, function ( index, page ) {
			if ( index && page - pages[ index - 1 ] > 1 ) {
				result.push( 'dots' );
			}
			result.push( page );
		} );

		return result;
	};

	Gallery.prototype.renderPagination = function () {
		var current = this.state.paged;
		var total = this.state.maxPages;
		var html = '';

		if ( total <= 1 ) {
			this.$pagination.empty().prop( 'hidden', true );
			return;
		}

		html +=
			'<button type="button" class="ospg__page ospg__page--prev"' +
			( current > 1 ? ' data-page="' + ( current - 1 ) + '"' : ' disabled' ) +
			'>' +
			( i18n.prevPage || 'Prev' ) +
			'</button>';

		$.each( this.getPages( current, total ), function ( index, page ) {
			if ( 'dots' === page ) {
				html += '<span class="ospg__dots" aria-hidden="true">&hellip;</span>';
				return;
			}

			html +=
				'<button type="button" class="ospg__page' +
				( page === current ? ' is-active" aria-current="page' : '' ) +
				'" data-page="' +
				page +
				'">' +
				page +
				'</button>';
		} );

		html +=
			'<button type="button" class="ospg__page ospg__page--next"' +
			( current < total ? ' data-page="' + ( current + 1 ) + '"' : ' disabled' ) +
			'>' +
			( i18n.nextPage || 'Next' ) +
			'</button>';

		this.$pagination.html( html ).prop( 'hidden', false );
	};

	/* ------------------------------------------------------------------
	 * Init
	 * ---------------------------------------------------------------- */
	$( function () {
		var $galleries = $( '.ospg' );

		if ( ! $galleries.length ) {
			return;
		}

		var lightbox = new Lightbox();

		$galleries.each( function () {
			new Gallery( $( this ), lightbox );
		} );
	} );

	/* ------------------------------------------------------------------
	 * Hover: show the product's second image
	 * ---------------------------------------------------------------- */
	$( document ).on( 'mouseenter focusin', '.ospg .ospg-item', function () {
		var $item = $( this );
		var $media = $item.find( '.ospg-item__media' );
		var id = $item.find( '.ospg-item__link' ).attr( 'data-product-id' );

		// Only check once per rendered card.
		if ( ! id || $item.data( 'ospgHover' ) ) {
			return;
		}

		$item.data( 'ospgHover', true );

		getHoverImage( id ).then( function ( image ) {
			if ( ! image ) {
				return; // No second image: do nothing.
			}

			// Preload so the card never shows a half-loaded image.
			var preload = new Image();

			preload.onload = function () {
				$( '<img/>', {
					class: 'ospg-item__hover',
					src: image.url,
					alt: image.alt || '',
					'aria-hidden': 'true',
				} ).appendTo( $media );
			};

			preload.src = image.url;
		} );
	} );
} )( jQuery );