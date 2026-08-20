<?php
/**
 * FORM ĐĂNG KÝ KHÁCH HÀNG - HIỂN THỊ NGOÀI WEBSITE (FRONTEND)
 * Dùng shortcode: [customer_registration_form]
 * Chèn shortcode này vào bất kỳ trang/bài viết nào để hiển thị form cho khách tự đăng ký.
 * Dữ liệu khách gửi lên sẽ được lưu vào cùng bảng wp_customers và hiện trong menu "Khách hàng" ở Dashboard.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Đăng ký shortcode
 */
add_shortcode( 'customer_registration_form', 'cm_render_frontend_registration_form' );

function cm_render_frontend_registration_form( $atts = array() ) {

    global $wpdb;
    $table_name = $wpdb->prefix . CM_TABLE_NAME;

    $success_message = '';
    $error_messages   = array();

    // Giá trị mặc định giữ lại khi submit lỗi (để khách không phải nhập lại)
    $old = array(
        'full_name' => '',
        'phone'     => '',
        'email'     => '',
        'address'   => '',
        'note'      => '',
    );

    // Xử lý khi khách submit form
    if ( isset( $_POST['cm_frontend_submit'] ) && isset( $_POST['cm_frontend_nonce'] ) ) {

        if ( ! wp_verify_nonce( $_POST['cm_frontend_nonce'], 'cm_frontend_register' ) ) {
            $error_messages[] = 'Phiên làm việc đã hết hạn, vui lòng tải lại trang và thử lại.';
        } else {

            $old['full_name'] = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
            $old['phone']     = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
            $old['email']     = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
            $old['address']   = sanitize_textarea_field( wp_unslash( $_POST['address'] ?? '' ) );
            $old['note']      = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );

            // Chống spam bot đơn giản (honeypot field ẩn)
            if ( ! empty( $_POST['cm_website'] ) ) {
                $error_messages[] = 'Đăng ký không hợp lệ.';
            }

            if ( empty( $old['full_name'] ) ) {
                $error_messages[] = 'Vui lòng nhập họ và tên.';
            }
            if ( empty( $old['phone'] ) ) {
                $error_messages[] = 'Vui lòng nhập số điện thoại.';
            }
            if ( ! empty( $old['email'] ) && ! is_email( $old['email'] ) ) {
                $error_messages[] = 'Email không hợp lệ.';
            }

            // Kiểm tra trùng số điện thoại (tránh đăng ký trùng)
            if ( empty( $error_messages ) ) {
                $existing = $wpdb->get_var(
                    $wpdb->prepare( "SELECT id FROM $table_name WHERE phone = %s", $old['phone'] )
                );
                if ( $existing ) {
                    $error_messages[] = 'Số điện thoại này đã được đăng ký trước đó.';
                }
            }

            if ( empty( $error_messages ) ) {
                $inserted = $wpdb->insert(
                    $table_name,
                    array(
                        'full_name'  => $old['full_name'],
                        'phone'      => $old['phone'],
                        'email'      => $old['email'],
                        'address'    => $old['address'],
                        'note'       => $old['note'],
                        'status'     => 'active',
                        'created_at' => current_time( 'mysql' ),
                        'updated_at' => current_time( 'mysql' ),
                    ),
                    array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
                );

                if ( $inserted ) {
                    $success_message = 'Đăng ký thành công! Cảm ơn bạn đã để lại thông tin, chúng tôi sẽ liên hệ sớm nhất.';
                    // Reset lại form sau khi thành công
                    $old = array( 'full_name' => '', 'phone' => '', 'email' => '', 'address' => '', 'note' => '' );

                    // (Tùy chọn) Gửi email thông báo cho admin khi có khách hàng mới
                    $admin_email = get_option( 'admin_email' );
                    if ( $admin_email ) {
                        wp_mail(
                            $admin_email,
                            'Có khách hàng mới đăng ký trên website',
                            "Khách hàng mới vừa đăng ký:\n\nHọ tên: {$old['full_name']}\nSĐT: {$_POST['phone']}\nEmail: {$_POST['email']}"
                        );
                    }
                } else {
                    $error_messages[] = 'Có lỗi xảy ra, vui lòng thử lại sau.';
                }
            }
        }
    }

    ob_start();
    ?>
    <div class="cm-frontend-form-wrapper">
        <style>
            .cm-frontend-form-wrapper { max-width: 520px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; }
            .cm-frontend-form-wrapper h3 { margin-bottom: 8px; }
            .cm-frontend-form-wrapper .cm-field { margin-bottom: 16px; }
            .cm-frontend-form-wrapper label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px; }
            .cm-frontend-form-wrapper input[type="text"],
            .cm-frontend-form-wrapper input[type="email"],
            .cm-frontend-form-wrapper input[type="tel"],
            .cm-frontend-form-wrapper textarea {
                width: 100%; padding: 10px 12px; border: 1px solid #d0d5dd; border-radius: 6px;
                font-size: 14px; box-sizing: border-box;
            }
            .cm-frontend-form-wrapper textarea { min-height: 80px; resize: vertical; }
            .cm-frontend-form-wrapper .cm-required { color: #d63638; }
            .cm-frontend-form-wrapper button[type="submit"] {
                background: #2271b1; color: #fff; border: none; padding: 12px 24px;
                border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer;
            }
            .cm-frontend-form-wrapper button[type="submit"]:hover { background: #135e96; }
            .cm-frontend-form-wrapper .cm-msg-success {
                background: #edfaef; border: 1px solid #00a32a; color: #006418;
                padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 14px;
            }
            .cm-frontend-form-wrapper .cm-msg-error {
                background: #fcf0f1; border: 1px solid #d63638; color: #8a1f1f;
                padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 14px;
            }
            .cm-frontend-form-wrapper .cm-msg-error ul { margin: 0; padding-left: 18px; }
            .cm-website-field { position: absolute; left: -9999px; top: -9999px; }
        </style>

        <h3>Đăng ký thông tin khách hàng</h3>

        <?php if ( $success_message ) : ?>
            <div class="cm-msg-success"><?php echo esc_html( $success_message ); ?></div>
        <?php endif; ?>

        <?php if ( ! empty( $error_messages ) ) : ?>
            <div class="cm-msg-error">
                <ul>
                    <?php foreach ( $error_messages as $err ) : ?>
                        <li><?php echo esc_html( $err ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <?php wp_nonce_field( 'cm_frontend_register', 'cm_frontend_nonce' ); ?>

            <!-- Honeypot chống spam bot -->
            <div class="cm-website-field">
                <label for="cm_website">Website</label>
                <input type="text" name="cm_website" id="cm_website" tabindex="-1" autocomplete="off" />
            </div>

            <div class="cm-field">
                <label for="cm_full_name">Họ và tên <span class="cm-required">*</span></label>
                <input type="text" name="full_name" id="cm_full_name" required
                       value="<?php echo esc_attr( $old['full_name'] ); ?>" />
            </div>

            <div class="cm-field">
                <label for="cm_phone">Số điện thoại <span class="cm-required">*</span></label>
                <input type="tel" name="phone" id="cm_phone" required
                       value="<?php echo esc_attr( $old['phone'] ); ?>" />
            </div>

            <div class="cm-field">
                <label for="cm_email">Email</label>
                <input type="email" name="email" id="cm_email"
                       value="<?php echo esc_attr( $old['email'] ); ?>" />
            </div>

            <div class="cm-field">
                <label for="cm_address">Địa chỉ</label>
                <textarea name="address" id="cm_address"><?php echo esc_textarea( $old['address'] ); ?></textarea>
            </div>

            <div class="cm-field">
                <label for="cm_note">Ghi chú</label>
                <textarea name="note" id="cm_note"><?php echo esc_textarea( $old['note'] ); ?></textarea>
            </div>

            <button type="submit" name="cm_frontend_submit">Đăng ký ngay</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
