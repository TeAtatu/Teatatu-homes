<?php
/**
 * Listing photos. Each photo is either:
 *
 *  - an **upload** — a Media Library attachment, stored as `{"id": 123}`.
 *    Uploads made for a home are saved in that home's own folder,
 *    wp-content/uploads/homes/<home ID>/, and attached to the home
 *    (post_parent), so the Media Library shows them as "Uploaded to" it.
 *    A home may only use uploads attached to itself.
 *  - a **link** — a remote image URL, stored as `{"url": "…", "alt": "…"}`
 *    and served exactly as given (never downloaded), as in the other
 *    Teatatu plugins.
 *
 * Uploads and links can be mixed and ordered freely; the first photo is the
 * main photo. Display layers read resolved photos from the normalised item
 * (teatatu_homes_resolve_photos()), never attachment IDs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Most photos a home may have.
 *
 * @return int
 */
function teatatu_homes_max_photos() {
	return (int) apply_filters( 'teatatu_homes_max_photos', 60 );
}

// ---------------------------------------------------------------------------
// Per-home upload folder
// ---------------------------------------------------------------------------

/**
 * The home an in-progress upload is for, if any: the media modal and
 * async-upload.php send `post_id`; the REST media route sends `post`.
 *
 * @return int Home ID, or 0.
 */
function teatatu_homes_upload_target() {
	// phpcs:disable WordPress.Security.NonceVerification -- read-only lookup; core verifies the upload request itself.
	$id = 0;
	if ( isset( $_REQUEST['post_id'] ) ) {
		$id = absint( $_REQUEST['post_id'] );
	} elseif ( defined( 'REST_REQUEST' ) && REST_REQUEST && isset( $_REQUEST['post'] ) ) {
		$id = absint( $_REQUEST['post'] );
	}
	// phpcs:enable
	if ( ! $id || 'teatatu_home' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
		return 0;
	}
	return $id;
}

/**
 * Folder (relative to the uploads base) for a home's photos.
 *
 * @param int $post_id Home ID.
 * @return string e.g. "/homes/123"
 */
function teatatu_homes_upload_subdir( $post_id ) {
	return '/homes/' . (int) $post_id;
}

add_filter( 'upload_dir', 'teatatu_homes_upload_dir' );

/**
 * Sends uploads made for a home into uploads/homes/<home ID>/.
 *
 * @param array $dirs Upload directory data.
 * @return array
 */
function teatatu_homes_upload_dir( $dirs ) {
	$home = teatatu_homes_upload_target();
	if ( ! $home || ! empty( $dirs['error'] ) ) {
		return $dirs;
	}
	$sub            = teatatu_homes_upload_subdir( $home );
	$dirs['subdir'] = $sub;
	$dirs['path']   = $dirs['basedir'] . $sub;
	$dirs['url']    = $dirs['baseurl'] . $sub;
	return $dirs;
}

// ---------------------------------------------------------------------------
// Validation
// ---------------------------------------------------------------------------

/**
 * Normalises one submitted photo: an integer (attachment ID), a URL
 * string, `{"id": …}` or `{"url": …, "alt": …}`.
 *
 * @param mixed $raw Submitted value.
 * @return array|null ['id' => int] or ['url' => string, 'alt' => string]; null if blank.
 */
function teatatu_homes_parse_photo( $raw ) {
	if ( is_int( $raw ) || ( is_string( $raw ) && ctype_digit( $raw ) ) ) {
		return array( 'id' => (int) $raw );
	}
	if ( is_string( $raw ) ) {
		$raw = trim( $raw );
		return '' === $raw ? null : array( 'url' => $raw, 'alt' => '' );
	}
	if ( is_array( $raw ) || is_object( $raw ) ) {
		$raw = (array) $raw;
		if ( ! empty( $raw['id'] ) ) {
			return array( 'id' => (int) $raw['id'] );
		}
		$url = trim( (string) ( $raw['url'] ?? '' ) );
		return '' === $url ? null : array( 'url' => $url, 'alt' => (string) ( $raw['alt'] ?? '' ) );
	}
	return null;
}

/**
 * Validates a submitted photo list for a home.
 *
 * Uploads must be images attached to this home (anything uploaded from the
 * home's edit screen is). For a home that doesn't exist yet (a REST create)
 * only links are accepted — upload with `post=<home ID>` once it exists.
 *
 * @param mixed $input   Submitted list.
 * @param int   $post_id Home ID (0 when creating).
 * @return array|WP_Error Normalised list.
 */
