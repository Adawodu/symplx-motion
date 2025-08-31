<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

interface Symplx_Motion_Provider_Interface {
    public function create_job( int $attachment_id, array $args = [] );
    // Returns array: [ 'status' => string, 'video_url' => ?string, 'progress' => ?int, 'raw' => mixed ] or WP_Error
    public function get_status( string $job_id );
}

class Symplx_Motion_Providers_Registry {
    public static function get_active_slug(): string {
        $slug = get_option( 'symplx_starter_provider', 'mock' );
        return $slug ?: 'mock';
    }

    public static function get(): ?Symplx_Motion_Provider_Interface {
        $slug = self::get_active_slug();
        switch ( $slug ) {
            case 'replicate':
                require_once SYMPLX_STARTER_PLUGIN_DIR . 'includes/providers/provider-replicate.php';
                return new Symplx_Motion_Provider_Replicate();
            case 'mock':
            default:
                require_once SYMPLX_STARTER_PLUGIN_DIR . 'includes/providers/provider-mock.php';
                return new Symplx_Motion_Provider_Mock();
        }
    }
}
