<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Symplx_Motion_Provider_Mock implements Symplx_Motion_Provider_Interface {
    public function create_job( int $attachment_id, array $args = [] ) {
        // Instantly "complete" with CSS effect only.
        update_post_meta( $attachment_id, '_symplx_motion_status', 'ready' );
        update_post_meta( $attachment_id, '_symplx_motion_effect', $args['effect'] ?? get_option( 'symplx_starter_default_effect', 'kenburns' ) );
        $job_id = 'mock-' . $attachment_id . '-' . time();
        return [ 'job_id' => $job_id, 'status' => 'ready' ];
    }

    public function get_status( string $job_id ) {
        return [ 'status' => 'ready' ];
    }
}