function teatatu_homes_validate_photos( $input, $post_id ) {
	$err = function ( $message ) {
		return new WP_Error( 'rest_invalid_param', $message, array( 'status' => 400, 'field' => 'photos' ) );
	};
	$out  = array();
	$seen = array();
	foreach ( (array) $input as $raw ) {
		$photo = teatatu_homes_parse_photo( $raw );
		if ( null === $photo ) {
			continue;
		}
		if ( isset( $photo['id'] ) ) {
			$id = $photo['id'];
			if ( 'attachment' !== get_post_type( $id ) || ! wp_attachment_is_image( $id ) ) {
				/* translators: %d: attachment ID. */
				return $err( sprintf( __( 'Media item %d is not an image.', 'teatatu-homes' ), $id ) );
			}
			if ( ! $post_id || (int) wp_get_post_parent_id( $id ) !== (int) $post_id ) {
				/* translators: %d: attachment ID. */
				return $err( sprintf( __( 'Photo %d was not uploaded to this home. Upload it from this home\'s edit screen.', 'teatatu-homes' ), $id ) );
			}
			$key = 'id:' . $id;
		} else {
			$url = esc_url_raw( $photo['url'], array( 'http', 'https' ) );
			if ( '' === $url ) {
				return $err( __( 'A photo URL is not valid.', 'teatatu-homes' ) );
			}
			$photo = array( 'url' => $url, 'alt' => sanitize_text_field( $photo['alt'] ) );
			$key   = 'url:' . $url;
		}
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = true;
		$out[]        = $photo;
	}
	if ( count( $out ) > teatatu_homes_max_photos() ) {
		/* translators: %d: maximum number of photos. */
		return $err( sprintf( __( 'A home can have at most %d photos.', 'teatatu-homes' ), teatatu_homes_max_photos() ) );
	}
	return $out;
}

// ---------------------------------------------------------------------------
// Reading
// ---------------------------------------------------------------------------

/**
 * Resolves stored photos into display-ready entries. Uploads that have
 * since been deleted from the Media Library are skipped.
 *
 * @param array  $photos  Stored photos.
 * @param string $alt     Fallback alt text (e.g. the public address).
 * @return array[] {
 *     @type string   $source 'upload' or 'link'.
 *     @type int      $id     Attachment ID (0 for links).
 *     @type string   $url    Large image for galleries.
 *     @type string   $thumb  Card-sized image.
 *     @type string   $full   Original image.
 *     @type string   $srcset Responsive srcset ('' for links).
 *     @type int      $width  Large width (0 if unknown).
 *     @type int      $height Large height (0 if unknown).
 *     @type string   $alt    Alt text.
 * }
 */
function teatatu_homes_resolve_photos( $photos, $alt = '' ) {
	$out = array();
	foreach ( (array) $photos as $photo ) {
		if ( ! empty( $photo['id'] ) ) {
			$id    = (int) $photo['id'];
			$large = wp_get_attachment_image_src( $id, 'large' );
			if ( ! $large ) {
				continue;
			}
			$thumb  = wp_get_attachment_image_src( $id, 'medium_large' );
			$own    = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
			$out[] = array(
				'source' => 'upload',
				'id'     => $id,
				'url'    => $large[0],
				'thumb'  => $thumb ? $thumb[0] : $large[0],
				'full'   => (string) wp_get_attachment_url( $id ),
				'srcset' => (string) wp_get_attachment_image_srcset( $id, 'large' ),
				'width'  => (int) $large[1],
				'height' => (int) $large[2],
				'alt'    => '' !== $own ? $own : $alt,
			);
		} elseif ( ! empty( $photo['url'] ) ) {
			$out[] = array(
				'source' => 'link',
				'id'     => 0,
				'url'    => $photo['url'],
				'thumb'  => $photo['url'],
				'full'   => $photo['url'],
				'srcset' => '',
				'width'  => 0,
				'height' => 0,
				'alt'    => ! empty( $photo['alt'] ) ? $photo['alt'] : $alt,
			);
		}
	}
	return $out;
}

/**
 * The main (first) photo's card-sized URL, or ''.
 *
 * @param int $post_id Home ID.
 * @return string
 */
