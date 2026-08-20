<?php
/**
 * Plugin Name: Quản Lý Khách Hàng
 * Plugin URI:  https://example.com
 * Description: Đăng ký & quản lý thông tin khách hàng, hiển thị danh sách khách hàng trên Dashboard WordPress (menu riêng).
 * Version:     1.0.0
 * Author:      Your Name
 * Text Domain: customer-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Chặn truy cập trực tiếp
}

define( 'CM_PLUGIN_VERSION', '1.0.0' );
define( 'CM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CM_TABLE_NAME', 'customers' ); // sẽ được ghép với $wpdb->prefix

/**
 * ========================================
 * 1. KÍCH HOẠT PLUGIN: TẠO BẢNG DATABASE
 * ========================================
 */
register_activation_hook( __FILE__, 'cm_create_customer_table' );

function cm_create_customer_table() {
    global $wpdb;

    $table_name      = $wpdb->prefix . CM_TABLE_NAME;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        full_name VARCHAR(191) NOT NULL,
        phone VARCHAR(30) DEFAULT '' NOT NULL,
        email VARCHAR(191) DEFAULT '' NOT NULL,
        address TEXT NULL,
        note TEXT NULL,
        status VARCHAR(20) DEFAULT 'active' NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY phone (phone),
        KEY email (email)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    add_option( 'cm_plugin_version', CM_PLUGIN_VERSION );
}

/**
 * ========================================
 * 2. TẠO MENU TRÊN DASHBOARD
 * ========================================
 */
add_action( 'admin_menu', 'cm_register_admin_menu' );

function cm_register_admin_menu() {

    // Menu chính: "Khách hàng"
    add_menu_page(
        'Quản lý khách hàng',      // Page title
        'Khách hàng',              // Menu title
        'manage_options',          // Capability (đổi thành 'edit_posts' nếu muốn user thường dùng được)
        'cm-customers',            // Menu slug
        'cm_render_customer_list_page', // Callback
        'dashicons-groups',        // Icon
        26                         // Vị trí trong menu
    );

    // Submenu: Danh sách khách hàng (trùng slug với menu cha -> submenu đầu tiên)
    add_submenu_page(
        'cm-customers',
        'Danh sách khách hàng',
        'Danh sách khách hàng',
        'manage_options',
        'cm-customers',
        'cm_render_customer_list_page'
    );

    // Submenu ẩn: Trang Sửa khách hàng (không hiện trên menu, chỉ truy cập qua link "Sửa" trong danh sách)
    add_submenu_page(
        null,
        'Sửa thông tin khách hàng',
        'Sửa thông tin khách hàng',
        'manage_options',
        'cm-customer-edit',
        'cm_render_customer_form_page'
    );

    // Submenu: Hướng dẫn lấy shortcode form đăng ký ngoài website
    add_submenu_page(
        'cm-customers',
        'Form đăng ký (Frontend)',
        'Form đăng ký (Frontend)',
        'manage_options',
        'cm-frontend-shortcode',
        'cm_render_shortcode_guide_page'
    );
}

function cm_render_shortcode_guide_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Bạn không có quyền truy cập trang này.' );
    }
    ?>
    <div class="wrap">
        <h1>Form đăng ký khách hàng ngoài website</h1>
        <p>Dán shortcode dưới đây vào bất kỳ <strong>Trang (Page)</strong> hoặc <strong>Bài viết (Post)</strong> nào để hiển thị form cho khách tự đăng ký thông tin. Dữ liệu khách gửi lên sẽ tự động xuất hiện trong mục <a href="<?php echo esc_url( admin_url( 'admin.php?page=cm-customers' ) ); ?>">Danh sách khách hàng</a>.</p>

        <p>
            <input type="text" readonly onclick="this.select();"
                   value="[customer_registration_form]"
                   style="width:320px;padding:8px;font-size:14px;font-family:monospace;" />
        </p>

        <p><strong>Cách dùng:</strong> Vào <em>Trang → Thêm mới</em>, dán shortcode <code>[customer_registration_form]</code> vào nội dung trang, sau đó Xuất bản. Khi truy cập trang đó ngoài website, khách sẽ thấy form đăng ký thông tin.</p>
    </div>
    <?php
}

/**
 * ========================================
 * 3. NẠP CLASS DANH SÁCH (WP_List_Table)
 * ========================================
 */
require_once CM_PLUGIN_DIR . 'class-customer-list-table.php';

/**
 * ========================================
 * 3b. NẠP FORM ĐĂNG KÝ NGOÀI WEBSITE (FRONTEND)
 * Dùng shortcode: [customer_registration_form]
 * ========================================
 */
require_once CM_PLUGIN_DIR . 'frontend-registration-form.php';

/**
 * ========================================
 * 4. TRANG DANH SÁCH KHÁCH HÀNG
 * ========================================
 */
