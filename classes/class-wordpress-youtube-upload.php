<?php

/**
 * Youtube upload primary class - Handles integration with youtube api and applicable settings
 *
 * Class WP_Youtube_Upload
 */
class WP_Youtube_Upload {

	protected $args;

	function __construct( $args = array() ) {

		$this->args = wp_parse_args( $args, array(
			'client_id'     => WP_Youtube_Upload_Configuration::get_client_id(),
			'client_secret' => WP_Youtube_Upload_Configuration::get_client_secret(),
			'redirect_uri'  => WP_Youtube_Upload_Configuration::get_redirect_uri(),
			'chunk_size'    => WP_Youtube_Upload_Configuration::get_chunk_size(),
			'access_token'  => WP_Youtube_Upload_Configuration::get_access_token(),
			'refresh_token' => WP_Youtube_Upload_Configuration::get_refresh_token()
		) );
	}

	/**
	 * Get an instance arg
	 *
	 * @param $key
	 * @return bool
	 */
	function get_arg( $key ) {

		return ( isset( $this->args[ $key ] ) ) ? $this->args[ $key ] : false;
	}

	/**
	 * Set an instance arg
	 *
	 * @param $key
	 * @param $val
	 */
	protected function set_arg( $key, $val ) {

		$this->args[ $key ] = $val;
	}

	/**
	 * Get the google api wrapper client
	 *
	 * @return bool|Google_Client
	 */
	public function get_client() {

		if ( empty( $this->client ) ) {

			try {

				$client = new Google_Client();

				//General setup
				$client->setClientId( $this->get_arg( 'client_id' ) );
				$client->setClientSecret( $this->get_arg( 'client_secret' ) );
				$client->setRedirectUri( $this->get_arg( 'redirect_uri' ) );

				$client->setAccessType( 'offline' );
				$client->setState( urlencode( wp_create_nonce( 'wp_youtube_upload_get_token' ) ) );
				$client->setScopes( array( 'https://www.googleapis.com/auth/youtube', 'https://www.googleapis.com/auth/youtube.upload','https://www.googleapis.com/auth/youtube.readonly' ) );

				//Set the saved access token
				if ( $this->get_arg( 'access_token' ) ) {
					$client->setAccessToken( $this->get_arg( 'access_token' ) );
				}

				//If we have a refresh token and no valid access token, get one now.
				if ( $this->get_arg( 'refresh_token' ) && ( ! $client->getAccessToken() || $client->isAccessTokenExpired() ) ) {

					$client->refreshToken( $this->get_arg( 'refresh_token' ) );

					$this->set_arg( 'access_token', $client->getAccessToken() );

					WP_Youtube_Upload_Configuration::set_access_token( $client->getAccessToken() );
				}

			} catch ( Exception $e ) {

				trigger_error( 'Error initialising google API client: ' . $e->getMessage() );

				return ! empty( $client ) ? $client : false;
			}

			$this->client = $client;
		}

		return $this->client;
	}

	/**
	 * Get the youtube api wrapper client
	 *
	 * @return Google_Service_YouTube
	 */
	public function get_youtube_client() {

		if ( empty( $this->youtube_client ) ) {

			// Define an object that will be used to make all API requests.
			$youtube = new Google_Service_YouTube( $this->get_client() );

			$this->youtube_client = $youtube;
		}

		return $this->youtube_client;
	}

	/**
	 * Upload a given WP_Youtube_Upload_Attachment to youtube
	 *
	 * @param WP_Youtube_Upload_Attachment $attachment
	 * @return WP_Error|Google_Service_YouTube_Video
	 */
	public function upload_attachment( WP_Youtube_Upload_Attachment $attachment ) {

		$client      = $this->get_client();

		$client->setDefer( true );

		$video_path  = $attachment->get_post()->guid;
		$media       = $this->get_media_file_upload_obj( $attachment );

		try {

			// Read the media file and upload it chunk by chunk.
			$status = false;

			$handle = fopen( $video_path, "rb" );
			$buffer = '';

			while ( ! $status && ! feof( $handle ) ) {

				$buffer .= fread( $handle, $this->get_arg( 'chunk_size' ) );

				if ( strlen( $buffer ) > $this->get_arg( 'chunk_size' ) ) {
					$status = $media->nextChunk( $buffer );
					$buffer = '';
				}
			}

			// finish the partial buffer off
			if ( $buffer > '' ) {
				$status = $media->nextChunk( $buffer );
			}

			fclose( $handle );

		} catch ( Exception $e ) {

			trigger_error( 'Error uploading attachment ' . $attachment->get_post_id() . ' to youtube: ' . $e->getMessage() );

			return new WP_Error( $e->getCode(), $e->getMessage() );
		}

		$client->setDefer( false );

		return $status;
	}

