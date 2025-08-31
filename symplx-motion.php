<?php
/**
 * Plugin Name: Symplx Motion
 * Description: Turn static images in posts into motion-enhanced media using AI. Enable per-image or entire post.
 * Version: 0.5.0
 * Author: Symplx Studio
 * Text Domain: symplx-motion
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'SYMPLX_STARTER_VERSION', '0.5.0' );
define( 'SYMPLX_STARTER_PLUGIN_FILE', __FILE__ );
define( 'SYMPLX_STARTER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SYMPLX_STARTER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SYMPLX_STARTER_PLUGIN_DIR . 'includes/class-symplx-starter.php';

function symplx_motion() {
    static $instance = null;
    if ( null === $instance ) {
        $instance = new Symplx_Starter();
    }
    return $instance;
}

register_activation_hook( __FILE__, [ 'Symplx_Starter', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Symplx_Starter', 'deactivate' ] );

// Bootstrap plugin.
symplx_motion();
