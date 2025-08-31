<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Symplx_Motion_REST {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        // Motion generation endpoints.
        register_rest_route( 'symplx/v1', '/motion/generate', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'generate_motion' ],
                'permission_callback' => function () { return current_user_can( 'upload_files' ); },
                'args'                => [
                    'attachment_id' => [ 'required' => true, 'type' => 'integer' ],
                    'prompt'        => [ 'required' => false, 'type' => 'string' ],
                    'effect'        => [ 'required' => false, 'type' => 'string', 'enum' => [ 'kenburns', 'parallax' ] ],
                    'duration'      => [ 'required' => false, 'type' => 'integer' ],
                    'fps'           => [ 'required' => false, 'type' => 'integer' ],
                ],
            ],
        ] );

        register_rest_route( 'symplx/v1', '/motion/status', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'status_motion' ],
                'permission_callback' => function () { return current_user_can( 'upload_files' ); },
                'args'                => [
                    'attachment_id' => [ 'required' => true, 'type' => 'integer' ],
                ],
            ],
        ] );
    }

    public function generate_motion( $request ) {
        require_once SYMPLX_MOTION_PLUGIN_DIR . 'includes/providers/interface-provider.php';
        $attachment_id = absint( $request->get_param( 'attachment_id' ) );
        $prompt        = sanitize_text_field( (string) $request->get_param( 'prompt' ) );
        $effect        = $request->get_param( 'effect' ) ?: get_option( 'symplx_motion_default_effect', 'kenburns' );
        $duration      = absint( $request->get_param( 'duration' ) );
        $fps           = absint( $request->get_param( 'fps' ) );
        if ( ! get_post( $attachment_id ) ) {
            return new WP_Error( 'not_found', 'Attachment not found', [ 'status' => 404 ] );
        }

        $provider = Symplx_Motion_Providers_Registry::get();
        if ( ! $provider ) {
            return new WP_Error( 'provider', 'No motion provider configured' );
        }

        $res = $provider->create_job( $attachment_id, [ 'prompt' => $prompt, 'effect' => $effect, 'duration' => $duration, 'fps' => $fps ] );
        if ( is_wp_error( $res ) ) {
            return $res;
        }
        $job_id = $res['job_id'] ?? '';
        $status = $res['status'] ?? 'queued';

        update_post_meta( $attachment_id, '_symplx_motion_provider', Symplx_Motion_Providers_Registry::get_active_slug() );
        if ( $job_id ) {
            update_post_meta( $attachment_id, '_symplx_motion_job_id', $job_id );
        }
        update_post_meta( $attachment_id, '_symplx_motion_status', $status );
        $now = current_time( 'mysql', true );
        update_post_meta( $attachment_id, '_symplx_motion_created_at', $now );
        update_post_meta( $attachment_id, '_symplx_motion_updated_at', $now );
        if ( isset( $res['raw'] ) ) {
            update_post_meta( $attachment_id, '_symplx_motion_last_raw', wp_json_encode( $res['raw'] ) );
        }
        // Store resolved model/preset used
        $parent = wp_get_post_parent_id( $attachment_id );
        $resolved_model = '';
        $resolved_preset = '';
        if ( $parent ) {
            $resolved_model = get_post_meta( $parent, 'symplx_motion_model_version', true );
            $resolved_preset = get_post_meta( $parent, 'symplx_motion_mapping_preset', true );
        }
        if ( ! $resolved_model ) {
            $resolved_model = get_option( 'symplx_motion_replicate_model_version', '' );
        }
        update_post_meta( $attachment_id, '_symplx_motion_model_version_resolved', $resolved_model );
        if ( $resolved_preset ) {
            update_post_meta( $attachment_id, '_symplx_motion_preset_resolved', $resolved_preset );
        }
        if ( $prompt ) {
            update_post_meta( $attachment_id, '_symplx_motion_prompt', $prompt );
        }

        // Schedule background polling.
        require_once SYMPLX_MOTION_PLUGIN_DIR . 'includes/jobs/class-symplx-starter-jobs.php';
        Symplx_Motion_Jobs::schedule_check( $attachment_id, 30 );

        return rest_ensure_response( [ 'attachment_id' => $attachment_id, 'status' => $status, 'job_id' => $job_id ] );
    }

    public function status_motion( $request ) {
        require_once SYMPLX_MOTION_PLUGIN_DIR . 'includes/providers/interface-provider.php';
        require_once SYMPLX_MOTION_PLUGIN_DIR . 'includes/helpers/media.php';
        $attachment_id = absint( $request->get_param( 'attachment_id' ) );
        if ( ! get_post( $attachment_id ) ) {
            return new WP_Error( 'not_found', 'Attachment not found', [ 'status' => 404 ] );
        }
        $provider_slug = get_post_meta( $attachment_id, '_symplx_motion_provider', true ) ?: Symplx_Motion_Providers_Registry::get_active_slug();
        $job_id        = get_post_meta( $attachment_id, '_symplx_motion_job_id', true );
        $status        = get_post_meta( $attachment_id, '_symplx_motion_status', true ) ?: 'none';
        $effect        = get_post_meta( $attachment_id, '_symplx_motion_effect', true ) ?: get_option( 'symplx_motion_default_effect', 'kenburns' );
        $video_meta    = get_post_meta( $attachment_id, '_symplx_motion_video_url', true );

        // If already have video URL, just return ready.
        if ( $video_meta ) {
            return rest_ensure_response( [ 'attachment_id' => $attachment_id, 'status' => 'ready', 'video_url' => $video_meta, 'effect' => 'video' ] );
        }

        if ( $job_id && in_array( $status, [ 'starting', 'processing', 'queued' ], true ) ) {
            $provider = Symplx_Motion_Providers_Registry::get();
            if ( $provider ) {
                $res = $provider->get_status( $job_id );
                if ( ! is_wp_error( $res ) ) {
                    $status = $res['status'] ?? $status;
                    if ( isset( $res['progress'] ) && is_numeric( $res['progress'] ) ) {
                        update_post_meta( $attachment_id, '_symplx_motion_progress', intval( $res['progress'] ) );
                    }
                    if ( isset( $res['raw'] ) ) {
                        update_post_meta( $attachment_id, '_symplx_motion_last_raw', wp_json_encode( $res['raw'] ) );
                        if ( is_array( $res['raw'] ) && isset( $res['raw']['logs'] ) && is_string( $res['raw']['logs'] ) ) {
                            update_post_meta( $attachment_id, '_symplx_motion_logs', $res['raw']['logs'] );
                        }
                    }
                    if ( ! empty( $res['video_url'] ) ) {
                        $sideloaded = symplx_sideload_video_to_media( $attachment_id, $res['video_url'] );
                        if ( ! is_wp_error( $sideloaded ) ) {
                            $status = 'ready';
                            update_post_meta( $attachment_id, '_symplx_motion_video_url', $sideloaded );
                        } else {
                            update_post_meta( $attachment_id, '_symplx_motion_error', $sideloaded->get_error_message() );
                            $status = 'failed';
                        }
                    }
                    update_post_meta( $attachment_id, '_symplx_motion_status', $status );
                    update_post_meta( $attachment_id, '_symplx_motion_updated_at', current_time( 'mysql', true ) );
                }
            }
        }

        $video_meta = get_post_meta( $attachment_id, '_symplx_motion_video_url', true );
        return rest_ensure_response( [ 'attachment_id' => $attachment_id, 'status' => $status, 'effect' => $effect, 'video_url' => $video_meta ] );
    }

    // sideload moved to helper
}
