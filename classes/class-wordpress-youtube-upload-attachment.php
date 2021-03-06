<?php

/**
 * Youtube upload attachment class, interfaces WP attachment object with youtube upload
 *
 * Class WP_Youtube_Upload_Attachment
 */
class WP_Youtube_Upload_Attachment {

	/**
	 * Attachment post object
	 *
	 * @var null|WP_Post
	 */
	var $attachment;

	/**
	 * Instantiate with a WP attachment object or ID
	 *
	 * @param $attachment
	 */
	function __construct( $attachment ) {

		$this->attachment = is_numeric( $attachment ) ? get_post( $attachment ) : $attachment;
	}

	/**
	 * Get instance from attachment id or object
	 *
	 * @param $attachment
	 * @return static
	 */
	static function get_instance( $attachment ) {

		return new static( $attachment );
	}

	/**
	 * Get instance from attachment url - needed for filtering video short code
	 *
	 * @param $url
	 * @return bool|WP_Youtube_Upload_Attachment
	 */
	static function get_instance_from_url( $url ) {

		//todo: cleaner way of doing this?

		$rel_url_found = preg_match( '~/\d.*~', $url, $matches );

		if ( ! $rel_url_found ) {
			return false;
		}

		$posts = get_posts( array( 'post_type' => 'attachment', 'meta_key' => '_wp_attached_file', 'meta_value' => ltrim( $matches[0], '/' ) ) );

		if ( empty( $posts ) ) {
			return false;
		}

		$processed = $uploaded = array();

		//support multiple same upload - find the best fit
		foreach ( $posts as $post ) {

			$at = static::get_instance( $post );

			if ( $at->is_processed() ) {
				$processed[] = $at;
			} else if ( $at->is_uploaded() ) {
				$uploaded[] = $at;
			}
		}

		if ( ! empty( $processed ) ) {
			return reset( $processed );
		}

		if ( ! empty( $uploaded ) ) {
			return reset( $uploaded );
		}

		return static::get_instance( end( $posts ) );
	}

	/**
	 * Get the local WP attachment object
	 *
	 * @return null|WP_Post
	 */
	function get_post() {

		return $this->attachment;
	}

	/**
	 * Get the WP attachment object ID
	 *
	 * @return array|mixed
	 */
	function get_post_id() {

		return (int) $this->attachment->ID;
	}

	/**
	 * Get the video's status. Valid statuses are "public", "private" and "unlisted".
	 *
	 * @return mixed|void
	 */
	function get_privacy() {

		return apply_filters( 'wp_youtube_upload_video_privacy', 'private', $this );
	}

	/**
	 * Get the title to be used on youtube
	 *
	 * @return mixed|void
	 */
	function get_title() {

		return apply_filters( 'wp_youtube_upload_video_title', $this->get_post()->post_title, $this );
	}

	/**
	 * Get the description to be used on youtube
	 *
	 * @return mixed|void
	 */
	function get_description() {

		return apply_filters( 'wp_youtube_upload_video_description', wp_strip_all_tags( $this->get_post()->post_content ), $this );
	}


	/**
	 * Get an excerpt with define string length
	 *
	 * @param int $str_length
	 * @return string
	 */
	function get_excerpt( $str_length = 200 ) {

		$excerpt = substr( $this->get_description(), 0, $str_length );
		$excerpt = ( $excerpt !== $this->get_description() ) ? $excerpt . '&hellip;' : $excerpt;

		return apply_filters( 'wp_youtube_upload_video_excerpt', $excerpt, $this );
	}

	/**
	 * Get the tags to be used on youtube
	 *
	 * @return mixed|void
	 */
	function get_tags() {

		return apply_filters( 'wp_youtube_upload_video_tags', array(), $this );
	}

	/**
	 * Get the category ID to be used on youtube
	 *
	 * @return mixed|void
	 */
	function get_category_id() {

		//todo: set proper default
		return apply_filters( 'wp_youtube_upload_video_tags', 22, $this );
	}

	/**
	 * Get the ID of the youtube upload (if already uploaded)
	 *
	 * @return mixed
	 */
	function get_upload_id() {

		return $this->get_meta( 'id' );
	}

	/**
	 * Set the ID of the youtube upload for the attachment
	 *
	 * @param $id
	 */
	function set_upload_id( $id ) {

		$this->set_meta( 'id', $id );
	}

	/**
	 * Get the snippet object of the youtube upload (if already uploaded)
	 *
	 * @return Google_Service_YouTube_VideoSnippet|mixed
	 */
	function get_upload_snippet() {

		return $this->get_meta( 'snippet' );
	}