function teatatu_homes_main_photo_url( $post_id ) {
	$photos = teatatu_homes_resolve_photos( array_slice( teatatu_homes_get_fields( $post_id )['photos'], 0, 3 ) );
	return $photos ? $photos[0]['thumb'] : '';
}

// ---------------------------------------------------------------------------
// Housekeeping
// ---------------------------------------------------------------------------

add_action( 'before_delete_post', 'teatatu_homes_delete_home_uploads' );

/**
 * When a home is permanently deleted, its uploaded photos go with it (they
 * live in the home's own folder and no other home may use them). Emptied
 * folders are removed. Linked photos are untouched — they were never
 * stored here.
 *
 * @param int $post_id Post being deleted.
 */
function teatatu_homes_delete_home_uploads( $post_id ) {
	if ( 'teatatu_home' !== get_post_type( $post_id ) ) {
		return;
	}
	teatatu_homes_delete_attached_photos( $post_id );
}

/**
 * Deletes every image attached to a home and its folder if empty. Shared
 * with uninstall.php.
 *
 * @param int $post_id Home ID.
 */
function teatatu_homes_delete_attached_photos( $post_id ) {
	$ids = get_posts(
		array(
			'post_type'        => 'attachment',
			'post_parent'      => (int) $post_id,
			'post_status'      => 'any',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'suppress_filters' => true,
		)
	);
	foreach ( $ids as $id ) {
		wp_delete_attachment( $id, true );
	}
	$uploads = wp_get_upload_dir();
	$dir     = $uploads['basedir'] . teatatu_homes_upload_subdir( $post_id );
	if ( is_dir( $dir ) && 2 === count( (array) scandir( $dir ) ) ) {
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- best effort.
	}
}

add_action( 'delete_attachment', 'teatatu_homes_forget_deleted_upload' );

/**
 * If an upload is deleted from the Media Library, drop it from its home's
 * photo list so the stored list never points at nothing.
 *
 * @param int $attachment_id Attachment being deleted.
 */
function teatatu_homes_forget_deleted_upload( $attachment_id ) {
	$home = (int) wp_get_post_parent_id( $attachment_id );
	if ( ! $home || 'teatatu_home' !== get_post_type( $home ) ) {
		return;
	}
	$key    = teatatu_homes_mk( 'photos' );
	$photos = get_post_meta( $home, $key, true );
	if ( ! is_array( $photos ) ) {
		return;
	}
	$kept = array_values(
		array_filter(
			$photos,
			function ( $photo ) use ( $attachment_id ) {
				return empty( $photo['id'] ) || (int) $photo['id'] !== (int) $attachment_id;
			}
		)
	);
	if ( count( $kept ) !== count( $photos ) ) {
		update_post_meta( $home, $key, $kept );
	}
}

// ---------------------------------------------------------------------------
// Admin: the photo manager in the "Listing details" box
// ---------------------------------------------------------------------------

/**
 * Data for one photo row in the admin photo manager.
 *
 * @param array $photo Stored photo.
 * @return array|null {value, thumb, label, source}
 */
function teatatu_homes_admin_photo_row( $photo ) {
	if ( ! empty( $photo['id'] ) ) {
		$thumb = wp_get_attachment_image_src( (int) $photo['id'], 'thumbnail' );
		if ( ! $thumb ) {
			return null;
		}
		return array(
			'value'  => 'id:' . (int) $photo['id'],
			'thumb'  => $thumb[0],
			'label'  => wp_basename( (string) get_attached_file( (int) $photo['id'] ) ),
			'source' => 'upload',
		);
	}
	return array(
		'value'  => 'url:' . $photo['url'],
		'thumb'  => $photo['url'],
		'label'  => $photo['url'],
		'source' => 'link',
	);
}

/**
 * Renders the photo manager: an ordered list of uploads and links with
 * "Upload photos" (media modal limited to this home) and "Add by link".
 * Rows submit as tth_photos[] = "id:<n>" or "url:<url>".
 *
 * @param WP_Post $post   Home.
 * @param array   $photos Stored photos.
 */
