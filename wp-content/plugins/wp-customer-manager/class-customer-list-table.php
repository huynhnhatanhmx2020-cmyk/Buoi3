<?php
/**
 * Class hiển thị bảng danh sách khách hàng trên Dashboard
 * Kế thừa WP_List_Table chuẩn của WordPress (giống bảng Users, Posts...)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class CM_Customer_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array(
            'singular' => 'customer',
            'plural'   => 'customers',
            'ajax'     => false,
        ) );
    }

    /**
     * Định nghĩa các cột hiển thị
     */
    public function get_columns() {
        return array(
            'cb'         => '<input type="checkbox" />',
            'full_name'  => 'Họ và tên',
            'phone'      => 'Số điện thoại',
            'email'      => 'Email',
            'address'    => 'Địa chỉ',
            'status'     => 'Trạng thái',
            'created_at' => 'Ngày tạo',
        );
    }

    /**
     * Các cột có thể sắp xếp
     */
    protected function get_sortable_columns() {
        return array(
            'full_name'  => array( 'full_name', false ),
            'created_at' => array( 'created_at', true ),
        );
    }

    /**
     * Checkbox chọn dòng
     */
    protected function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="customer_ids[]" value="%d" />', $item['id'] );
    }

    /**
     * Cột tên -> hiển thị link Sửa / Xóa khi hover (row actions)
     */
    protected function column_full_name( $item ) {
        $edit_url = admin_url( 'admin.php?page=cm-customer-edit&id=' . absint( $item['id'] ) );
        $delete_url = wp_nonce_url(
            admin_url( 'admin.php?page=cm-customers&action=delete&id=' . absint( $item['id'] ) ),
            'cm_delete_customer_' . absint( $item['id'] )
        );

        $actions = array(
            'edit'   => sprintf( '<a href="%s">Sửa</a>', esc_url( $edit_url ) ),
            'delete' => sprintf(
                '<a href="%s" onclick="return confirm(\'Bạn có chắc muốn xóa khách hàng này?\');">Xóa</a>',
                esc_url( $delete_url )
            ),
        );

        return sprintf(
            '<strong><a href="%s">%s</a></strong> %s',
            esc_url( $edit_url ),
            esc_html( $item['full_name'] ),
            $this->row_actions( $actions )
        );
    }

    /**
     * Cột trạng thái -> hiển thị badge màu
     */
    protected function column_status( $item ) {
        if ( $item['status'] === 'active' ) {
            return '<span style="color:#00a32a;font-weight:600;">● Đang hoạt động</span>';
        }
        return '<span style="color:#d63638;font-weight:600;">● Ngừng hoạt động</span>';
    }

    /**
     * Hiển thị mặc định cho các cột còn lại
     */
    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'phone':
            case 'email':
                return esc_html( $item[ $column_name ] );
            case 'address':
                $address = $item['address'];
                return esc_html( mb_strlen( $address ) > 40 ? mb_substr( $address, 0, 40 ) . '…' : $address );
            case 'created_at':
                return esc_html( mysql2date( 'd/m/Y H:i', $item['created_at'] ) );
            default:
                return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
        }
    }

    /**
     * Hành động hàng loạt (bulk actions)
     */
    protected function get_bulk_actions() {
        return array(
            'bulk-delete' => 'Xóa các mục đã chọn',
        );
    }

    /**
     * Xử lý bulk action xóa
     */
    protected function process_bulk_action() {
        if ( 'bulk-delete' === $this->current_action() && ! empty( $_POST['customer_ids'] ) ) {
            check_admin_referer( 'bulk-' . $this->_args['plural'] );
            global $wpdb;
            $table_name = $wpdb->prefix . CM_TABLE_NAME;
            $ids = array_map( 'absint', (array) $_POST['customer_ids'] );
            $ids_placeholder = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE id IN ($ids_placeholder)", $ids ) );
        }
    }

    /**
     * Lấy dữ liệu từ database + phân trang + tìm kiếm
     */
    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . CM_TABLE_NAME;

        $this->process_bulk_action();

        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array( $columns, $hidden, $sortable );

        // Tìm kiếm
        $search = '';
        if ( ! empty( $_REQUEST['s'] ) ) {
            $search = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
        }

        // Sắp xếp
        $orderby = ! empty( $_REQUEST['orderby'] ) ? sanitize_sql_orderby( wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at';
        $order   = ( ! empty( $_REQUEST['order'] ) && strtolower( $_REQUEST['order'] ) === 'asc' ) ? 'ASC' : 'DESC';

        // Phân trang
        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $offset       = ( $current_page - 1 ) * $per_page;

        $where = '';
        $query_args = array();
        if ( ! empty( $search ) ) {
            $where = "WHERE full_name LIKE %s OR phone LIKE %s OR email LIKE %s";
            $like  = '%' . $wpdb->esc_like( $search ) . '%';
            $query_args = array( $like, $like, $like );
        }

        // Tổng số bản ghi
        if ( ! empty( $query_args ) ) {
            $total_items = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_name $where", $query_args ) );
        } else {
            $total_items = $wpdb->get_var( "SELECT COUNT(id) FROM $table_name" );
        }

        // Lấy dữ liệu trang hiện tại
        $sql = "SELECT * FROM $table_name $where ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $sql_args = array_merge( $query_args, array( $per_page, $offset ) );
        $this->items = $wpdb->get_results( $wpdb->prepare( $sql, $sql_args ), ARRAY_A );

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ) );
    }

    /**
     * Thông báo khi không có dữ liệu
     */
    public function no_items() {
        echo 'Chưa có khách hàng nào đăng ký.';
    }
}