	/**
	 * Updates a previously uploaded video (snippet and status based on current values of the WP_Youtube_Upload_Attachment passed in)
	 *
	 * @param WP_Youtube_Upload_Attachment $attachment
	 * @return bool|Google_Service_YouTube_Video
	 */
	public function update_uploaded_attachment( WP_Youtube_Upload_Attachment $attachment ) {

		if ( ! $attachment->is_uploaded() ) {
			return false;
		}

		$youtube = $this->get_youtube_client();

		try {

			$r = $youtube->videos->listVideos( 'snippet', array( 'id' => $attachment->get_upload_id() ) );

			if ( empty( $r ) ) {
				return false;
			}

			// Since the request specified a video ID, the response only
			// contains one video resource.
			$video = $r[0];

			$video['snippet']['title']        = $attachment->get_title();
			$video['snippet']['description']  = $attachment->get_description();
			$video['snippet']['tags']         = $attachment->get_tags();
			$video['snippet']['categoryId']   = $attachment->get_category_id();

			//todo: ability to set privacy status?
			$r = $youtube->videos->update( "snippet", $video );

		} catch ( Exception $e ) {

			trigger_error( 'Error updating attachment ' . $attachment->get_post_id() . ' on youtube: ' . $e->getMessage() );

			return false;
		}

		return true;
	}

	/**
	 * Get youtube api response for a given uploaded attachment
	 *
	 * @param WP_Youtube_Upload_Attachment $attachment
	 * @return Google_Service_YouTube_Video|bool
	 */
	public function get_uploaded_attachment( WP_Youtube_Upload_Attachment $attachment ) {

		if ( ! $attachment->is_uploaded() ) {
			return false;
		}

		$youtube = $this->get_youtube_client();

		try {

			$r = $youtube->videos->listVideos( 'snippet, status', array( 'id' => $attachment->get_upload_id() ) );

			if ( empty( $r ) ) {
				return false;
			}

		} catch ( Exception $e ) {

			trigger_error( 'Error getting uploaded attachment ' . $attachment->get_post_id() . ' on youtube: ' . $e->getMessage() );

			return false;
		}

		return $r[0];
	}

	/**
	 * Get a media file upload wrapper class object to be used to upload files to youtube
	 *
	 * @param WP_Youtube_Upload_Attachment $attachment
	 * @return Google_Http_MediaFileUpload
	 */
	protected function get_media_file_upload_obj( WP_Youtube_Upload_Attachment $attachment ) {

		$video       = $this->get_video_obj( $attachment );
		$client      = $this->get_client();
		$youtube     = $this->get_youtube_client();

		$insert_request = $youtube->videos->insert( "status,snippet", $video );
		$media = new Google_Http_MediaFileUpload( $client, $insert_request, 'video/*', null, true, $this->get_arg( 'chunk_size' ) );
		$media->setFileSize( $attachment->get_size() );

		return $media;
	}

	/**
	 * Get a video snippet object to be used for the file uploaded to youtube
	 *
	 * @param WP_Youtube_Upload_Attachment $attachment
	 * @return Google_Service_YouTube_VideoSnippet
	 */
	protected function get_video_snippet_obj( WP_Youtube_Upload_Attachment $attachment ) {

		$snippet = new Google_Service_YouTube_VideoSnippet();

		$snippet->setTitle( $attachment->get_title() );
		$snippet->setDescription( $attachment->get_description() );
		$snippet->setTags( $attachment->get_tags() );
		$snippet->setCategoryId( $attachment->get_category_id() );

		return $snippet;
	}

	/**
	 * Get a video status object to be used for the file uploaded to youtube
	 *
	 * @param WP_Youtube_Upload_Attachment $attachment
	 * @return Google_Service_YouTube_VideoStatus
	 */
	protected function get_video_status_obj( WP_Youtube_Upload_Attachment $attachment ) {

		$status = new Google_Service_YouTube_VideoStatus();
		$status->privacyStatus = $attachment->get_privacy();

		return $status;
	}

	/**
	 * Get a video object to be used for the file uploaded to youtube
	 *
	 * @param WP_Youtube_Upload_Attachment $attachment
	 * @return Google_Service_YouTube_Video
	 */
	protected function get_video_obj( WP_Youtube_Upload_Attachment $attachment ) {

		$snippet = $this->get_video_snippet_obj( $attachment );
		$status  = $this->get_video_status_obj( $attachment );

		// Associate the snippet and status objects with a new video resource.
		$video = new Google_Service_YouTube_Video();

		$video->setSnippet( $snippet );
		$video->setStatus( $status );

		return $video;
	}
}