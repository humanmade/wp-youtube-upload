<?php

//Admin submenu page
add_action( 'admin_menu', function() {

	$hook = add_submenu_page( 'upload.php', 'Youtube Upload', 'Youtube Upload', 'manage_options', 'wp-youtube-upload', function() {
		include __DIR__ . '/templates/admin.php';
	} );

	//Capture admin submenu page form submission
	add_action( 'load-'. $hook, function() {

		if ( ! ( isset( $_POST['youtube_upload_settings_submit'] ) || isset( $_POST['youtube_upload_authenticate_submit'] ) ) || ! isset( $_POST['youtube_upload_settings_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['youtube_upload_settings_nonce'], 'youtube_upload_settings_nonce' ) ) {
			wp_die( 'Failed to verify nonce' );
		}

		$client_id     = sanitize_text_field( $_POST['client_id'] );
		$client_secret = sanitize_text_field( $_POST['client_secret'] );
		$redirect_uri  = esc_url_raw( $_POST['redirect_uri'] );

		WP_Youtube_Upload_Configuration::set_client_id( $client_id );
		WP_Youtube_Upload_Configuration::set_client_secret( $client_secret );
		WP_Youtube_Upload_Configuration::set_redirect_uri( $redirect_uri );

		if ( isset( $_POST['youtube_upload_authenticate_submit'] ) ) {

			$yt = new WP_Youtube_Upload();

			wp_redirect( $yt->get_client()->createAuthUrl() );

		} else {

			wp_redirect( add_query_arg( array( 'updated' => 'true', 'page' => sanitize_text_field( $_POST['page'] ) ) ) );
		}

		exit;

	} );

} );

//Capture authorisation callback from youtube, we get a code which we have to exchange for a refresh token
add_action( 'init', function() {

	//todo: better to get this on a dedicated end point?

	if ( ! isset( $_GET['state'] ) || ! isset( $_GET['code'] ) || ! current_user_can( 'administrator' ) ) {
		return;
	}

	$state = sanitize_text_field( $_GET['state'] );
	$code  = sanitize_text_field( $_GET['code'] );

	if ( ! wp_verify_nonce( $state, 'wp_youtube_upload_get_token' ) ) {

		wp_die( 'Failed to verify nonce' );
	}

	$yt           = new WP_Youtube_Upload();
	$client       = $yt->get_client();

	$redirect_url = add_query_arg( array( 'page' => 'wp-youtube-upload' ), admin_url( 'upload.php' ) );

	try {

		//Get a refresh token from the acquired code
		$client->authenticate( $code );

		//Store the refresh token for later use if it has been supplied
		if ( $client->getRefreshToken() ) {

			WP_Youtube_Upload_Configuration::set_refresh_token( $client->getRefreshToken() );
		}

		//Store the access token for later use
		WP_Youtube_Upload_Configuration::set_access_token(  $client->getAccessToken() );

		$redirect_url = add_query_arg( array( 'google_auth_successful' => 'true' ), $redirect_url );

	} catch ( Exception $e ) {

		$redirect_url = add_query_arg( array( 'google_auth_successful' => 'false' ), $redirect_url );

		trigger_error( 'Error verifying google auth ' . $e->getMessage() );
	}

	wp_redirect($redirect_url );

	exit;
} );

add_action( 'attachment_submitbox_misc_actions', function() {

	$post = get_post();

	if ( ! preg_match( '#^(video)/#', $post->post_mime_type ) ) {
		return;
	}

	$youtube_video = new WP_Youtube_Upload_Attachment( $post );

	?>

	<?php if ( $schedule = wp_next_scheduled( 'wp_youtube_upload_new_video_attachment', array( 'attachment' => $post->ID ) ) ) : ?>
		<div class="misc-pub-section misc-pub-mime-meta">
			Queued for Youtube upload in <?php echo time() - $schedule ?> seconds.
		</div>
	<?php endif ?>

	<div class="misc-pub-section misc-pub-mime-meta">
		Uploaded to Youtube: <strong><?php echo $youtube_video->is_uploaded() ? 'Yes' : 'No' ?></strong>
	</div>

	<?php if ( $youtube_video->is_uploaded() ) : ?>
		<div class="misc-pub-section misc-pub-mime-meta">
			Youtube Processed: <strong><?php echo $youtube_video->is_processed() ? 'Yes' : 'No' ?></strong>
		</div>
	<?php endif ?>
	<?php
}, 11 );
//add_action( 'init', function() {
//
//	global $shortcode_tags;
//
//	var_dump( $shortcode_tags['video'] );
//	exit;
//} );