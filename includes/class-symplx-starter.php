<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Symplx_Starter {

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public' ] );

        // Load components.
        require_once SYMPLX_STARTER_PLUGIN_DIR . 'includes/admin/class-symplx-starter-admin.php';
        require_once SYMPLX_STARTER_PLUGIN_DIR . 'includes/public/class-symplx-starter-public.php';
        require_once SYMPLX_STARTER_PLUGIN_DIR . 'includes/rest/class-symplx-starter-rest.php';
        require_once SYMPLX_STARTER_PLUGIN_DIR . 'includes/providers/interface-provider.php';
        require_once SYMPLX_STARTER_PLUGIN_DIR . 'includes/jobs/class-symplx-starter-jobs.php';
        require_once SYMPLX_STARTER_PLUGIN_DIR . 'includes/admin/class-symplx-starter-media.php';
        require_once SYMPLX_STARTER_PLUGIN_DIR . 'includes/admin/class-symplx-starter-jobs-page.php';

        // Instantiate components.
        new Symplx_Starter_Admin();
        new Symplx_Starter_Public();
        new Symplx_Starter_REST();
        new Symplx_Starter_Jobs();
        if ( is_admin() ) { new Symplx_Starter_Media_UI(); new Symplx_Starter_Jobs_Page(); }

        // Register post meta used for per-post motion setting.
        add_action( 'init', [ $this, 'register_post_meta' ] );
    }

    public static function activate() {
        // Default options.
        add_option( 'symplx_starter_provider', 'mock' );
        add_option( 'symplx_starter_api_key', '' );
        add_option( 'symplx_replicate_api_token', '', '', 'no' );
        add_option( 'symplx_replicate_model_version', '', '', 'no' );
        add_option( 'symplx_starter_default_mode', 'off' ); // off | select | all
        add_option( 'symplx_starter_default_effect', 'kenburns' );
    }

    public static function deactivate() {
        // Intentionally left blank; no action needed on deactivate.
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'symplx-starter', false, dirname( plugin_basename( SYMPLX_STARTER_PLUGIN_FILE ) ) . '/languages' );
    }

    public function enqueue_admin( $hook ) {
        wp_enqueue_style( 'symplx-starter-admin', SYMPLX_STARTER_PLUGIN_URL . 'assets/admin.css', [], SYMPLX_STARTER_VERSION );
    }

    public function enqueue_public() {
        wp_enqueue_style( 'symplx-starter-public', SYMPLX_STARTER_PLUGIN_URL . 'assets/public.css', [], SYMPLX_STARTER_VERSION );
    }

    public function register_post_meta() {
        register_post_meta( 'post', 'symplx_motion_enable_all', [
            'type'         => 'boolean',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => false,
            'auth_callback'=> function() { return current_user_can( 'edit_posts' ); },
        ] );

        register_post_meta( 'post', 'symplx_motion_default_prompt', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => '',
            'auth_callback'=> function() { return current_user_can( 'edit_posts' ); },
        ] );
    }
}
