<?php

// Call set_include_path() as needed to point to your client library.
require_once __DIR__ . '/sdk/google-api-php-client/autoload.php';
require_once __DIR__ . '/classes/class-wordpress-youtube-upload-configuration.php';
require_once __DIR__ . '/classes/class-wordpress-youtube-upload-attachment.php';
require_once __DIR__ . '/classes/class-wordpress-youtube-upload.php';
require_once __DIR__ . '/wordpress-youtube-upload-admin.php';

//Hook into add_attachment and schedule cron event to upload the attachment to youtube if requirements are satisfied
add_action( 'add_attachment', function( $attachment ) {

	//Not authenticated yet, return
	if ( ! WP_Youtube_Upload_Configuration::get_refresh_token() ) {
		return;
	}

	$at = new WP_Youtube_Upload_Attachment( $attachment );

	if ( ! $at->should_upload() ) {
		return;
	}

	wp_schedule_single_event( time(), 'wp_youtube_upload_new_video_attachment', array( 'attachment' => $attachment, 'time' => time() ) );
} );

//Capture cron event for uploading attachment to youtube
add_action( 'wp_youtube_upload_new_video_attachment', function( $attachment ) {

	$at = new WP_Youtube_Upload_Attachment( $attachment );

	$at->upload();
} );

//Hook into edit_attachment and schedule cron event to update an exisitng video on youtube if requirements are satisfied
add_action( 'edit_attachment', function( $attachment ) {

	//Not authenticated yet, return
	if ( ! WP_Youtube_Upload_Configuration::get_refresh_token() ) {
		return;
	}

	$at = new WP_Youtube_Upload_Attachment( $attachment );

	//Not a video, ignore
	if ( ! $at->is_video() || ! $at->is_uploaded()  ) {
		return;
	}

	wp_schedule_single_event( time(), 'wp_youtube_update_video_attachment', array( 'attachment' => $attachment, 'time' => time() ) );
} );

//Capture cron event for updating a video on youtube
add_action( 'wp_youtube_update_video_attachment', function( $attachment ) {

	$at = new WP_Youtube_Upload_Attachment( $attachment );

	$at->update();
} );

//When an attachment is uploaded to youtube, call and update on the api incase any of the attachment fields were changed while it was being uploaded
add_action( 'wp_youtube_upload_attachment_upload_complete', function( WP_Youtube_Upload_Attachment $attachment ) {

	$attachment->update();
} );

//When an attachment is uploaded to youtube, periodically check if the video has completed processing
add_action( 'wp_youtube_upload_attachment_upload_complete', function( WP_Youtube_Upload_Attachment $attachment ) {

	wp_schedule_single_event( strtotime( '+10 minutes' ), 'wp_youtube_recursive_check_uploaded_attachment_processed', array( $attachment->get_post_id(), 'recursion' => 1 ) );
} );

//Recursively check if the upload has finished processing - exit only if the status !== 'uploaded' or if we fail to get a response from the API or we have already checked 5 times
add_action( 'wp_youtube_recursive_check_uploaded_attachment_processed', function( $attachment_id, $recursion = 1 ) {

	$recursion++;

	$attachment = WP_Youtube_Upload_Attachment::get_instance( $attachment_id );
	$refreshed  = $attachment->refresh_upload_data();

	if ( $refreshed && $attachment->get_upload_status()->getUploadStatus() === 'uploaded' && $recursion > 5 ) {

		wp_schedule_single_event( strtotime( '+10 minutes' ), 'wp_youtube_recursive_check_uploaded_attachment_processed', array( $attachment->get_post_id(), 'recursion' => $recursion ) );
	}
} );

//Override video shortcode
//todo: honor all the shortcode settings
add_filter( 'wp_video_shortcode_override', function( $bool, $attrs, $content ) {

	$url = array_filter( array_values( $attrs ), function( $item ) { return ( strpos( $item, 'http' ) !== false ); } )[0];
	$at  = WP_Youtube_Upload_Attachment::get_instance_from_url( $url );

	if ( ! $at || ! $at->is_uploaded() ) {
		return '';
	}

	$defaults_atts = array(
		'src'      => '',
		'poster'   => '',
		'loop'     => '',
		'autoplay' => '',
		'preload'  => 'metadata',
		'width'    => 640,
		'height'   => 360,
	);

	$atts = shortcode_atts( $defaults_atts, $attrs, 'video' );

	if ( is_admin() ) {
		// shrink the video so it isn't huge in the admin
		if ( $atts['width'] > $defaults_atts['width'] ) {
			$atts['height'] = round( ( $atts['height'] * $defaults_atts['width'] ) / $atts['width'] );
			$atts['width'] = $defaults_atts['width'];
		}
	} else {
		// if the video is bigger than the theme
		if ( ! empty( $content_width ) && $atts['width'] > $content_width ) {
			$atts['height'] = round( ( $atts['height'] * $content_width ) / $atts['width'] );
			$atts['width'] = $content_width;
		}
	}

	return $at->get_embed_html( $atts );

}, 10, 3 );