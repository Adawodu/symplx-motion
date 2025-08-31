<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function symplx_sideload_video_to_media( int $attachment_id, string $remote_url ) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    // Validate scheme and extension
    $parts = wp_parse_url( $remote_url );
    if ( empty( $parts['scheme'] ) || ! in_array( strtolower( $parts['scheme'] ), [ 'http', 'https' ], true ) ) {
        return new WP_Error( 'invalid_url', 'Only http/https video URLs are allowed' );
    }
    $path = isset( $parts['path'] ) ? $parts['path'] : '';
    if ( ! preg_match( '/\.(mp4|webm)(?:$|\?)/i', $path ) ) {
        return new WP_Error( 'invalid_ext', 'Only MP4/WebM video files are allowed' );
    }

    $tmp = download_url( $remote_url, 60 );
    if ( is_wp_error( $tmp ) ) return $tmp;

    // Enforce max size (MB) if configured
    $max_mb = (int) get_option( 'symplx_motion_max_video_size_mb', 100 );
    if ( $max_mb > 0 ) {
        $size = @filesize( $tmp );
        if ( false !== $size && $size > ( $max_mb * 1024 * 1024 ) ) {
            @unlink( $tmp );
            return new WP_Error( 'file_too_large', 'Video exceeds max allowed size' );
        }
    }

    $file_array = [
        'name'     => basename( parse_url( $remote_url, PHP_URL_PATH ) ) ?: ( 'motion-' . $attachment_id . '.mp4' ),
        'tmp_name' => $tmp,
    ];
    $parent = wp_get_post_parent_id( $attachment_id ) ?: 0;
    $att_id = media_handle_sideload( $file_array, $parent );
    if ( is_wp_error( $att_id ) ) {
        @unlink( $tmp );
        return $att_id;
    }
    $url = wp_get_attachment_url( $att_id );
    return $url ?: new WP_Error( 'sideload', 'Unable to get sideloaded URL' );
}
