<?php
/**
 * When running from GitHub repository, show admin notice to build assets and install composer dependencies.
 *
 * @package OneAccess
 */

?>

<div class="notice notice-error">
	<p>
		<?php
		printf(
			/* translators: %s is the plugin name. */
			esc_html__( 'You are running the %s plugin from the GitHub repository. Please build the assets and install composer dependencies to use the plugin.', 'oneaccess' ),
			'<strong>' . esc_html__( 'OneAccess', 'oneaccess' ) . '</strong>'
		);
		?>
	</p>
	<p>
		<?php
		printf(
			/* translators: %s is the command to run. */
			esc_html__( 'Run the following commands in the plugin directory: %s', 'oneaccess' ),
			'<code>composer install && npm install && npm run build:prod</code>'
		);
		?>
	<p>
		<?php
		printf(
			/* translators: %s is the plugin name. */
			esc_html__( 'Please refer to the %s for more information.', 'oneaccess' ),
			sprintf(
				'<a href="%s" target="_blank">%s</a>',
				esc_url( 'https://github.com/rtCamp/OneAccess' ),
				esc_html__( 'OneAccess GitHub repository', 'oneaccess' )
			)
		);
		?>
	</p>
</div>