	/**
	 * Set the snippet object of the youtube upload for the attachment
	 *
	 * @param $snippet
	 */
	function set_upload_snippet( $snippet ) {

		$this->set_meta( 'snippet', $snippet );
	}

	/**
	 * Get the status object of the youtube upload (if already uploaded)
	 *
	 * @return Google_Service_YouTube_VideoStatus|mixed
	 */
	function get_upload_status() {

		return $this->get_meta( 'status' );
	}

	/**
	 * Set the status object of the youtube upload for the attachment
	 *
	 * @param $status
	 */
	function set_upload_status( $status ) {

		$this->set_meta( 'status', $status );
	}

	/**
	 * Set if the attachment is uploading or not
	 *
	 * @param $bool
	 */
	function set_is_uploading( $bool ) {

		if ( $bool ) {
			$this->set_meta( 'is_uploading', ( $bool ) ? '1' : '0' );
		} else {
			$this->delete_meta( 'is_uploading' );
		}
	}

	/**
	 * Get if the attachment is uploading or not
	 *
	 * @return bool
	 */
	function is_uploading() {

		return (bool) $this->get_meta( 'is_uploading' );
	}

	/**
	 * Get if the attachment has been uploaded or not
	 *
	 * @return bool
	 */
	function is_uploaded() {

		return (bool) $this->get_upload_status();
	}

	/**
	 * Get if the attachment is a video or not
	 *
	 * @return bool
	 */
	function is_video() {

		return apply_filters( 'wp_youtube_upload_attachment_is_video', ( strpos( $this->get_post()->post_mime_type, 'video' ) !== false ), $this );
	}

	/**
	 * Get whether or not we should upload the attachment
	 *
	 * @return bool
	 */
	function should_upload() {

		return apply_filters( 'wp_youtube_upload_attachment_should_upload', $this->is_video() && ! $this->is_uploaded() && ! $this->is_uploading(), $this );
	}

	/**
	 * Get whether or not the video is uploaded and processed on youtube - this signifies whether or not the video is ready to be viewed on youtube
	 *
	 * @return bool
	 */
	function is_processed() {

		$status = $this->get_upload_status();

		return ( $status && is_object( $status ) && $status->getUploadStatus() === 'processed' );
	}

	/**
	 * Refreshes the upload data by calling the youtube api and getting the response for the uploaded attachment
	 *
	 * @return bool
	 */
	function refresh_upload_data() {

		if ( ! $this->is_uploaded() ) {
			return false;
		}

		$yt     = new WP_Youtube_Upload();
		$data   = $yt->get_uploaded_attachment( $this );

		if ( ! $data ) {
			return false;
		}

		$this->set_upload_snippet( $data->getSnippet() );
		$this->set_upload_status( $data->getStatus() );

		if ( $data->getStatus()->getUploadStatus() === 'processed' ) {
			$this->save_thumbnail_locally();
		}

		do_action( 'wp_youtube_upload_attachment_upload_data_updated', $this );

		return true;
	}

	function save_thumbnail_locally() {

		$snippet = $this->get_upload_snippet();

		$file = download_url( $image_url = $snippet->getThumbnails()->getHigh()->getUrl() );

		if ( is_wp_error( $file ) ) {
			return $file;
		}

		$file_array             = array();
		$file_array['name']     = md5( $file ) . '-' . strtok( pathinfo( $image_url, PATHINFO_BASENAME ), '?' );
		$file_array['tmp_name'] = $file;

		// do the validation and storage stuff
		$return = wp_handle_sideload( $file_array, array( 'test_form' => false ) );

		if ( is_wp_error( $return ) ) {
			return $return;
		}

		if ( ! empty( $return['error'] ) ) {
			return new WP_Error( 'wp_handle_upload_error', $return['error'] );
		}

		$return['path'] = _wp_relative_upload_path( $return['file'] );

		update_post_meta( $this->attachment->ID, '_youtube_thumbnail', $return );
	}

	/**
	 * Upload the attachment to youtube
	 */
	function upload() {

		if ( $this->is_uploading() ) {
			return false;
		}

		$this->delete_meta( 'youtube_last_error' );
		$this->set_is_uploading( true );

		$yt     = new WP_Youtube_Upload();
		$status = $yt->upload_attachment( $this );

		if ( is_wp_error( $status ) ) {

			$this->set_is_uploading( false );
			$this->set_meta( 'youtube_last_error', $status );
			return false;
		}

		$this->set_upload_id( $status->getId() );
		$this->set_upload_snippet( $status->getSnippet() );
		$this->set_upload_status( $status->getStatus() );

		$this->set_is_uploading( false );

		do_action( 'wp_youtube_upload_attachment_upload_complete', $this );

		return true;
	}

