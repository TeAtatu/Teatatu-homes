/**
 * Teatatu Homes Theme — progressive enhancement only. Every feature here
 * works without JavaScript (forms submit, links navigate); this just makes
 * them nicer.
 */
( function () {
	var data = window.tthTheme || {};

	// Buy / Rent switch: swap price options and the price label.
	document.querySelectorAll( '[data-tth-search]' ).forEach( function ( form ) {
		var radios = form.querySelectorAll( 'input[name="listing"]' );
		function apply( listing ) {
			var key = 'rent' === listing ? 'rent' : 'sale';
			form.querySelectorAll( '[data-price-label]' ).forEach( function ( el ) {
				el.textContent = el.getAttribute( 'data-' + key );
			} );
			if ( ! data.priceSteps || ! data.priceSteps[ key ] ) {
				return;
			}
			form.querySelectorAll( '[data-price-select]' ).forEach( function ( select ) {
				var any = select.options[ 0 ];
				var current = select.value;
				select.length = 0;
				select.add( any );
				Object.keys( data.priceSteps[ key ] ).forEach( function ( value ) {
					var opt = new Option( data.priceSteps[ key ][ value ], value );
					opt.selected = value === current;
					select.add( opt );
				} );
			} );
		}
		radios.forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				apply( radio.value );
			} );
		} );
	} );

	// Sort dropdown submits on change.
	document.querySelectorAll( '[data-tth-autosubmit] select' ).forEach( function ( select ) {
		select.addEventListener( 'change', function () {
			select.form.submit();
		} );
	} );

	// Front page tabs (For sale / For rent).
	document.querySelectorAll( '[data-tth-tabs]' ).forEach( function ( list ) {
		var tabs = Array.prototype.slice.call( list.querySelectorAll( '[role="tab"]' ) );
		function select( tab ) {
			tabs.forEach( function ( t ) {
				var on = t === tab;
				t.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				t.tabIndex = on ? 0 : -1;
				document.getElementById( t.getAttribute( 'aria-controls' ) ).hidden = ! on;
			} );
		}
		tabs.forEach( function ( tab, i ) {
			tab.addEventListener( 'click', function () {
				select( tab );
			} );
			tab.addEventListener( 'keydown', function ( e ) {
				if ( 'ArrowRight' !== e.key && 'ArrowLeft' !== e.key ) {
					return;
				}
				var next = tabs[ ( i + ( 'ArrowRight' === e.key ? 1 : tabs.length - 1 ) ) % tabs.length ];
				next.focus();
				select( next );
			} );
		} );
		list.hidden = false;
		select( tabs[ 0 ] );
	} );

	// Photo lightbox.
	var gallery = document.querySelector( '[data-tth-gallery]' );
	if ( gallery && gallery.showModal ) {
		document.querySelectorAll( '[data-tth-open-gallery]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				gallery.showModal();
				var photo = gallery.querySelector( '[data-tth-photo="' + btn.getAttribute( 'data-tth-open-gallery' ) + '"]' );
				if ( photo ) {
					photo.scrollIntoView();
				}
			} );
		} );
		gallery.querySelector( '[data-tth-close]' ).addEventListener( 'click', function () {
			gallery.close();
		} );
		gallery.addEventListener( 'click', function ( e ) {
			if ( e.target === gallery ) {
				gallery.close();
			}
		} );
	}

	// Phone number: show "Show phone number" until clicked, then the number.
	document.querySelectorAll( '[data-tth-reveal]' ).forEach( function ( link ) {
		var text = link.querySelector( '[data-tth-reveal-text]' );
		var revealed = false;
		text.textContent = data.showPhone || 'Show phone number';
		link.addEventListener( 'click', function ( e ) {
			if ( revealed ) {
				return;
			}
			e.preventDefault();
			revealed = true;
			text.textContent = link.getAttribute( 'data-tth-reveal' );
		} );
	} );

	// Share: native share sheet, else copy the link.
	document.querySelectorAll( '[data-tth-share]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var url = btn.getAttribute( 'data-tth-share' );
			if ( navigator.share ) {
				navigator.share( { title: document.title, url: url } ).catch( function () {} );
			} else if ( navigator.clipboard ) {
				navigator.clipboard.writeText( url ).then( function () {
					btn.setAttribute( 'aria-label', data.copied || 'Link copied' );
					btn.title = data.copied || 'Link copied';
				} );
			}
		} );
	} );
} )();
