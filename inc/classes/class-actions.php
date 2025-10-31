<?php
/**
 * When ever user is created/updated perform action to notify governing site.
 * 
 * @package OneAccess
 */

namespace OneAccess;

use OneAccess\Traits\Singleton;

/**
 * Class Actions
 */
class Actions {

    /**
     * Use Singleton Trait
     */
    use Singleton;

    /**
     * Max retries for failed notifications (Action Scheduler default is 5; override if needed).
     */
    const MAX_RETRIES = 5;

    /**
     * User meta key to track sync status.
     */
    const SYNC_META_KEY = '_oneaccess_synced_to_governing';

    /**
     * Constructor
     */
    protected function __construct() {
        $this->setup_hooks();
    }

    /**
     * Setup hooks
     *
     * @return void
     */
    protected function setup_hooks(): void {
        
        // Perform action on user create/update to schedule notification if not already synced.
        add_action( 'user_register', array( $this, 'handle_user_created' ), 10, 1 );
        add_action( 'profile_update', array( $this, 'handle_user_updated' ), 10, 2 ); // Pass user_id and old_user_data for updates.

        // oneaccess_user_created action to notify governing site about new/changed user.
        add_action( 'oneaccess_user_sync', array( $this, 'notify_governing_site' ), 10, 1 );

        // Optional: Clean up old failed actions periodically (e.g., via a daily cron).
        // add_action( 'wp', array( $this, 'schedule_cleanup_cron' ) );
    }

    /**
     * Handle user created action.
     *
     * @param int $user_id User ID.
     *
     * @return void
     */
    public function handle_user_created( int $user_id ): void {
        $this->schedule_user_sync( $user_id, 'create' );
    }

    /**
     * Handle user updated action (separate to detect changes).
     *
     * @param int    $user_id      User ID.
     * @param object $old_user_data Old user data (from profile_update).
     *
     * @return void
     */
    public function handle_user_updated( int $user_id, $old_user_data ): void {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        // Check if relevant fields changed (e.g., email, roles).
        $changes = $this->detect_user_changes( $user, $old_user_data );
        if ( empty( $changes ) ) {
            return; // No significant changes; skip sync.
        }

        $this->schedule_user_sync( $user_id, 'update', $changes );
    }

    /**
     * Schedule sync action if not already processed.
     *
     * @param int    $user_id User ID.
     * @param string $action  'create' or 'update'.
     * @param array  $changes Optional changes for updates.
     *
     * @return void
     */
    protected function schedule_user_sync( int $user_id, string $action = 'create', array $changes = [] ): void {
        // Avoid duplicates: Check if already synced or pending.
        $synced = get_user_meta( $user_id, self::SYNC_META_KEY, true );
        if ( $synced ) {
            error_log( "OneAccess: User {$user_id} already synced ({$action}). Skipping." );
            return;
        }

        if ( ! function_exists( 'as_schedule_single_action' ) ) {
            error_log( 'OneAccess: Action Scheduler not available. Sync skipped for user ' . $user_id );
            return;
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        // Generate unique action ID to prevent duplicates (based on user_id + action).
        $action_id = 'oneaccess_sync_' . $user_id . '_' . $action . '_' . time();

        // Prepare data.
        $data = array(
            'user_id'    => $user_id,
            'action'     => $action,
            'changes'    => $changes,
            'user_email' => $user->user_email,
            'username'   => $user->user_login,
            'site_url'   => get_site_url(),
            'roles'      => $user->roles,
            'attempt'    => 0, // Track retry count.
        );

        // Schedule with immediate execution + unique ID for idempotency.
        // Use group for organization (e.g., 'oneaccess-syncs').
        $scheduled = as_schedule_single_action(
            time() + 10, // Slight delay to avoid cron overlap during spikes.
            'oneaccess_user_sync',
            [ $data ], // Wrapped as single arg.
            $action_id,
            'oneaccess-syncs' // Group for querying/managing batches.
        );

        if ( $scheduled ) {
            error_log( "OneAccess: Scheduled sync for user {$user_id} ({$action}). Action ID: {$action_id}" );
        } else {
            error_log( "OneAccess: Failed to schedule sync for user {$user_id}. Already pending?" );
        }
    }

    /**
     * Detect changes in user data for updates.
     *
     * @param WP_User $user         Current user.
     * @param object  $old_user_data Old user data.
     *
     * @return array List of changed fields.
     */
    protected function detect_user_changes( $user, $old_user_data ): array {
        $changes = [];
        if ( $user->user_email !== $old_user_data->user_email ) {
            $changes[] = 'email';
        }
        // Add more fields as needed (e.g., roles via wp_get_user_roles or custom).
        // For roles: Compare $user->roles vs stored old roles.
        return $changes;
    }

    /**
     * Notify governing site about new/changed user.
     * Returns true on success (marks complete), false on failure (triggers retry).
     *
     * @param array $args Action args (single array with user data).
     *
     * @return bool Success flag.
     */
    public function notify_governing_site( array $args ): bool {
        $data = $args[0] ?? [];
        if ( empty( $data ) || ! is_array( $data ) || empty( $data['user_id'] ) ) {
            error_log( 'OneAccess: Invalid data for sync notification.' );
            return false;
        }

        $user_id = intval( $data['user_id'] );
        $attempt = intval( $data['attempt'] ?? 0 ) + 1;
        $data['attempt'] = $attempt; // Update for logging/retry.

        // Mark as in-progress to prevent duplicate schedules.
        update_user_meta( $user_id, self::SYNC_META_KEY, 'in-progress' );

        // Prepare payload for REST request.
        $payload = array(
            'action'     => $data['action'],
            'changes'    => $data['changes'] ?? [],
            'user'       => array(
                'id'       => $user_id,
                'email'    => sanitize_email( $data['user_email'] ),
                'username' => sanitize_user( $data['username'] ),
                'roles'    => array_map( 'sanitize_text_field', $data['roles'] ?? [] ),
            ),
            'site_url'   => esc_url_raw( $data['site_url'] ),
            'timestamp'  => current_time( 'mysql' ),
            'attempt'    => $attempt,
        );

        // Send REST request to governing site (replace with your endpoint).
        $governing_url = 'https://governing-site.com/wp-json/oneaccess/v1/users/sync'; // Customize.
        $response = wp_remote_post( $governing_url, array(
            'timeout'   => 30, // Adjust for network latency.
            'body'      => wp_json_encode( $payload ),
            'headers'   => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->get_governing_api_token(), // Implement auth.
            ),
        ) );

        $success = false;
        if ( is_wp_error( $response ) ) {
            $error_msg = $response->get_error_message();
            error_log( "OneAccess: Sync failed for user {$user_id} (attempt {$attempt}): {$error_msg}" );
        } else {
            $code = wp_remote_retrieve_response_code( $response );
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( 200 === $code && isset( $body['success'] ) && $body['success'] ) {
                // Confirmation received: Mark as synced.
                update_user_meta( $user_id, self::SYNC_META_KEY, true );
                error_log( "OneAccess: Sync successful for user {$user_id} (attempt {$attempt}). Confirmation: " . print_r( $body, true ) );
                $success = true;
            } else {
                $error_msg = $body['message'] ?? 'Unknown error';
                error_log( "OneAccess: Governing site rejected sync for user {$user_id} (attempt {$attempt}): {$error_msg} (Code: {$code})" );
            }
        }

        if ( ! $success ) {
            // Check max retries.
            if ( $attempt >= self::MAX_RETRIES ) {
                // Final failure: Mark as failed, notify admin (e.g., email).
                update_user_meta( $user_id, self::SYNC_META_KEY, 'failed' );
                $this->notify_admin_on_failure( $user_id, $data );
                error_log( "OneAccess: Max retries exceeded for user {$user_id}. Marked as failed." );
            } else {
                // Reschedule for retry (AS will handle backoff, but we can manual if needed).
                // For now, return false to let AS retry automatically (exponential backoff: 5min, 15min, etc.).
                error_log( "OneAccess: Retrying sync for user {$user_id} (attempt {$attempt}/" . self::MAX_RETRIES . ")" );
            }
        }

        return $success;
    }

