<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Symplx_Motion {

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public' ] );

        // Load components.
        require_once SYMPLX_MOTION_PLUGIN_DIR . 'includes/admin/class-symplx-starter-admin.php';
        require_once SYMPLX_MOTION_PLUGIN_DIR . 'includes/public/class-symplx-starter-public.php';
        require_once SYMPLX_MOTION_PLUGIN_DIR . 'includes/rest/class-symplx-starter-rest.php';
        require_once SYMPLX_MOTION_PLUGIN_DIR . 'includes/providers/interface-provider.php';
        require_once SYMPLX_MOTION_PLUGIN_DIR . 'includes/jobs/class-symplx-starter-jobs.php';
        require_once SYMPLX_MOTION_PLUGIN_DIR . 'includes/admin/class-symplx-starter-media.php';
        require_once SYMPLX_MOTION_PLUGIN_DIR . 'includes/admin/class-symplx-starter-jobs-page.php';

        // Instantiate components.
        new Symplx_Motion_Admin();
        new Symplx_Motion_Public();
        new Symplx_Motion_REST();
        new Symplx_Motion_Jobs();
        if ( is_admin() ) { new Symplx_Motion_Media_UI(); new Symplx_Motion_Jobs_Page(); }

        // Register post meta used for per-post motion setting.
        add_action( 'init', [ $this, 'register_post_meta' ] );
    }

    public static function activate() {
        // Default options for new installs.
        add_option( 'symplx_motion_provider', 'mock' );
        add_option( 'symplx_motion_default_mode', 'off' ); // off | select | all
        add_option( 'symplx_motion_default_effect', 'kenburns' );
        add_option( 'symplx_motion_max_video_size_mb', 100 );
        add_option( 'symplx_motion_replicate_api_token', '', '', 'no' );
        add_option( 'symplx_motion_replicate_model_version', '', '', 'no' );
        add_option( 'symplx_motion_replicate_image_key', 'input_image' );
        add_option( 'symplx_motion_replicate_fps_key', 'fps' );
        add_option( 'symplx_motion_replicate_frames_key', 'num_frames' );
        add_option( 'symplx_motion_replicate_duration_key', '' );
        add_option( 'symplx_motion_replicate_prompt_key', 'prompt' );
        add_option( 'symplx_motion_replicate_extra_input', '' );

        // Migrate settings from previous symplx-starter versions.
        self::maybe_migrate_options();
    }

    public static function deactivate() {
        // Intentionally left blank; no action needed on deactivate.
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'symplx-motion', false, dirname( plugin_basename( SYMPLX_MOTION_PLUGIN_FILE ) ) . '/languages' );
    }

    public function enqueue_admin( $hook ) {
        wp_enqueue_style( 'symplx-motion-admin', SYMPLX_MOTION_PLUGIN_URL . 'assets/admin.css', [], SYMPLX_MOTION_VERSION );
    }

    public function enqueue_public() {
        wp_enqueue_style( 'symplx-motion-public', SYMPLX_MOTION_PLUGIN_URL . 'assets/public.css', [], SYMPLX_MOTION_VERSION );
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

    private static function maybe_migrate_options() {
        // Map old option keys to new keys.
        $map = [
            'symplx_starter_provider'           => 'symplx_motion_provider',
            'symplx_starter_default_mode'       => 'symplx_motion_default_mode',
            'symplx_starter_default_effect'     => 'symplx_motion_default_effect',
            'symplx_max_video_size_mb'          => 'symplx_motion_max_video_size_mb',
            'symplx_replicate_api_token'        => 'symplx_motion_replicate_api_token',
            'symplx_replicate_model_version'    => 'symplx_motion_replicate_model_version',
            'symplx_replicate_image_key'        => 'symplx_motion_replicate_image_key',
            'symplx_replicate_fps_key'          => 'symplx_motion_replicate_fps_key',
            'symplx_replicate_frames_key'       => 'symplx_motion_replicate_frames_key',
            'symplx_replicate_duration_key'     => 'symplx_motion_replicate_duration_key',
            'symplx_replicate_prompt_key'       => 'symplx_motion_replicate_prompt_key',
            'symplx_replicate_extra_input'      => 'symplx_motion_replicate_extra_input',
        ];
        foreach ( $map as $old => $new ) {
            $marker = '__symplx_no_option__';
            $old_val = get_option( $old, $marker );
            if ( $old_val !== $marker ) {
                $new_val = get_option( $new, $marker );
                if ( $new_val === $marker ) {
                    update_option( $new, $old_val );
                }
            }
        }
    }
}
