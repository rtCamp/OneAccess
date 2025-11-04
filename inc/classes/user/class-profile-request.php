<?php
/**
 * Class Profile_Request -- this is to handle profile update requests.
 *
 * @package OneAccess
 */

namespace OneAccess\User;

use OneAccess\Plugin_Configs\Constants;
use OneAccess\Plugin_Configs\DB;
use OneAccess\Traits\Singleton;
use OneAccess\Utils;

/**
 * Class Profile_Request
 */
class Profile_Request {

	/**
	 * Use Singleton trait.
	 */
	use Singleton;

	/**
	 * Protected class constructor
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup WordPress hooks
	 */
	public function setup_hooks(): void {

		// early return if this is not a brand site.
		if ( ! Utils::is_brand_site() ) {
			return;
		}

		// store personal profile update and edit user profile request.
		add_action( 'personal_options_update', array( $this, 'store_profile_update_request' ) );
		add_action( 'edit_user_profile_update', array( $this, 'store_profile_update_request' ) );
	}

	/**
	 * Store profile update request.
	 *
	 * @param int $user_id User ID.
	 */
	public function store_profile_update_request( int $user_id ): void {

		// Prevent multiple executions for the same request.
		static $processed = array();
		if ( isset( $processed[ $user_id ] ) ) {
			return;
		}
		$processed[ $user_id ] = true;

		// get profile request data.
		$profile_request_data = DB::get_pending_profile_request_by_user_id( $user_id );
		if ( ! is_array( $profile_request_data ) ) {
			$profile_request_data = array();
		}

		$request_exists = is_array( $profile_request_data ) && ! empty( $profile_request_data );

		if ( $request_exists ) {
			return;
		}

		// get $_POST data and compare with existing user data & user meta.
		$post_data = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- this is to intercept profile update request before updating into DB.
		$user_data = get_userdata( $user_id );
		$user_meta = get_user_meta( $user_id );

		$updated_metadata = array();
		$fields_to_check  = array(
			'admin_color',
			'first_name',
			'last_name',
			'nickname',
			'facebook',
			'instagram',
			'linkedin',
			'myspace',
			'pinterest',
			'soundcloud',
			'tumblr',
			'wikipedia',
			'twitter',
			'youtube',
			'description',
		);

		foreach ( $fields_to_check as $field ) {
			if ( isset( $post_data[ $field ] ) ) {
				$post_user_data = $post_data[ $field ] ?? '';
				$user_meta_data = $user_meta[ $field ] ?? '';
				if ( is_array( $user_meta_data ) ) {
					$user_meta_data = $user_meta_data[0] ?? '';
				}
				if ( $post_user_data !== $user_meta_data ) {
					$updated_metadata[ $field ] = array(
						'old' => self::sanitize_user_fields( $field, isset( $user_meta[ $field ] ) ? $user_meta[ $field ][0] : '' ) ?? '',
						'new' => self::sanitize_user_fields( $field, $post_data[ $field ] ) ?? '',
					);
					$post_data[ $field ]        = $user_meta[ $field ][0] ?? '';
				}
			}
		}

		$updated_data = array();

		if ( isset( $post_data['display_name'] ) && $post_data['display_name'] !== $user_data->display_name ) {
			$updated_data['display_name'] = array(
				'old' => self::sanitize_user_fields( 'display_name', $user_data->display_name ),
				'new' => self::sanitize_user_fields( 'display_name', $post_data['display_name'] ),
			);
			$post_data['display_name']    = $user_data->display_name;
		}
		if ( isset( $post_data['email'] ) && $post_data['email'] !== $user_data->user_email ) {
			$updated_data['email'] = array(
				'old' => self::sanitize_user_fields( 'email', $user_data->user_email ),
				'new' => self::sanitize_user_fields( 'email', $post_data['email'] ),
			);
			$post_data['email']    = $user_data->user_email;
		}
		if ( isset( $post_data['url'] ) && $post_data['url'] !== $user_data->user_url ) {
			$updated_data['url'] = array(
				'old' => self::sanitize_user_fields( 'url', $user_data->user_url ),
				'new' => self::sanitize_user_fields( 'url', $post_data['url'] ),
			);
			$post_data['url']    = $user_data->user_url;
		}
		// user nicename.
		if ( isset( $post_data['user_nicename'] ) && $post_data['user_nicename'] !== $user_data->user_nicename ) {
			$updated_data['user_nicename'] = array(
				'old' => self::sanitize_user_fields( 'user_nicename', $user_data->user_nicename ),
				'new' => self::sanitize_user_fields( 'user_nicename', $post_data['user_nicename'] ),
			);
			$post_data['user_nicename']    = $user_data->user_nicename;
		}

		$_POST = $post_data;

		if ( empty( $updated_metadata ) && empty( $updated_data ) ) {
			return;
		}

		$requested_by = get_current_user_id();
		if ( $requested_by === $user_id ) {
			$requested_by = ( get_userdata( $requested_by )->display_name ?? 'Unknown' ) . esc_html( ' (Self)' );
		} else {
			$requested_by = ( get_userdata( $requested_by )->display_name ?? 'Unknown' ) . esc_html( ' (Brand Admin)' );
		}

		$profile_request_data[ $user_id ] = array(
			'data'         => $updated_data,
			'user_name'    => get_userdata( $user_id )->display_name ?? get_userdata( $user_id )->user_nicename ?? __( 'Unknown', 'oneaccess' ),
			'user_email'   => get_userdata( $user_id )->user_email ?? 'Unknown',
			'metadata'     => $updated_metadata,
			'requested_by' => $requested_by,
			'status'       => __( 'pending', 'oneaccess' ),
			'user_login'   => get_userdata( $user_id )->user_login ?? 'Unknown',
			'requested_at' => current_time( 'mysql' ),
		);

		DB::add_profile_request( $user_id, $profile_request_data[ $user_id ], 'pending' );
	}

	/**
	 * Sanitize user fields based on field type.
	 *
	 * @param string $field Field name.
	 * @param string $value Field value.
	 *
	 * @return string Sanitized value.
	 */
	private static function sanitize_user_fields( string $field, string $value ): string {
		$sanitized_value = '';
		switch ( $field ) {

			// user email fields.
			case 'email':
				return sanitize_email( $value );

			// user name fields.
			case 'first_name':
			case 'last_name':
			case 'nickname':
			case 'user_nicename':
			case 'display_name':
				return sanitize_text_field( $value );

			// user url fields.
			case 'url':
			case 'website':
			case 'facebook':
			case 'instagram':
			case 'linkedin':
			case 'myspace':
			case 'pinterest':
			case 'soundcloud':
			case 'tumblr':
			case 'wikipedia':
			case 'twitter':
			case 'youtube':
			case 'github':
			case 'tiktok':
				$sanitized_value = esc_url_raw( $value );
				break;

			// user description field.
			case 'description':
			case 'biographical_info':
			case 'bio':
				$sanitized_value = sanitize_textarea_field( $value );
				break;

			// default case for any other fields.
			default:
				$sanitized_value = sanitize_text_field( $value );
				break;
		}
		return $sanitized_value;
	}
}
