<?php
/**
 * This will have code related to action scheduler required API.
 * 
 * @package OneAccess
 */

namespace OneAccess\REST;

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
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST API routes
     *
     * @return void
     */
    public function register_routes(): void {
    }
}