function cm_render_customer_list_page() {

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Bạn không có quyền truy cập trang này.' );
    }

    // Xử lý xóa khách hàng
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
        check_admin_referer( 'cm_delete_customer_' . absint( $_GET['id'] ) );
        cm_delete_customer( absint( $_GET['id'] ) );
        echo '<div class="notice notice-success is-dismissible"><p>Đã xóa khách hàng thành công.</p></div>';
    }

    // Thông báo sau khi sửa
    if ( isset( $_GET['message'] ) && $_GET['message'] === 'updated' ) {
        echo '<div class="notice notice-success is-dismissible"><p>Đã cập nhật thông tin khách hàng.</p></div>';
    }

    $list_table = new CM_Customer_List_Table();
    $list_table->prepare_items();
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Danh sách khách hàng</h1>
        <p>Khách hàng đăng ký qua form ngoài website (xem mục <a href="<?php echo esc_url( admin_url( 'admin.php?page=cm-frontend-shortcode' ) ); ?>">Form đăng ký (Frontend)</a>) sẽ tự động xuất hiện tại đây.</p>
        <hr class="wp-header-end">

        <form method="get">
            <input type="hidden" name="page" value="cm-customers" />
            <?php
            $list_table->search_box( 'Tìm khách hàng', 'cm-search' );
            $list_table->display();
            ?>
        </form>
    </div>
    <?php
}

/**
 * ========================================
 * 5. TRANG SỬA THÔNG TIN KHÁCH HÀNG
 * (Chỉ dùng để sửa khách hàng đã có sẵn — không có chức năng admin tự thêm mới)
 * ========================================
 */
function cm_render_customer_form_page() {

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Bạn không có quyền truy cập trang này.' );
    }

    global $wpdb;
    $table_name = $wpdb->prefix . CM_TABLE_NAME;

    // Bắt buộc phải có ID hợp lệ -> chỉ cho phép sửa, không cho thêm mới
    $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    $customer = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ), ARRAY_A ) : null;

    if ( ! $customer ) {
        echo '<div class="wrap"><h1>Không tìm thấy khách hàng</h1><p>Khách hàng này không tồn tại hoặc đã bị xóa.</p>';
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=cm-customers' ) ) . '" class="button">Quay lại danh sách</a></p></div>';
        return;
    }

    // Xử lý submit form (chỉ cập nhật)
    if ( isset( $_POST['cm_submit'] ) ) {
        check_admin_referer( 'cm_save_customer', 'cm_nonce' );

        $data = array(
            'full_name' => sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) ),
            'phone'     => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
            'email'     => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
            'address'   => sanitize_textarea_field( wp_unslash( $_POST['address'] ?? '' ) ),
            'note'      => sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ),
            'status'    => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'active' ) ),
        );

        $errors = array();
        if ( empty( $data['full_name'] ) ) {
            $errors[] = 'Vui lòng nhập họ tên khách hàng.';
        }
        if ( ! empty( $data['email'] ) && ! is_email( $data['email'] ) ) {
            $errors[] = 'Email không hợp lệ.';
        }

        if ( empty( $errors ) ) {
            $data['updated_at'] = current_time( 'mysql' );
            $wpdb->update( $table_name, $data, array( 'id' => $id ) );
            wp_redirect( admin_url( 'admin.php?page=cm-customers&message=updated' ) );
            exit;
        } else {
            foreach ( $errors as $error ) {
                echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
            }
            // Giữ lại dữ liệu vừa nhập để không phải gõ lại
            $customer = array_merge( $customer, $data );
        }
    }
    ?>
    <div class="wrap">
        <h1>Sửa thông tin khách hàng</h1>

        <form method="post" action="">
            <?php wp_nonce_field( 'cm_save_customer', 'cm_nonce' ); ?>
            <input type="hidden" name="customer_id" value="<?php echo esc_attr( $customer['id'] ); ?>" />

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="full_name">Họ và tên <span style="color:red">*</span></label></th>
                    <td>
                        <input name="full_name" id="full_name" type="text" class="regular-text"
                               value="<?php echo esc_attr( $customer['full_name'] ); ?>" required />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="phone">Số điện thoại</label></th>
                    <td>
                        <input name="phone" id="phone" type="text" class="regular-text"
                               value="<?php echo esc_attr( $customer['phone'] ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="email">Email</label></th>
                    <td>
                        <input name="email" id="email" type="email" class="regular-text"
                               value="<?php echo esc_attr( $customer['email'] ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="address">Địa chỉ</label></th>
                    <td>
                        <textarea name="address" id="address" class="large-text" rows="3"><?php echo esc_textarea( $customer['address'] ); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="note">Ghi chú</label></th>
                    <td>
                        <textarea name="note" id="note" class="large-text" rows="3"><?php echo esc_textarea( $customer['note'] ); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="status">Trạng thái</label></th>
                    <td>
                        <select name="status" id="status">
                            <option value="active" <?php selected( $customer['status'], 'active' ); ?>>Đang hoạt động</option>
                            <option value="inactive" <?php selected( $customer['status'], 'inactive' ); ?>>Ngừng hoạt động</option>
                        </select>
                    </td>
                </tr>
            </table>

            <?php submit_button( 'Cập nhật khách hàng', 'primary', 'cm_submit' ); ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=cm-customers' ) ); ?>" class="button">Hủy</a>
        </form>
    </div>
    <?php
}

/**
 * ========================================
 * 6. HÀM XÓA KHÁCH HÀNG
 * ========================================
 */
function cm_delete_customer( $id ) {
    global $wpdb;
    $table_name = $wpdb->prefix . CM_TABLE_NAME;
    $wpdb->delete( $table_name, array( 'id' => $id ), array( '%d' ) );
}
