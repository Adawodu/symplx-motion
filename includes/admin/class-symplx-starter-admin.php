<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Symplx_Starter_Admin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_post_metabox' ] );
        add_action( 'save_post', [ $this, 'save_post_meta' ] );
        add_action( 'admin_post_symplx_test_provider', [ $this, 'handle_test_provider' ] );
        add_action( 'admin_post_symplx_apply_replicate_preset', [ $this, 'handle_apply_replicate_preset' ] );
        add_action( 'admin_post_symplx_generate_post_images', [ $this, 'handle_generate_post_images' ] );
        add_action( 'admin_notices', [ $this, 'maybe_bulk_notice' ] );
    }

    public function add_menu() {
        add_options_page(
            __( 'Symplx Motion', 'symplx-motion' ),
            __( 'Symplx Motion', 'symplx-motion' ),
            'manage_options',
            'symplx-motion',
            [ $this, 'render_settings' ]
        );
    }

    public function register_settings() {
        register_setting( 'symplx_starter', 'symplx_starter_provider', [
            'sanitize_callback' => function( $v ) { return in_array( $v, [ 'mock', 'replicate' ], true ) ? $v : 'mock'; },
            'default'           => 'mock',
        ] );

        // Generic API key no longer used; provider-specific keys below.

        register_setting( 'symplx_starter', 'symplx_starter_default_mode', [
            'sanitize_callback' => function( $v ) { return in_array( $v, [ 'off', 'select', 'all' ], true ) ? $v : 'off'; },
            'default'           => 'off',
        ] );

        register_setting( 'symplx_starter', 'symplx_starter_default_effect', [
            'sanitize_callback' => function( $v ) { return in_array( $v, [ 'kenburns', 'parallax' ], true ) ? $v : 'kenburns'; },
            'default'           => 'kenburns',
        ] );

        register_setting( 'symplx_starter', 'symplx_max_video_size_mb', [
            'sanitize_callback' => function( $v ) {
                $n = absint( $v );
                return $n > 0 ? $n : 100; // default 100MB
            },
            'default'           => 100,
        ] );

        register_setting( 'symplx_starter', 'symplx_replicate_api_token', [
            'sanitize_callback' => function( $value ) {
                $v = is_string( $value ) ? trim( $value ) : '';
                if ( $v === '' ) return get_option( 'symplx_replicate_api_token', '' ); // keep existing
                if ( strtolower( $v ) === 'reset' ) return '';
                return sanitize_text_field( $v );
            },
            'default'           => '',
        ] );

        register_setting( 'symplx_starter', 'symplx_replicate_model_version', [
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );

        add_settings_section(
            'symplx_starter_main',
            __( 'Main Settings', 'symplx-starter' ),
            '__return_false',
            'symplx_starter'
        );

        add_settings_field(
            'symplx_starter_provider',
            __( 'Provider', 'symplx-starter' ),
            [ $this, 'field_provider' ],
            'symplx_starter',
            'symplx_starter_main'
        );

        add_settings_field(
            'symplx_replicate_api_token',
            __( 'Replicate API Token', 'symplx-starter' ),
            [ $this, 'field_replicate_token' ],
            'symplx_starter',
            'symplx_starter_main'
        );

        add_settings_field(
            'symplx_replicate_model_version',
            __( 'Replicate Model Version', 'symplx-starter' ),
            [ $this, 'field_replicate_version' ],
            'symplx_starter',
            'symplx_starter_main'
        );

        // Replicate advanced input mapping (for models like bytedance/seedance-1-pro)
        register_setting( 'symplx_starter', 'symplx_replicate_image_key', [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'input_image' ] );
        register_setting( 'symplx_starter', 'symplx_replicate_fps_key', [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'fps' ] );
        register_setting( 'symplx_starter', 'symplx_replicate_frames_key', [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'num_frames' ] );
        register_setting( 'symplx_starter', 'symplx_replicate_duration_key', [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ] );
        register_setting( 'symplx_starter', 'symplx_replicate_prompt_key', [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'prompt' ] );
        register_setting( 'symplx_starter', 'symplx_replicate_extra_input', [
            'sanitize_callback' => function( $v ) {
                $v = is_string( $v ) ? trim( $v ) : '';
                if ( $v === '' ) return '';
                $decoded = json_decode( $v, true );
                return is_array( $decoded ) ? wp_json_encode( $decoded ) : '';
            },
            'default' => '',
        ] );

        add_settings_field(
            'symplx_replicate_input_mapping',
            __( 'Replicate Input Mapping', 'symplx-starter' ),
            [ $this, 'field_replicate_input_mapping' ],
            'symplx_starter',
            'symplx_starter_main'
        );

        add_settings_field(
            'symplx_starter_default_mode',
            __( 'Default Mode', 'symplx-starter' ),
            [ $this, 'field_default_mode' ],
            'symplx_starter',
            'symplx_starter_main'
        );

        add_settings_field(
            'symplx_starter_default_effect',
            __( 'Default Effect', 'symplx-starter' ),
            [ $this, 'field_default_effect' ],
            'symplx_starter',
            'symplx_starter_main'
        );

        add_settings_field(
            'symplx_max_video_size_mb',
            __( 'Max Video Size (MB)', 'symplx-starter' ),
            [ $this, 'field_max_video_size' ],
            'symplx_starter',
            'symplx_starter_main'
        );
    }

    public function field_provider() {
        $value = get_option( 'symplx_starter_provider', 'mock' );
        echo '<select name="symplx_starter_provider">';
        echo '<option value="mock"' . selected( $value, 'mock', false ) . '>Mock (CSS-only)</option>';
        echo '<option value="replicate"' . selected( $value, 'replicate', false ) . '>Replicate (Stable Video Diffusion)</option>';
        echo '</select>';
        echo '<p class="description">' . esc_html__( 'Mock provider uses CSS to simulate motion. Replicate uses hosted models for image→video. If Action Scheduler is installed, background polling runs via it; otherwise WP‑Cron is used.', 'symplx-starter' ) . '</p>';
    }

    public function field_replicate_token() {
        $stored = get_option( 'symplx_replicate_api_token', '' );
        // Do not echo the token back. Leave blank to keep, enter new to replace, type "reset" to clear.
        echo '<input type="password" class="regular-text" name="symplx_replicate_api_token" value="" autocomplete="off" placeholder="••••••" />';
        echo '<p class="description">' . esc_html__( 'Enter to set/replace. Leave blank to keep current. Type "reset" to clear.', 'symplx-starter' ) . '</p>';
    }

    public function field_replicate_version() {
        $value = get_option( 'symplx_replicate_model_version', '' );
        echo '<input type="text" class="regular-text" name="symplx_replicate_model_version" value="' . esc_attr( $value ) . '" />';
        echo '<p class="description">' . esc_html__( 'Replicate model version (e.g., stability-ai/stable-video-diffusion-img2vid:<hash>).', 'symplx-starter' ) . '</p>';
    }

    public function field_replicate_input_mapping() {
        $image = get_option( 'symplx_replicate_image_key', 'input_image' );
        $fps = get_option( 'symplx_replicate_fps_key', 'fps' );
        $frames = get_option( 'symplx_replicate_frames_key', 'num_frames' );
        $duration = get_option( 'symplx_replicate_duration_key', '' );
        $prompt = get_option( 'symplx_replicate_prompt_key', 'prompt' );
        $extra = get_option( 'symplx_replicate_extra_input', '' );
        echo '<p><label>' . esc_html__( 'Image key', 'symplx-starter' ) . ' <input type="text" name="symplx_replicate_image_key" value="' . esc_attr( $image ) . '" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'FPS key', 'symplx-starter' ) . ' <input type="text" name="symplx_replicate_fps_key" value="' . esc_attr( $fps ) . '" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'Frames key', 'symplx-starter' ) . ' <input type="text" name="symplx_replicate_frames_key" value="' . esc_attr( $frames ) . '" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'Duration key (optional)', 'symplx-starter' ) . ' <input type="text" name="symplx_replicate_duration_key" value="' . esc_attr( $duration ) . '" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'Prompt key', 'symplx-starter' ) . ' <input type="text" name="symplx_replicate_prompt_key" value="' . esc_attr( $prompt ) . '" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'Extra input JSON', 'symplx-starter' ) . '<br/>';
        echo '<textarea name="symplx_replicate_extra_input" rows="4" class="large-text code" placeholder="{\n  &quot;strength&quot;: 0.7\n}">' . esc_textarea( $extra ) . '</textarea></label></p>';
        echo '<p class="description">' . esc_html__( 'For bytedance/seedance-1-pro, set Image key to "image". If the model expects a different frames/duration key, set it here. Extra input JSON merges into the payload.', 'symplx-starter' ) . '</p>';
    }

    public function field_default_mode() {
        $value = get_option( 'symplx_starter_default_mode', 'off' );
        echo '<select name="symplx_starter_default_mode">';
        foreach ( [ 'off' => 'Off', 'select' => 'Select images', 'all' => 'All images in post' ] as $k => $label ) {
            echo '<option value="' . esc_attr( $k ) . '"' . selected( $value, $k, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__( 'Default behavior for new posts. Authors can override per post or per image.', 'symplx-starter' ) . '</p>';
    }

    public function field_default_effect() {
        $value = get_option( 'symplx_starter_default_effect', 'kenburns' );
        echo '<select name="symplx_starter_default_effect">';
        foreach ( [ 'kenburns' => 'Ken Burns', 'parallax' => 'Parallax' ] as $k => $label ) {
            echo '<option value="' . esc_attr( $k ) . '"' . selected( $value, $k, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__( 'Visual effect used when motion is enabled and no AI asset is available.', 'symplx-starter' ) . '</p>';
    }

    public function field_max_video_size() {
        $value = (int) get_option( 'symplx_max_video_size_mb', 100 );
        echo '<input type="number" min="1" max="2048" name="symplx_max_video_size_mb" value="' . esc_attr( $value ) . '" />';
        echo '<p class="description">' . esc_html__( 'Reject generated videos larger than this size for security/performance. Default 100MB.', 'symplx-starter' ) . '</p>';
    }

    public function render_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Symplx Motion Settings', 'symplx-starter' ) . '</h1>';

        // Display provider test notice if present
        if ( isset( $_GET['symplx_test_key'] ) ) {
            $key = sanitize_text_field( wp_unslash( $_GET['symplx_test_key'] ) );
            $notice = get_transient( $key );
            if ( $notice && is_array( $notice ) ) {
                $class = ( $notice['type'] ?? 'info' ) === 'success' ? 'notice-success' : 'notice-error';
                echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible">';
                foreach ( (array) ( $notice['messages'] ?? [] ) as $msg ) {
                    echo '<p>' . esc_html( $msg ) . '</p>';
                }
                echo '</div>';
                delete_transient( $key );
            }
        }
        echo '<form method="post" action="options.php">';
        settings_fields( 'symplx_starter' );
        do_settings_sections( 'symplx_starter' );
        submit_button();
        echo '</form>';

        // Presets for Replicate input mapping
        echo '<hr style="margin:20px 0;" />';
        echo '<h2>' . esc_html__( 'Replicate Presets', 'symplx-starter' ) . '</h2>';
        echo '<p>' . esc_html__( 'Quickly apply recommended input mappings for popular models.', 'symplx-starter' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;gap:8px;align-items:center;">';
        echo '<input type="hidden" name="action" value="symplx_apply_replicate_preset" />';
        wp_nonce_field( 'symplx_apply_replicate_preset' );
        echo '<label for="symplx_preset">' . esc_html__( 'Preset', 'symplx-starter' ) . '</label>';
        echo '<select id="symplx_preset" name="preset">';
        echo '<option value="svd">' . esc_html__( 'Stable Video Diffusion (img2vid)', 'symplx-starter' ) . '</option>';
        echo '<option value="seedance">' . esc_html__( 'Seedance (bytedance/seedance-1-pro)', 'symplx-starter' ) . '</option>';
        echo '<option value="minimax">' . esc_html__( 'MiniMax (minimax/video-01)', 'symplx-starter' ) . '</option>';
        echo '</select>';
        submit_button( __( 'Apply Preset', 'symplx-starter' ), 'secondary', 'submit', false );
        echo '</form>';

        // Provider connection test form (non-destructive)
        echo '<hr style="margin:20px 0;" />';
        echo '<h2>' . esc_html__( 'Provider Connection Test', 'symplx-starter' ) . '</h2>';
        echo '<p>' . esc_html__( 'Verify credentials and model configuration without generating a job.', 'symplx-starter' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="symplx_test_provider" />';
        wp_nonce_field( 'symplx_test_provider' );
        submit_button( __( 'Test Provider Connection', 'symplx-starter' ), 'secondary' );
        echo '</form>';
        echo '</div>';
    }

    public function handle_apply_replicate_preset() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permission denied', 'symplx-starter' ) );
        }
        check_admin_referer( 'symplx_apply_replicate_preset' );
        $preset = isset( $_POST['preset'] ) ? sanitize_text_field( wp_unslash( $_POST['preset'] ) ) : '';
        $messages = [];
        switch ( $preset ) {
            case 'seedance':
                update_option( 'symplx_replicate_image_key', 'image' );
                update_option( 'symplx_replicate_fps_key', 'fps' );
                update_option( 'symplx_replicate_frames_key', '' );
                update_option( 'symplx_replicate_duration_key', 'duration' );
                update_option( 'symplx_replicate_prompt_key', 'prompt' );
                update_option( 'symplx_replicate_extra_input', '' );
                $messages[] = __( 'Applied Seedance preset: image key set to "image", using fps + duration.', 'symplx-starter' );
                break;
            case 'minimax':
                // MiniMax video-01 commonly accepts image + duration-like controls; adjust as needed per model docs.
                update_option( 'symplx_replicate_image_key', 'image' );
                update_option( 'symplx_replicate_fps_key', 'fps' );
                update_option( 'symplx_replicate_frames_key', '' );
                update_option( 'symplx_replicate_duration_key', 'duration' );
                update_option( 'symplx_replicate_prompt_key', 'prompt' );
                update_option( 'symplx_replicate_extra_input', '' );
                $messages[] = __( 'Applied MiniMax preset: image key set to "image", using fps + duration. Update model version to minimax/video-01:<version>.', 'symplx-starter' );
                break;
            case 'svd':
            default:
                update_option( 'symplx_replicate_image_key', 'input_image' );
                update_option( 'symplx_replicate_fps_key', 'fps' );
                update_option( 'symplx_replicate_frames_key', 'num_frames' );
                update_option( 'symplx_replicate_duration_key', '' );
                update_option( 'symplx_replicate_prompt_key', 'prompt' );
                update_option( 'symplx_replicate_extra_input', '' );
                $messages[] = __( 'Applied SVD preset: input_image, fps and num_frames mapping set.', 'symplx-starter' );
                break;
        }
        $key = 'symplx_preset_' . wp_generate_password( 8, false, false );
        set_transient( $key, [ 'type' => 'success', 'messages' => $messages ], 60 );
        $redirect = add_query_arg( [ 'page' => 'symplx-starter', 'symplx_test_key' => $key ], admin_url( 'options-general.php' ) );
        wp_safe_redirect( $redirect );
        exit;
    }

    public function handle_generate_post_images() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( __( 'Permission denied', 'symplx-starter' ) );
        }
        $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
        if ( ! $post_id || ! wp_verify_nonce( (string) $_GET['_wpnonce'], 'symplx_generate_post_images_' . $post_id ) ) {
            wp_die( __( 'Invalid request', 'symplx-starter' ) );
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_safe_redirect( admin_url( 'edit.php' ) );
            exit;
        }

        require_once SYMPLX_STARTER_PLUGIN_DIR . 'includes/providers/interface-provider.php';
        require_once SYMPLX_STARTER_PLUGIN_DIR . 'includes/jobs/class-symplx-starter-jobs.php';
        $provider = Symplx_Motion_Providers_Registry::get();
        if ( ! $provider ) {
            wp_safe_redirect( add_query_arg( [ 'post' => $post_id, 'action' => 'edit', 'symplx_bulk' => 1, 'ok' => 0, 'fail' => 0, 'reason' => 'noprovider' ], admin_url( 'post.php' ) ) );
            exit;
        }

        // Gather image attachment IDs: from content (wp-image-#) and from direct attachments.
        $ids = [];
        if ( preg_match_all( '/wp-image-(\d+)/', (string) $post->post_content, $m ) ) {
            $ids = array_map( 'absint', $m[1] );
        }
        $children = get_children( [ 'post_parent' => $post_id, 'post_type' => 'attachment', 'post_mime_type' => 'image', 'fields' => 'ids' ] );
        if ( $children ) { $ids = array_merge( $ids, array_map( 'absint', $children ) ); }
        $ids = array_values( array_unique( $ids ) );

        $ok = 0; $fail = 0;
        foreach ( $ids as $att_id ) {
            if ( ! $att_id || ! wp_attachment_is_image( $att_id ) ) { continue; }
            $res = $provider->create_job( $att_id, [] );
            if ( is_wp_error( $res ) ) {
                update_post_meta( $att_id, '_symplx_motion_status', 'failed' );
                update_post_meta( $att_id, '_symplx_motion_error', $res->get_error_message() );
                $fail++;
                continue;
            }
            $job_id = $res['job_id'] ?? '';
            $status = $res['status'] ?? 'queued';
            update_post_meta( $att_id, '_symplx_motion_provider', Symplx_Motion_Providers_Registry::get_active_slug() );
            update_post_meta( $att_id, '_symplx_motion_job_id', $job_id );
            update_post_meta( $att_id, '_symplx_motion_status', $status );
            if ( isset( $res['raw'] ) ) {
                update_post_meta( $att_id, '_symplx_motion_last_raw', wp_json_encode( $res['raw'] ) );
            }
            $now = current_time( 'mysql', true );
            update_post_meta( $att_id, '_symplx_motion_created_at', $now );
            update_post_meta( $att_id, '_symplx_motion_updated_at', $now );

            // Save resolved model/preset
            $resolved_model = get_post_meta( $post_id, 'symplx_motion_model_version', true );
            if ( ! $resolved_model ) { $resolved_model = get_option( 'symplx_replicate_model_version', '' ); }
            $resolved_preset = get_post_meta( $post_id, 'symplx_motion_mapping_preset', true );
            update_post_meta( $att_id, '_symplx_motion_model_version_resolved', $resolved_model );
            if ( $resolved_preset ) { update_post_meta( $att_id, '_symplx_motion_preset_resolved', $resolved_preset ); }

            Symplx_Starter_Jobs::schedule_check( $att_id, 30 );
            $ok++;
        }

        wp_safe_redirect( add_query_arg( [ 'post' => $post_id, 'action' => 'edit', 'symplx_bulk' => 1, 'ok' => $ok, 'fail' => $fail ], admin_url( 'post.php' ) ) );
        exit;
    }

    public function maybe_bulk_notice() {
        if ( ! isset( $_GET['symplx_bulk'], $_GET['post'] ) ) return;
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || 'post' !== $screen->base ) return;
        $ok = isset( $_GET['ok'] ) ? absint( $_GET['ok'] ) : 0;
        $fail = isset( $_GET['fail'] ) ? absint( $_GET['fail'] ) : 0;
        $msg = sprintf( _n( '%d job started.', '%d jobs started.', $ok, 'symplx-starter' ), $ok );
        if ( $fail ) {
            $msg .= ' ' . sprintf( _n( '%d failed.', '%d failed.', $fail, 'symplx-starter' ), $fail );
        }
        echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
    }

    public function handle_test_provider() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permission denied', 'symplx-starter' ) );
        }
        check_admin_referer( 'symplx_test_provider' );

        $messages = [];
        $type = 'success';

        $provider = get_option( 'symplx_starter_provider', 'mock' );
        if ( 'replicate' === $provider ) {
            $token = get_option( 'symplx_replicate_api_token', '' );
            $version = get_option( 'symplx_replicate_model_version', '' );
            if ( ! $token ) {
                $messages[] = __( 'Replicate token is missing.', 'symplx-starter' );
                $type = 'error';
            } else {
                // Test token via /v1/account (non-billable)
                $res = wp_remote_get( 'https://api.replicate.com/v1/account', [
                    'headers' => [ 'Authorization' => 'Token ' . $token ],
                    'timeout' => 20,
                ] );
                if ( is_wp_error( $res ) ) {
                    $messages[] = sprintf( __( 'Token check failed: %s', 'symplx-starter' ), $res->get_error_message() );
                    $type = 'error';
                } else {
                    $code = wp_remote_retrieve_response_code( $res );
                    if ( $code === 200 ) {
                        $messages[] = __( 'Replicate token OK.', 'symplx-starter' );
                    } elseif ( $code === 401 ) {
                        $messages[] = __( 'Replicate token invalid (401).', 'symplx-starter' );
                        $type = 'error';
                    } else {
                        $messages[] = sprintf( __( 'Replicate token check returned HTTP %d.', 'symplx-starter' ), $code );
                        if ( $code >= 400 ) $type = 'error';
                    }
                }
            }

            if ( $version ) {
                // If version looks like owner/model:version, verify existence.
                if ( preg_match( '/^([\w-]+)\/([\w-]+):([\w-]+)/', $version, $m ) ) {
                    $owner = $m[1]; $model = $m[2]; $ver = $m[3];
                    $url = sprintf( 'https://api.replicate.com/v1/models/%s/%s/versions/%s', rawurlencode( $owner ), rawurlencode( $model ), rawurlencode( $ver ) );
                    $res2 = wp_remote_get( $url, [ 'headers' => [ 'Authorization' => 'Token ' . $token ], 'timeout' => 20 ] );
                    if ( is_wp_error( $res2 ) ) {
                        $messages[] = sprintf( __( 'Model version check failed: %s', 'symplx-starter' ), $res2->get_error_message() );
                        $type = 'error';
                    } else {
                        $code2 = wp_remote_retrieve_response_code( $res2 );
                        if ( $code2 === 200 ) {
                            $messages[] = __( 'Replicate model version found.', 'symplx-starter' );
                        } elseif ( $code2 === 404 ) {
                            $messages[] = __( 'Model/version not found (404). Check owner/model and version hash.', 'symplx-starter' );
                            $type = 'error';
                        } else {
                            $messages[] = sprintf( __( 'Model version check returned HTTP %d.', 'symplx-starter' ), $code2 );
                            if ( $code2 >= 400 ) $type = 'error';
                        }
                    }
                } else {
                    // Likely a bare version id; acceptable for create calls, cannot validate without owner/model
                    $messages[] = __( 'Model version format not owner/model:version. Will attempt to use as a raw version id.', 'symplx-starter' );
                }
            } else {
                $messages[] = __( 'Replicate model version is empty.', 'symplx-starter' );
                $type = 'error';
            }
        } else {
            $messages[] = __( 'Using Mock provider: no external connection required.', 'symplx-starter' );
        }

        $key = 'symplx_test_' . wp_generate_password( 8, false, false );
        set_transient( $key, [ 'type' => $type === 'error' ? 'error' : 'success', 'messages' => $messages ], 60 );
        $redirect = add_query_arg( [ 'page' => 'symplx-starter', 'symplx_test_key' => $key ], admin_url( 'options-general.php' ) );
        wp_safe_redirect( $redirect );
        exit;
    }

    public function add_post_metabox() {
        add_meta_box(
            'symplx_motion_box',
            __( 'Symplx Motion', 'symplx-starter' ),
            [ $this, 'render_post_metabox' ],
            [ 'post' ],
            'side',
            'default'
        );
    }

    public function render_post_metabox( $post ) {
        wp_nonce_field( 'symplx_motion_save', 'symplx_motion_nonce' );
        $enable = (bool) get_post_meta( $post->ID, 'symplx_motion_enable_all', true );
        $prompt = (string) get_post_meta( $post->ID, 'symplx_motion_default_prompt', true );
        $model_version = (string) get_post_meta( $post->ID, 'symplx_motion_model_version', true );
        $mapping_preset = (string) get_post_meta( $post->ID, 'symplx_motion_mapping_preset', true );
        echo '<p><label><input type="checkbox" name="symplx_motion_enable_all" value="1"' . checked( $enable, true, false ) . '> ' . esc_html__( 'Enable motion for all images in this post', 'symplx-starter' ) . '</label></p>';
        echo '<p><label>' . esc_html__( 'Default prompt (optional)', 'symplx-starter' ) . '<br/>';
        echo '<textarea name="symplx_motion_default_prompt" rows="3" style="width:100%">' . esc_textarea( $prompt ) . '</textarea>';
        echo '</label></p>';
        echo '<hr />';
        echo '<p><strong>' . esc_html__( 'Per‑post Replicate overrides (optional)', 'symplx-starter' ) . '</strong></p>';
        echo '<p><label>' . esc_html__( 'Model version', 'symplx-starter' ) . '<br/>';
        echo '<input type="text" name="symplx_motion_model_version" value="' . esc_attr( $model_version ) . '" class="widefat" placeholder="owner/model:versionhash or raw version id" />';
        echo '</label></p>';
        echo '<p><label>' . esc_html__( 'Mapping preset', 'symplx-starter' ) . '<br/>';
        $presets = [
            '' => __( 'Use global mapping', 'symplx-starter' ),
            'svd' => 'Stable Video Diffusion (img2vid)',
            'seedance' => 'Seedance (bytedance/seedance-1-pro)',
            'seedance-lite' => 'Seedance Lite (bytedance/seedance-1-lite)',
            'minimax' => 'MiniMax (minimax/video-01)',
            'wan-fast' => 'WAN 2.2 i2v fast (wan-video/wan-2.2-i2v-fast)',
            'wan-720p' => 'WAN 2.1 i2v 720p (wavespeedai/wan-2.1-i2v-720p)',
            'ltx-video' => 'LTX Video (lightricks/ltx-video)'
        ];
        echo '<select name="symplx_motion_mapping_preset" class="widefat">';
        foreach ( $presets as $val => $label ) {
            echo '<option value="' . esc_attr( $val ) . '"' . selected( $mapping_preset, $val, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '</label></p>';
        echo '<p class="description">' . esc_html__( 'Use the [symplx_motion id=...] shortcode to target specific images if not enabling all.', 'symplx-starter' ) . '</p>';

        // Generate all images in this post
        $bulk_url = wp_nonce_url( add_query_arg( [ 'action' => 'symplx_generate_post_images', 'post_id' => $post->ID ], admin_url( 'admin-post.php' ) ), 'symplx_generate_post_images_' . $post->ID );
        echo '<p><a href="' . esc_url( $bulk_url ) . '" class="button button-primary">' . esc_html__( 'Generate motion for all images in this post', 'symplx-starter' ) . '</a></p>';
    }

    public function save_post_meta( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! isset( $_POST['symplx_motion_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['symplx_motion_nonce'] ) ), 'symplx_motion_save' ) ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $enable = isset( $_POST['symplx_motion_enable_all'] ) ? 1 : 0;
        update_post_meta( $post_id, 'symplx_motion_enable_all', (bool) $enable );

        $prompt = isset( $_POST['symplx_motion_default_prompt'] ) ? wp_kses_post( wp_unslash( $_POST['symplx_motion_default_prompt'] ) ) : '';
        update_post_meta( $post_id, 'symplx_motion_default_prompt', $prompt );

        $model_version = isset( $_POST['symplx_motion_model_version'] ) ? sanitize_text_field( wp_unslash( $_POST['symplx_motion_model_version'] ) ) : '';
        update_post_meta( $post_id, 'symplx_motion_model_version', $model_version );

        $mapping_preset = isset( $_POST['symplx_motion_mapping_preset'] ) ? sanitize_text_field( wp_unslash( $_POST['symplx_motion_mapping_preset'] ) ) : '';
        update_post_meta( $post_id, 'symplx_motion_mapping_preset', $mapping_preset );
    }
}
