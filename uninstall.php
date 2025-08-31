<?php
// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'symplx_starter_provider' );
delete_option( 'symplx_starter_api_key' );
delete_option( 'symplx_starter_default_mode' );
delete_option( 'symplx_starter_default_effect' );
delete_option( 'symplx_max_video_size_mb' );
// Replicate provider options
delete_option( 'symplx_replicate_api_token' );
delete_option( 'symplx_replicate_model_version' );
delete_option( 'symplx_replicate_image_key' );
delete_option( 'symplx_replicate_fps_key' );
delete_option( 'symplx_replicate_frames_key' );
delete_option( 'symplx_replicate_duration_key' );
delete_option( 'symplx_replicate_prompt_key' );
delete_option( 'symplx_replicate_extra_input' );
