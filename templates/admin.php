<div class="wrap">

	<div id="icon-options-general" class="icon32"><br></div>

	<h2>Youtube Upload</h2>

	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div id="message" class="updated">
			<p>
				Settings have been successfully updated
			</p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['google_auth_successful'] ) && $_GET['google_auth_successful'] ) : ?>
		<div id="message" class="updated">
			<p>
				Authentication with your google account was successful
			</p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['google_auth_successful'] ) && ! $_GET['google_auth_successful'] ) : ?>
		<div id="message" class="error">
			<p>
				There was an error authenticating your google account, please contact the administrator
			</p>
		</div>
	<?php endif; ?>

	<form method="post">

		<table class="formtable">

			<tr>
				<th><label for="client_id">Client ID</label></th>
				<td><input type="text" id="client_id" name="client_id" value="<?php echo WP_Youtube_Upload_Configuration::get_client_id(); ?>" /></td>
			</tr>

			<tr>
				<th><label for="client_secret">Client Secret</label></th>
				<td><input type="text" id="client_secret" name="client_secret" value="<?php echo WP_Youtube_Upload_Configuration::get_client_secret(); ?>" /></td>
			</tr>

			<tr>
				<th><label for="redirect_uri">Redirect uri</label></th>
				<td><input type="text" id="redirect_uri" name="redirect_uri" value="<?php echo WP_Youtube_Upload_Configuration::get_redirect_uri(); ?>"  /></td>
			</tr>

			<tr>
				<th>Authentication</th>
				<td>
					<input type="submit" class="button button-primary" name="youtube_upload_authenticate_submit" value="<?php echo ( WP_Youtube_Upload_Configuration::get_access_token() ) ? 'Reauthenticate' : 'Authenticate' ; ?>" />
					<br /><span class="description">Authenticate after ensuring your Client ID, Client Secret and Redirect uri are correct</span>
				</td>
			</tr>

		</table>

		<?php wp_nonce_field( 'youtube_upload_settings_nonce', 'youtube_upload_settings_nonce' ); ?>
		<input type="hidden" name="page" value="wp-youtube-upload" />

		<p>
			<input type="submit" name="youtube_upload_settings_submit" class="button button-primary" value="Update Settings">
		</p>
	</form>
</div>