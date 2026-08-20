<?php
/**
 * Plugin Name: Simple Color Changer
 * Plugin URI:  https://example.com
 * Description: Cho phép đổi màu sắc website (nền, chữ, link, nút, header, footer...) thông qua một trang menu riêng trong Dashboard, không cần sửa code.
 * Version:     1.0.0
 * Author:      Claude
 * Text Domain: simple-color-changer
 */

// Chặn truy cập trực tiếp file
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Simple_Color_Changer {

    const OPTION_KEY = 'scc_colors';
    const NONCE_KEY  = 'scc_save_colors_nonce';

    /**
     * Danh sách các màu có thể tuỳ chỉnh: key => [label, css_selector, css_property, default]
     */
    private $fields = array();

    public function __construct() {
        $this->fields = array(
            'body_bg' => array(
                'label'    => 'Màu nền trang (Body Background)',
                'selector' => 'body',
                'property' => 'background-color',
                'default'  => '#ffffff',
            ),
            'text_color' => array(
                'label'    => 'Màu chữ chính (Text)',
                'selector' => 'body',
                'property' => 'color',
                'default'  => '#333333',
            ),
            'link_color' => array(
                'label'    => 'Màu liên kết (Link)',
                'selector' => 'a',
                'property' => 'color',
                'default'  => '#0073aa',
            ),
            'link_hover_color' => array(
                'label'    => 'Màu liên kết khi hover',
                'selector' => 'a:hover',
                'property' => 'color',
                'default'  => '#005177',
            ),
            'header_bg' => array(
                'label'    => 'Màu nền Header',
                'selector' => 'header, .site-header',
                'property' => 'background-color',
                'default'  => '#222222',
            ),
            'header_text' => array(
                'label'    => 'Màu chữ Header',
                'selector' => 'header, .site-header',
                'property' => 'color',
                'default'  => '#ffffff',
            ),
            'footer_bg' => array(
                'label'    => 'Màu nền Footer',
                'selector' => 'footer, .site-footer',
                'property' => 'background-color',
                'default'  => '#222222',
            ),
            'footer_text' => array(
                'label'    => 'Màu chữ Footer',
                'selector' => 'footer, .site-footer',
                'property' => 'color',
                'default'  => '#ffffff',
            ),
            'button_bg' => array(
                'label'    => 'Màu nền nút (Button)',
                'selector' => 'button, .button, input[type="submit"], input[type="button"]',
                'property' => 'background-color',
                'default'  => '#0073aa',
            ),
            'button_text' => array(
                'label'    => 'Màu chữ nút (Button)',
                'selector' => 'button, .button, input[type="submit"], input[type="button"]',
                'property' => 'color',
                'default'  => '#ffffff',
            ),
        );

        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'maybe_save_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_head', array( $this, 'output_custom_css' ), 100 );
    }

    /**
     * Lấy giá trị màu hiện tại (đã lưu hoặc mặc định)
     */
    private function get_colors() {
        $saved = get_option( self::OPTION_KEY, array() );
        $colors = array();
        foreach ( $this->fields as $key => $field ) {
            $colors[ $key ] = isset( $saved[ $key ] ) && $saved[ $key ] !== ''
                ? sanitize_hex_color( $saved[ $key ] )
                : $field['default'];
        }
        return $colors;
    }

    /**
     * Thêm menu vào Dashboard
     */
    public function add_admin_menu() {
        add_menu_page(
            'Đổi màu Website',           // Page title
            'Đổi màu Website',           // Menu title
            'manage_options',            // Capability
            'simple-color-changer',      // Slug
            array( $this, 'render_settings_page' ),
            'dashicons-admin-appearance',
            61
        );
    }

    /**
     * Nạp Color Picker của WordPress cho trang admin của plugin
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'toplevel_page_simple-color-changer' !== $hook ) {
            return;
        }
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_script(
            'scc-admin-script',
            plugins_url( 'admin-script.js', __FILE__ ),
            array( 'jquery', 'wp-color-picker' ),
            '1.0.0',
            true
        );
    }

    /**
     * Xử lý lưu dữ liệu khi submit form
     */
    public function maybe_save_settings() {
        if ( ! isset( $_POST['scc_submit'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( ! isset( $_POST[ self::NONCE_KEY ] ) || ! wp_verify_nonce( $_POST[ self::NONCE_KEY ], 'scc_save_colors' ) ) {
            return;
        }

        $new_colors = array();
        foreach ( $this->fields as $key => $field ) {
            if ( isset( $_POST[ $key ] ) ) {
                $color = sanitize_hex_color( wp_unslash( $_POST[ $key ] ) );
                $new_colors[ $key ] = $color ? $color : $field['default'];
            } else {
                $new_colors[ $key ] = $field['default'];
            }
        }

        update_option( self::OPTION_KEY, $new_colors );

        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-success is-dismissible"><p>Đã lưu màu sắc thành công!</p></div>';
        } );
    }

    /**
     * Giao diện trang cài đặt trong Dashboard
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $colors = $this->get_colors();
        ?>
        <div class="wrap">
            <h1>Đổi màu Website</h1>
            <p>Chọn màu sắc cho các thành phần bên dưới, sau đó bấm <strong>Lưu thay đổi</strong>. Màu sẽ được áp dụng ngay trên toàn bộ trang web (front-end).</p>

            <form method="post" action="">
                <?php wp_nonce_field( 'scc_save_colors', self::NONCE_KEY ); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                    <?php foreach ( $this->fields as $key => $field ) : ?>
                        <tr>
                            <th scope="row">
                                <label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
                            </th>
                            <td>
                                <input
                                    type="text"
                                    id="<?php echo esc_attr( $key ); ?>"
                                    name="<?php echo esc_attr( $key ); ?>"
                                    value="<?php echo esc_attr( $colors[ $key ] ); ?>"
                                    class="scc-color-field"
                                    data-default-color="<?php echo esc_attr( $field['default'] ); ?>"
                                />
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="submit">
                    <input type="submit" name="scc_submit" class="button button-primary" value="Lưu thay đổi" />
                </p>
            </form>

            <hr/>
            <h2>Xem trước nhanh</h2>
            <p>Sau khi lưu, hãy mở trang chủ website để xem thay đổi thực tế. Lưu ý: nếu theme của bạn dùng CSS với độ ưu tiên (specificity) cao hơn, một số màu có thể cần chỉnh sửa selector trong code plugin để áp dụng đúng.</p>
        </div>
        <?php
    }

    /**
     * In CSS tuỳ chỉnh ra front-end (trong thẻ <head>)
     */
    public function output_custom_css() {
        $colors = $this->get_colors();
        echo "\n<!-- Simple Color Changer: Custom CSS -->\n";
        echo '<style id="scc-custom-css">' . "\n";
        foreach ( $this->fields as $key => $field ) {
            $value = isset( $colors[ $key ] ) ? $colors[ $key ] : $field['default'];
            printf(
                "%s { %s: %s !important; }\n",
                $field['selector'],
                $field['property'],
                esc_attr( $value )
            );
        }
        echo "</style>\n";
    }
}

new Simple_Color_Changer();