function teatatu_homes_render_photo_manager( $post, $photos ) {
	$can_upload = current_user_can( 'upload_files' );
	?>
	<div class="tth-photos" data-tth-photos data-post="<?php echo (int) $post->ID; ?>">
		<input type="hidden" name="tth_photos_present" value="1" />
		<ol class="tth-photos__list" data-tth-photo-list>
			<?php
			foreach ( $photos as $photo ) :
				$row = teatatu_homes_admin_photo_row( $photo );
				if ( ! $row ) {
					continue;
				}
				teatatu_homes_render_photo_row( $row );
			endforeach;
			?>
		</ol>
		<p class="tth-photos__empty" data-tth-photo-empty <?php echo $photos ? 'hidden' : ''; ?>><?php esc_html_e( 'No photos yet.', 'teatatu-homes' ); ?></p>
		<div class="tth-photos__add">
			<?php if ( $can_upload ) : ?>
				<button type="button" class="button button-primary" data-tth-photo-upload><?php esc_html_e( 'Upload photos', 'teatatu-homes' ); ?></button>
			<?php endif; ?>
			<label class="screen-reader-text" for="tth_photo_url"><?php esc_html_e( 'Photo URL', 'teatatu-homes' ); ?></label>
			<input type="url" id="tth_photo_url" class="regular-text" placeholder="https://…" data-tth-photo-url />
			<button type="button" class="button" data-tth-photo-link><?php esc_html_e( 'Add by link', 'teatatu-homes' ); ?></button>
		</div>
		<p class="description">
			<?php
			echo esc_html(
				$can_upload
					/* translators: %s: folder path. */
					? sprintf( __( 'The first photo is the main photo. Uploads are saved in this home\'s own Media Library folder (uploads%s) and deleted with the home. Links are shown as given and never downloaded.', 'teatatu-homes' ), teatatu_homes_upload_subdir( $post->ID ) )
					: __( 'The first photo is the main photo. Links are shown as given and never downloaded.', 'teatatu-homes' )
			);
			?>
		</p>
		<template data-tth-photo-template><?php teatatu_homes_render_photo_row( array( 'value' => '', 'thumb' => '', 'label' => '', 'source' => '' ) ); ?></template>
	</div>
	<?php
}

/**
 * One row of the photo manager.
 *
 * @param array $row Row data (teatatu_homes_admin_photo_row()).
 */
function teatatu_homes_render_photo_row( $row ) {
	?>
	<li class="tth-photo" data-source="<?php echo esc_attr( $row['source'] ); ?>">
		<input type="hidden" name="tth_photos[]" value="<?php echo esc_attr( $row['value'] ); ?>" />
		<img src="<?php echo esc_url( $row['thumb'] ); ?>" alt="" referrerpolicy="no-referrer" />
		<span class="tth-photo__meta">
			<span class="tth-photo__badge" data-upload="<?php esc_attr_e( 'Uploaded', 'teatatu-homes' ); ?>" data-link="<?php esc_attr_e( 'Link', 'teatatu-homes' ); ?>"><?php echo esc_html( 'upload' === $row['source'] ? __( 'Uploaded', 'teatatu-homes' ) : __( 'Link', 'teatatu-homes' ) ); ?></span>
			<span class="tth-photo__label"><?php echo esc_html( $row['label'] ); ?></span>
		</span>
		<span class="tth-photo__actions">
			<button type="button" class="button-link" data-tth-photo-up aria-label="<?php esc_attr_e( 'Move photo up', 'teatatu-homes' ); ?>">↑</button>
			<button type="button" class="button-link" data-tth-photo-down aria-label="<?php esc_attr_e( 'Move photo down', 'teatatu-homes' ); ?>">↓</button>
			<button type="button" class="button-link button-link-delete" data-tth-photo-remove><?php esc_html_e( 'Remove', 'teatatu-homes' ); ?></button>
		</span>
	</li>
	<?php
}

/**
 * Turns the photo manager's submitted rows back into a photo list.
 *
 * @param array $src Unslashed POST data.
 * @return array|null Null if the photo manager wasn't on the form.
 */
function teatatu_homes_photos_from_form( $src ) {
	if ( empty( $src['tth_photos_present'] ) ) {
		return null;
	}
	$out = array();
	foreach ( (array) ( $src['tth_photos'] ?? array() ) as $value ) {
		$value = (string) $value;
		if ( 0 === strpos( $value, 'id:' ) ) {
			$out[] = array( 'id' => (int) substr( $value, 3 ) );
		} elseif ( 0 === strpos( $value, 'url:' ) ) {
			$out[] = array( 'url' => substr( $value, 4 ), 'alt' => '' );
		}
	}
	return $out;
}