	/**
	 * Update the attachment if it already exists on youtube
	 */
	function update() {

		if ( ! $this->is_uploaded() ) {
			return false;
		}

		$yt     = new WP_Youtube_Upload();
		$status = $yt->update_uploaded_attachment( $this );

		do_action( 'wp_youtube_upload_attachment_update_complete', $this );

		return ( $status );
	}

	/**
	 * Get the size of the attachment
	 *
	 * @return mixed
	 */
	function get_size() {

		//todo:: cleaner method of recovering file size from aws?

		if ( $size = filesize( get_attached_file( $this->attachment->ID ) ) ) {
			var_dump($size);
			return $size;
		}

		$ch = curl_init( $this->get_post()->guid );

		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
		curl_setopt( $ch, CURLOPT_HEADER, TRUE );
		curl_setopt( $ch, CURLOPT_NOBODY, TRUE );

		curl_exec( $ch );
		$size = curl_getinfo( $ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD );

		curl_close( $ch );

		return $size;
	}

	/**
	 * Set meta for the attachment with wp_youtube_upload_ prefix
	 *
	 * @param $key
	 * @param $value
	 */
	protected function set_meta( $key, $value ) {

		update_post_meta( $this->get_post_id(), 'wp_youtube_upload_' . $key, $value );
	}

	/**
	 * Get meta for the attachment with wp_youtube_upload_ prefix
	 *
	 * @param $key
	 * @return mixed
	 */
	public function get_meta( $key ) {

		return get_post_meta( $this->get_post_id(), 'wp_youtube_upload_' . $key, true );
	}

	/**
	 * Delete meta for the attachment with wp_youtube_upload_ prefix
	 *
	 * @param $key
	 * @param string $value
	 */
	protected function delete_meta( $key, $value = '' ) {

		delete_post_meta(  $this->get_post_id(), 'wp_youtube_upload_' . $key, $value );
	}

	/**
	 * Get a Google_Service_YouTube_Thumbnail object for the attachment
	 *
	 * @param array $size
	 * @return bool|Google_Service_YouTube_Thumbnail
	 */
	function get_thumbnail( $size = array() ) {

		if ( ! $meta = get_post_meta( $this->attachment->ID, '_youtube_thumbnail', true ) ) {
			return '';
		}

		$upload_dir = wp_upload_dir();

		return apply_filters( 'wp_youtube_thumbnail_url', trailingslashit( $upload_dir['baseurl'] ) . $meta['path'], $size );
	}

	/**
	 * Get thumbnail html for the attachment
	 *
	 * @param array $args
	 * @return string
	 */
	function get_thumbnail_html( $args = array() ) {

		$thumbnail = $this->get_thumbnail( $args );

		if (  $thumbnail ) {

			ob_start(); ?>

			<span class="video-thumbnail" href="<?php echo get_the_permalink( $this->get_post_id() ); ?>" style="display: inline-block; <?php echo ! empty( $args['height'] ) ? 'height: ' . $args['height'] . 'px;' : '' ?> <?php echo ! empty( $args['width'] ) ? 'width: ' . $args['width'] . 'px;' : '' ?>">
				<img src="<?php echo esc_url( $thumbnail ) ?>" height="<?php echo ! empty( $args['height'] ) ? $args['height'] : '' ?>" width="<?php echo ! empty( $args['width'] ) ? $args['width'] : '' ?>" >
			</span>

			<?php $contents = ob_get_clean();

		} else {

			$contents = '';
		}

		return apply_filters( 'wp_youtube_upload_attachment_thumbnail_html', $contents, $args, $this );
	}

	/**
	 * Get the embed code html for the youtube upload
	 *
	 * @param array $args
	 * @return string
	 */
	function get_embed_html( $args = array() ) {

		$args = wp_parse_args( $args, array(
			'width'           => '640',
			'height'          => '360',
			'rel'             => '0',
			'modestbranding'  => '1',
			'enablejsapi'     => '1',
			'autohide'        => '1',
			'iv_load_policy'  => '3',
			'fs'              => '1'
  		) );

		if ( $this->is_uploaded() ) {

			ob_start(); ?>

			<iframe id="<?php echo esc_attr( 'youtube-video-' . $this->attachment->ID ) ?>" class="wp-youtube-video" width="<?php echo esc_attr( $args['width'] ); ?>" height="<?php echo esc_attr( $args['height'] ); ?>" src="<?php echo esc_url( add_query_arg( $args, 'https://www.youtube.com/embed/' . $this->get_upload_id() ) ); ?>" frameborder="0" allowfullscreen></iframe>

			<?php $content = ob_get_clean();

		} else {

			$content = '';
		}

		return apply_filters( 'wp_youtube_upload_attachment_embed_html', $content, $args, $this );
	}

}