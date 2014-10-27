<?php

/**
 * Configuration class - Handles getting and setting plugin configuration
 *
 * Class WP_Youtube_Upload_Configuration
 */
class WP_Youtube_Upload_Configuration {

	/**
	 * Get the chunk size to be used in uploading videos
	 *
	 * @return int
	 */
	public static function get_chunk_size() {

		return apply_filters( 'wp_youtube_upload_chunk_size', 1024 * 1024 * 5 ); //5MB
	}

	/**
	 * Set the refresh token for the API
	 *
	 * @param $token
	 */
	public static  function set_refresh_token( $token ) {
		update_option( 'wp_youtube_upload_refresh_token', $token );
	}

	/**
	 * Get the saved refresh token for the API
	 *
	 * @return mixed|void
	 */
	public static function get_refresh_token() {

		return apply_filters( 'wp_youtube_upload_refresh_token', get_option( 'wp_youtube_upload_refresh_token', false ) );
	}

	/**
	 * Set the access token for the API
	 *
	 * @param $token
	 */
	public static function set_access_token( $token ) {

		update_option( 'wp_youtube_upload_access_token', $token );
	}

	/**
	 * Get the access token for the API
	 *
	 * @return mixed|void
	 */
	public static function get_access_token() {

		return  apply_filters( 'wp_youtube_upload_access_token', get_option( 'wp_youtube_upload_access_token', false ) );
	}

	/**
	 * Get the client secret for the API
	 *
	 * @return string
	 */
	public static function get_client_secret() {

		return apply_filters( 'wp_youtube_upload_client_secret', get_option( 'wp_youtube_upload_client_secret', false ) );
	}

	/**
	 * Set the client secret for the API
	 *
	 * @param $secret
	 */
	public static function set_client_secret( $secret ) {

		update_option( 'wp_youtube_upload_client_secret', $secret );
	}

	/**
	 * Get the client ID for the API
	 *
	 * @return string
	 */
	public static function get_client_id() {

		return apply_filters( 'wp_youtube_upload_client_id', get_option( 'wp_youtube_upload_client_id', false ) );
	}

	/**
	 * Set the client ID for the API
	 *
	 * @param $id
	 */
	public static function set_client_id( $id ) {

		update_option( 'wp_youtube_upload_client_id', $id );
	}

	/**
	 * Get the redirect url to be returned to after authentication with the API
	 *
	 * @return string
	 */
	public static function get_redirect_uri() {

		return apply_filters( 'wp_youtube_upload_redirect_uri', get_option( 'wp_youtube_upload_redirect_uri', home_url() ) );
	}

	/**
	 * Set the redirect url to be returned to after authentication with the API
	 *
	 * @param $uri
	 */
	public static function set_redirect_uri( $uri ) {

		update_option( 'wp_youtube_upload_redirect_uri', $uri );
	}

	/**
	 * Get the default categorisation to be used on uploaded attachments, can be overridden on an individual attachment basis
	 *
	 * @return mixed|void
	 */
	public static function get_default_video_category() {

		return apply_filters( 'wp_youtube_upload_default_video_category', get_option( 'wp_youtube_upload_default_video_category', '22' ) );
	}

	/**
	 * Set the default categorisation to be used on uploaded attachments, can be overridden on an individual attachment basis
	 *
	 * @param $category_id
	 */
	public static function set_default_video_category( $category_id ) {

		update_option( 'wp_youtube_upload_default_video_category', $category_id );
	}

	/**
	 * Get the default privacy setting to be used on uploaded attachments, can be overridden on an individual attachment basis
	 *
	 * @return mixed|void
	 */
	public static function get_default_video_privacy() {

		return apply_filters( 'wp_youtube_upload_default_video_privacy', get_option( 'wp_youtube_upload_default_video_privacy', '22' ) );
	}

	/**
	 * Set the default privacy setting to be used on uploaded attachments, can be overridden on an individual attachment basis
	 *
	 * @param $privacy
	 */
	public static function set_default_video_privacy( $privacy ) {

		update_option( 'wp_youtube_upload_default_video_privacy', $privacy );
	}

	/**
	 * Get a list of available privacy settings than can be used on a video upload
	 *
	 * @return array
	 */
	public static function get_privacy_options() {

		return array(
			'public',
			'unlisted',
			'private'
		);
	}

}