    /**
     * Get API token for governing site (implement based on your auth method).
     *
     * @return string Token.
     */
    protected function get_governing_api_token(): string {
        // e.g., return get_option( 'oneaccess_governing_token' );
        return ''; // Placeholder.
    }

    /**
     * Notify admin on final failure (e.g., via email).
     *
     * @param int   $user_id User ID.
     * @param array $data    User data.
     *
     * @return void
     */
    protected function notify_admin_on_failure( int $user_id, array $data ): void {
        $admin_email = get_option( 'admin_email' );
        $subject = sprintf( 'OneAccess: Failed to sync user %d after %d attempts', $user_id, self::MAX_RETRIES );
        $message = sprintf(
            "User %s (%d) from %s could not be synced to governing site.\n\nData: %s\n\nCheck logs for details.",
            $data['username'] ?? 'Unknown',
            $user_id,
            $data['site_url'] ?? '',
            print_r( $data, true )
        );
        wp_mail( $admin_email, $subject, $message );
    }

    /**
     * Optional: Schedule a daily cleanup cron for old failed actions.
     *
     * @return void
     */
    // public function schedule_cleanup_cron(): void {
    //     if ( ! wp_next_scheduled( 'oneaccess_cleanup_failed_syncs' ) ) {
    //         wp_schedule_event( time(), 'daily', 'oneaccess_cleanup_failed_syncs' );
    //     }
    //     add_action( 'oneaccess_cleanup_failed_syncs', array( $this, 'cleanup_old_failed_syncs' ) );
    // }

    // /**
    //  * Cleanup old failed syncs (e.g., delete user meta after 7 days).
    //  *
    //  * @return void
    //  */
    // public function cleanup_old_failed_syncs(): void {
    //     $users = get_users( array( 'meta_key' => self::SYNC_META_KEY, 'meta_value' => 'failed', 'fields' => 'all' ) );
    //     foreach ( $users as $user ) {
    //         $fail_time = get_user_meta( $user->ID, self::SYNC_META_KEY . '_time', true );
    //         if ( $fail_time && ( time() - strtotime( $fail_time ) > WEEK_IN_SECONDS ) ) {
    //             delete_user_meta( $user->ID, self::SYNC_META_KEY );
    //             delete_user_meta( $user->ID, self::SYNC_META_KEY . '_time' );
    //         }
    //     }
    // }
}
