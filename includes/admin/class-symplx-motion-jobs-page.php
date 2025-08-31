<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Symplx_Motion_Jobs_Table extends WP_List_Table {
    private $filter_provider;
    private $filter_status;

    public function __construct() {
        parent::__construct( [ 'plural' => 'symplx_jobs', 'singular' => 'symplx_job', 'ajax' => false ] );
        $this->filter_provider = isset( $_GET['symplx_provider'] ) ? sanitize_text_field( wp_unslash( $_GET['symplx_provider'] ) ) : '';
        $this->filter_status   = isset( $_GET['symplx_status'] ) ? sanitize_text_field( wp_unslash( $_GET['symplx_status'] ) ) : '';
    }

    public function get_columns() {
        return [
            'cb'        => '<input type="checkbox" />',
            'thumb'     => __( 'Image', 'symplx-motion' ),
            'title'     => __( 'Attachment', 'symplx-motion' ),
            'provider'  => __( 'Provider', 'symplx-motion' ),
            'model'     => __( 'Model', 'symplx-motion' ),
            'preset'    => __( 'Preset', 'symplx-motion' ),
            'status'    => __( 'Status', 'symplx-motion' ),
            'progress'  => __( 'Progress', 'symplx-motion' ),
            'created'   => __( 'Created', 'symplx-motion' ),
            'updated'   => __( 'Updated', 'symplx-motion' ),
            'video'     => __( 'Video', 'symplx-motion' ),
            'actions'   => __( 'Actions', 'symplx-motion' ),
        ];
    }

    public function prepare_items() {
        $per_page = 20;
        $paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $args = [
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'meta_query'     => [
                'relation' => 'OR',
                [ 'key' => '_symplx_motion_job_id', 'compare' => 'EXISTS' ],
                [ 'key' => '_symplx_motion_video_url', 'compare' => 'EXISTS' ],
                [ 'key' => '_symplx_motion_status', 'compare' => 'EXISTS' ],
            ],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];
        $filter_meta = [ 'relation' => 'AND' ];
        if ( $this->filter_provider ) {
            $filter_meta[] = [ 'key' => '_symplx_motion_provider', 'value' => $this->filter_provider ];
        }
        if ( $this->filter_status ) {
            $filter_meta[] = [ 'key' => '_symplx_motion_status', 'value' => $this->filter_status ];
        }
        if ( count( $filter_meta ) > 1 ) {
            $args['meta_query'] = [ $args['meta_query'], $filter_meta ];
        }
        $q = new WP_Query( $args );
        $items = [];
        foreach ( $q->posts as $p ) {
            $id = $p->ID;
            $items[] = [
                'ID'        => $id,
                'thumb'     => wp_get_attachment_image( $id, [60,60] ),
                'title'     => get_the_title( $id ),
                'provider'  => get_post_meta( $id, '_symplx_motion_provider', true ) ?: '—',
                'model'     => get_post_meta( $id, '_symplx_motion_model_version_resolved', true ) ?: '',
                'preset'    => get_post_meta( $id, '_symplx_motion_preset_resolved', true ) ?: '',
                'status'    => get_post_meta( $id, '_symplx_motion_status', true ) ?: '—',
                'progress'  => get_post_meta( $id, '_symplx_motion_progress', true ) ?: '',
                'created'   => get_post_meta( $id, '_symplx_motion_created_at', true ) ?: '',
                'updated'   => get_post_meta( $id, '_symplx_motion_updated_at', true ) ?: '',
                'video'     => get_post_meta( $id, '_symplx_motion_video_url', true ) ?: '',
            ];
        }
        $this->items = $items;
        $this->set_pagination_args( [
            'total_items' => intval( $q->found_posts ),
            'per_page'    => $per_page,
            'total_pages' => max( 1, intval( ceil( $q->found_posts / $per_page ) ) ),
        ] );
    }

    protected function get_bulk_actions() {
        return [ 'bulk_refresh' => __( 'Refresh', 'symplx-motion' ) ];
    }

    public function process_bulk_action() {
        if ( 'bulk_refresh' === $this->current_action() ) {
            $ids = isset( $_REQUEST['attachment'] ) ? (array) $_REQUEST['attachment'] : [];
            $ids = array_map( 'absint', $ids );
            check_admin_referer( 'bulk-' . $this->_args['plural'] );
            require_once SYMPLX_MOTION_PLUGIN_DIR . 'includes/jobs/class-symplx-motion-jobs.php';
            $jobs = new Symplx_Motion_Jobs();
            foreach ( $ids as $id ) {
                if ( $id ) {
                    $jobs->check_job( $id );
                    Symplx_Motion_Jobs::schedule_check( $id, 30 );
                }
            }
        }
    }

    protected function extra_tablenav( $which ) {
        if ( 'top' !== $which ) return;
        $provider = $this->filter_provider;
        $status   = $this->filter_status;
        echo '<div class="alignleft actions">';
        echo '<select name="symplx_provider">';
        echo '<option value="">' . esc_html__( 'All providers', 'symplx-motion' ) . '</option>';
        foreach ( [ 'mock' => 'Mock', 'replicate' => 'Replicate' ] as $k => $label ) {
            echo '<option value="' . esc_attr( $k ) . '"' . selected( $provider, $k, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';

        echo '<select name="symplx_status" style="margin-left:6px;">';
        echo '<option value="">' . esc_html__( 'All statuses', 'symplx-motion' ) . '</option>';
        foreach ( [ 'queued' => 'Queued', 'starting' => 'Starting', 'processing' => 'Processing', 'ready' => 'Ready', 'failed' => 'Failed', 'canceled' => 'Canceled' ] as $k => $label ) {
            echo '<option value="' . esc_attr( $k ) . '"' . selected( $status, $k, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        submit_button( __( 'Filter' ), '', 'filter_action', false, [ 'style' => 'margin-left:6px;' ] );
        echo '</div>';
    }

    protected function column_cb( $item ) {
        return '<input type="checkbox" name="attachment[]" value="' . esc_attr( $item['ID'] ) . '" />';
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'thumb': return $item['thumb'];
            case 'title': return esc_html( $item['title'] );
            case 'provider': return esc_html( strtoupper( $item['provider'] ) );
            case 'model': return $item['model'] ? '<code>' . esc_html( $item['model'] ) . '</code>' : '—';
            case 'preset':
                $labels = [
                    'svd' => 'SVD', 'seedance' => 'Seedance', 'seedance-lite' => 'Seedance Lite', 'minimax' => 'MiniMax', 'wan-fast' => 'WAN Fast', 'wan-720p' => 'WAN 720p', 'ltx-video' => 'LTX Video'
                ];
                if ( ! $item['preset'] ) return '—';
                return esc_html( $labels[ $item['preset'] ] ?? $item['preset'] );
            case 'status':
                $status = $item['status'];
                if ( 'failed' === $status ) {
                    $err = get_post_meta( $item['ID'], '_symplx_motion_error', true );
                    $icon = '<span class="dashicons dashicons-warning" style="color:#d63638" title="' . esc_attr( $err ) . '"></span> ';
                    return $icon . esc_html__( 'Failed', 'symplx-motion' );
                }
                return esc_html( ucfirst( $status ) );
            case 'progress': return $item['progress'] !== '' ? intval( $item['progress'] ) . '%' : '—';
            case 'created': return $item['created'] ? esc_html( get_date_from_gmt( $item['created'], 'Y-m-d H:i' ) ) : '—';
            case 'updated': return $item['updated'] ? esc_html( get_date_from_gmt( $item['updated'], 'Y-m-d H:i' ) ) : '—';
            case 'video': return $item['video'] ? '<a href="' . esc_url( $item['video'] ) . '" target="_blank">' . esc_html__( 'View', 'symplx-motion' ) . '</a>' : '—';
            case 'actions':
                $refresh = wp_nonce_url( add_query_arg( [ 'page' => 'symplx-motion-jobs', 'symplx_action' => 'refresh', 'attachment_id' => $item['ID'] ], admin_url( 'options-general.php' ) ), 'symplx_refresh_' . $item['ID'] );
                $detail  = wp_nonce_url( add_query_arg( [ 'page' => 'symplx-motion-jobs', 'symplx_action' => 'detail', 'attachment_id' => $item['ID'] ], admin_url( 'options-general.php' ) ), 'symplx_detail_' . $item['ID'] );
                $actions = '<a class="button" href="' . esc_url( $refresh ) . '">' . esc_html__( 'Refresh', 'symplx-motion' ) . '</a> ';
                $actions .= '<a class="button" href="' . esc_url( $detail ) . '">' . esc_html__( 'Detail', 'symplx-motion' ) . '</a> ';
                $has_job = get_post_meta( $item['ID'], '_symplx_motion_job_id', true );
                $has_video = get_post_meta( $item['ID'], '_symplx_motion_video_url', true );
                if ( ! $has_job && ! $has_video ) {
                    $gen = wp_nonce_url( add_query_arg( [ 'page' => 'symplx-motion-jobs', 'symplx_action' => 'generate', 'attachment_id' => $item['ID'] ], admin_url( 'options-general.php' ) ), 'symplx_generate_' . $item['ID'] );
                    $actions .= '<a class="button button-primary" href="' . esc_url( $gen ) . '">' . esc_html__( 'Generate', 'symplx-motion' ) . '</a>';
                }
                return $actions;
        }
        return '';
    }
}

class Symplx_Motion_Jobs_Page {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'menu' ] );
    }

    public function menu() {
        add_submenu_page(
            'options-general.php',
            __( 'Symplx Motion Jobs', 'symplx-motion' ),
            __( 'Symplx Motion Jobs', 'symplx-motion' ),
            'manage_options',
            'symplx-motion-jobs',
            [ $this, 'render' ]
        );
    }

    public function render() {
        if ( isset( $_GET['symplx_action'] ) && $_GET['symplx_action'] === 'refresh' ) {
            $att_id = isset( $_GET['attachment_id'] ) ? absint( $_GET['attachment_id'] ) : 0;
            if ( $att_id && wp_verify_nonce( (string) $_GET['_wpnonce'], 'symplx_refresh_' . $att_id ) ) {
                require_once SYMPLX_MOTION_PLUGIN_DIR . 'includes/jobs/class-symplx-motion-jobs.php';
                // Immediate check, then reschedule.
                $jobs = new Symplx_Motion_Jobs();
                $jobs->check_job( $att_id );
                Symplx_Motion_Jobs::schedule_check( $att_id, 30 );
                echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Job refreshed.', 'symplx-motion' ) . '</p></div>';
            }
        }
        if ( isset( $_GET['symplx_action'] ) && $_GET['symplx_action'] === 'generate' ) {
            $att_id = isset( $_GET['attachment_id'] ) ? absint( $_GET['attachment_id'] ) : 0;
            $ok = false;
            if ( $att_id ) {
                if ( isset( $_GET['_wpnonce'] ) ) {
                    $ok = wp_verify_nonce( (string) $_GET['_wpnonce'], 'symplx_generate_' . $att_id );
                } elseif ( isset( $_GET['_symplx_nonce_any'] ) ) {
                    $ok = wp_verify_nonce( (string) $_GET['_symplx_nonce_any'], 'symplx_generate_any' );
                }
            }
            if ( $ok ) {
                require_once SYMPLX_MOTION_PLUGIN_DIR . 'includes/providers/interface-provider.php';
                require_once SYMPLX_MOTION_PLUGIN_DIR . 'includes/jobs/class-symplx-motion-jobs.php';
                $provider = Symplx_Motion_Providers_Registry::get();
                if ( $provider ) {
                    $res = $provider->create_job( $att_id, [] );
                    if ( ! is_wp_error( $res ) ) {
                        $job_id = $res['job_id'] ?? '';
                        $status = $res['status'] ?? 'queued';
                        update_post_meta( $att_id, '_symplx_motion_provider', Symplx_Motion_Providers_Registry::get_active_slug() );
                        update_post_meta( $att_id, '_symplx_motion_job_id', $job_id );
                        update_post_meta( $att_id, '_symplx_motion_status', $status );
                        update_post_meta( $att_id, '_symplx_motion_created_at', current_time( 'mysql', true ) );
                        update_post_meta( $att_id, '_symplx_motion_updated_at', current_time( 'mysql', true ) );
                        if ( isset( $res['raw'] ) ) {
                            update_post_meta( $att_id, '_symplx_motion_last_raw', wp_json_encode( $res['raw'] ) );
                        }
                        // Save resolved model/preset for visibility
                        $parent = wp_get_post_parent_id( $att_id );
                        $resolved_model = '';
                        $resolved_preset = '';
                        if ( $parent ) {
                            $resolved_model = get_post_meta( $parent, 'symplx_motion_model_version', true );
                            $resolved_preset = get_post_meta( $parent, 'symplx_motion_mapping_preset', true );
                        }
                        if ( ! $resolved_model ) {
                            $resolved_model = get_option( 'symplx_motion_replicate_model_version', '' );
                        }
                        update_post_meta( $att_id, '_symplx_motion_model_version_resolved', $resolved_model );
                        if ( $resolved_preset ) {
                            update_post_meta( $att_id, '_symplx_motion_preset_resolved', $resolved_preset );
                        }
                        Symplx_Motion_Jobs::schedule_check( $att_id, 30 );
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Generation started.', 'symplx-motion' ) . '</p></div>';
                    } else {
                        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to start generation. Check provider settings.', 'symplx-motion' ) . '</p></div>';
                    }
                }
            }
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Symplx Motion Jobs', 'symplx-motion' ) . '</h1>';
        echo '<p>' . esc_html__( 'List of image attachments with motion jobs or generated artifacts. Background polling updates status. Use filters, bulk refresh, or per-row actions.', 'symplx-motion' ) . '</p>';

        // Quick generate form
        echo '<form method="get" style="margin-bottom:12px;">';
        echo '<input type="hidden" name="page" value="symplx-motion-jobs" />';
        echo '<input type="hidden" name="symplx_action" value="generate" />';
        echo '<label>' . esc_html__( 'Attachment ID', 'symplx-motion' ) . ' <input type="number" name="attachment_id" min="1" required /></label> ';
        wp_nonce_field( 'symplx_generate_any', '_symplx_nonce_any' );
        submit_button( __( 'Generate Motion' ), 'primary', '', false );
        echo '</form>';

        $table = new Symplx_Motion_Jobs_Table();
        $table->process_bulk_action();
        $table->prepare_items();
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="symplx-motion-jobs" />';
        $table->display();
        echo '</form>';
        echo '</div>';

        // Detail view
        if ( isset( $_GET['symplx_action'] ) && $_GET['symplx_action'] === 'detail' ) {
            $att_id = isset( $_GET['attachment_id'] ) ? absint( $_GET['attachment_id'] ) : 0;
            if ( $att_id && wp_verify_nonce( (string) $_GET['_wpnonce'], 'symplx_detail_' . $att_id ) ) {
                $raw = get_post_meta( $att_id, '_symplx_motion_last_raw', true );
                $logs = get_post_meta( $att_id, '_symplx_motion_logs', true );
                echo '<div class="wrap">';
                echo '<h2>' . esc_html__( 'Job Detail', 'symplx-motion' ) . ' #' . intval( $att_id ) . '</h2>';
                if ( $logs ) {
                    echo '<h3>' . esc_html__( 'Logs', 'symplx-motion' ) . '</h3>';
                    echo '<pre style="max-height:300px;overflow:auto;background:#f6f7f7;border:1px solid #ccd0d4;padding:10px;">' . esc_html( $logs ) . '</pre>';
                }
                if ( $raw ) {
                    $pretty = $raw;
                    $decoded = json_decode( $raw, true );
                    if ( is_array( $decoded ) ) { $pretty = wp_json_encode( $decoded, JSON_PRETTY_PRINT ); }
                    echo '<h3>' . esc_html__( 'Raw Payload', 'symplx-motion' ) . '</h3>';
                    echo '<pre style="max-height:400px;overflow:auto;background:#f6f7f7;border:1px solid #ccd0d4;padding:10px;">' . esc_html( $pretty ) . '</pre>';
                } else {
                    echo '<p>' . esc_html__( 'No raw payload recorded yet. Refresh the job to fetch provider status.', 'symplx-motion' ) . '</p>';
                }
                echo '</div>';
            }
        }
    }
}
