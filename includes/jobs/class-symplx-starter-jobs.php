<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Symplx_Starter_Jobs {

    public function __construct() {
        add_action( 'symplx_motion_check_job', [ $this, 'check_job' ], 10, 1 );
    }

    public static function schedule_check( int $attachment_id, int $delay = 60 ) {
        if ( function_exists( 'as_schedule_single_action' ) ) {
            if ( function_exists( 'as_next_scheduled_action' ) ) {
                $next = as_next_scheduled_action( 'symplx_motion_check_job', [ $attachment_id ], 'symplx_motion' );
                if ( $next ) { return; }
            }
            // Use Action Scheduler if available.
            as_schedule_single_action( time() + $delay, 'symplx_motion_check_job', [ $attachment_id ], 'symplx_motion' );
        } else {
            // Fallback to WP-Cron single event.
            if ( ! wp_next_scheduled( 'symplx_motion_check_job', [ $attachment_id ] ) ) {
                wp_schedule_single_event( time() + $delay, 'symplx_motion_check_job', [ $attachment_id ] );
            }
        }
    }

    public function check_job( int $attachment_id ) {
        $status = get_post_meta( $attachment_id, '_symplx_motion_status', true );
        if ( 'ready' === $status ) {
            return; // done
        }

        require_once SYMPLX_STARTER_PLUGIN_DIR . 'includes/providers/interface-provider.php';
        require_once SYMPLX_STARTER_PLUGIN_DIR . 'includes/helpers/media.php';

        $job_id = get_post_meta( $attachment_id, '_symplx_motion_job_id', true );
        if ( ! $job_id ) {
            return;
        }
        $provider = Symplx_Motion_Providers_Registry::get();
        if ( ! $provider ) {
            return;
        }
        $res = $provider->get_status( $job_id );
        if ( is_wp_error( $res ) ) {
            // Retry later with exponential-ish backoff.
            self::schedule_check( $attachment_id, 120 );
            return;
        }
        $new_status = $res['status'] ?? $status;
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
                update_post_meta( $attachment_id, '_symplx_motion_video_url', $sideloaded );
                $new_status = 'ready';
            } else {
                update_post_meta( $attachment_id, '_symplx_motion_error', $sideloaded->get_error_message() );
                $new_status = 'failed';
            }
        }
        update_post_meta( $attachment_id, '_symplx_motion_status', $new_status );
        update_post_meta( $attachment_id, '_symplx_motion_updated_at', current_time( 'mysql', true ) );

        if ( in_array( $new_status, [ 'starting', 'processing', 'queued' ], true ) ) {
            self::schedule_check( $attachment_id, 60 );
        }
    }
}
