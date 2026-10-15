/* Teatatu Homes admin: sale/rent field toggle and the photo manager. */
( function () {
	var box = document.querySelector( '.tth-box' );
	if ( ! box ) {
		return;
	}
	var i18n = window.tthAdmin || {};

	// Show sale-only or rent-only fields.
	box.querySelectorAll( 'input[name="tth_listing"]' ).forEach( function ( radio ) {
		radio.addEventListener( 'change', function () {
			box.setAttribute( 'data-listing', radio.value );
		} );
	} );

	// Photo manager.
	var manager = box.querySelector( '[data-tth-photos]' );
	if ( ! manager ) {
		return;
	}
	var list = manager.querySelector( '[data-tth-photo-list]' );
	var empty = manager.querySelector( '[data-tth-photo-empty]' );
	var template = manager.querySelector( '[data-tth-photo-template]' );
	var urlInput = manager.querySelector( '[data-tth-photo-url]' );

	function refresh() {
		empty.hidden = list.children.length > 0;
	}

	function has( value ) {
		return Array.prototype.some.call( list.querySelectorAll( 'input[name="tth_photos[]"]' ), function ( input ) {
			return input.value === value;
		} );
	}

	function addRow( value, thumb, label, source ) {
		if ( has( value ) ) {
			return;
		}
		var row = template.content.firstElementChild.cloneNode( true );
		row.setAttribute( 'data-source', source );
		row.querySelector( 'input' ).value = value;
		row.querySelector( 'img' ).src = thumb;
		row.querySelector( '.tth-photo__label' ).textContent = label;
		var badge = row.querySelector( '.tth-photo__badge' );
		badge.textContent = badge.getAttribute( 'upload' === source ? 'data-upload' : 'data-link' );
		list.appendChild( row );
		refresh();
	}

	list.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( 'button' );
		if ( ! btn ) {
			return;
		}
		var row = btn.closest( '.tth-photo' );
		if ( btn.hasAttribute( 'data-tth-photo-remove' ) ) {
			row.remove();
			refresh();
		} else if ( btn.hasAttribute( 'data-tth-photo-up' ) && row.previousElementSibling ) {
			list.insertBefore( row, row.previousElementSibling );
			btn.focus();
		} else if ( btn.hasAttribute( 'data-tth-photo-down' ) && row.nextElementSibling ) {
			list.insertBefore( row.nextElementSibling, row );
			btn.focus();
		}
	} );

	// Add by link.
	function addLink() {
		var url = urlInput.value.trim();
		if ( ! /^https?:\/\/\S+$/i.test( url ) ) {
			window.alert( i18n.badUrl || 'Enter a full image URL.' );
			urlInput.focus();
			return;
		}
		addRow( 'url:' + url, url, url, 'link' );
		urlInput.value = '';
		urlInput.focus();
	}
	manager.querySelector( '[data-tth-photo-link]' ).addEventListener( 'click', addLink );
	urlInput.addEventListener( 'keydown', function ( e ) {
		if ( 'Enter' === e.key ) {
			e.preventDefault();
			addLink();
		}
	} );

	// Upload: the media modal, limited to images uploaded to this home.
	var uploadBtn = manager.querySelector( '[data-tth-photo-upload]' );
	if ( uploadBtn && window.wp && wp.media ) {
		var frame;
		uploadBtn.addEventListener( 'click', function () {
			if ( ! frame ) {
				frame = wp.media( {
					title: i18n.frameTitle,
					button: { text: i18n.frameButton },
					multiple: 'add',
					library: { type: 'image', uploadedTo: parseInt( manager.getAttribute( 'data-post' ), 10 ) }
				} );
				frame.on( 'select', function () {
					frame.state().get( 'selection' ).each( function ( att ) {
						var a = att.toJSON();
						var sizes = a.sizes || {};
						var thumb = ( sizes.thumbnail || sizes.medium || sizes.full || { url: a.url } ).url;
						addRow( 'id:' + a.id, thumb, a.filename || a.title || '', 'upload' );
					} );
				} );
			}
			frame.open();
		} );
	}
} )();
