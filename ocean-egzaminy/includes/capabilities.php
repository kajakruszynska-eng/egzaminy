<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Plugin capabilities.
 *   oe_manage_exams        exams, signups, status changes, CSV export
 *   oe_generate_documents  DOCX paperwork (contains participants' personal data)
 * Administrators always get both. Other roles are assigned on the settings page.
 */

define( 'OE_CAPS_VERSION', '1' );

function oe_capabilities() {
    return array(
        'oe_manage_exams'       => 'Zarządzanie egzaminami i zapisami',
        'oe_generate_documents' => 'Generowanie dokumentów DOCX',
    );
}

/** Primitive caps for register_post_type(), all mapped to oe_manage_exams. */
function oe_cpt_capabilities( $extra = array() ) {
    $caps = array();
    foreach ( array(
        'edit_posts', 'edit_others_posts', 'edit_published_posts', 'edit_private_posts',
        'publish_posts', 'read_private_posts',
        'delete_posts', 'delete_others_posts', 'delete_published_posts', 'delete_private_posts',
        'create_posts',
    ) as $c ) {
        $caps[ $c ] = 'oe_manage_exams';
    }
    return array_merge( $caps, $extra );
}

function oe_grant_admin_caps() {
    $admin = get_role( 'administrator' );
    if ( ! $admin ) return;
    foreach ( array_keys( oe_capabilities() ) as $cap ) {
        $admin->add_cap( $cap );
    }
}

// Activation does not run when a plugin zip replaces an older version, so check on every admin load.
add_action( 'admin_init', function() {
    if ( get_option( 'oe_caps_version' ) === OE_CAPS_VERSION ) return;
    oe_grant_admin_caps();
    update_option( 'oe_caps_version', OE_CAPS_VERSION );
    // The current user's caps were computed before this ran; refresh them for this request.
    $user = wp_get_current_user();
    if ( $user && $user->exists() ) $user->get_role_caps();
}, 1 );

function oe_render_capabilities_form() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $caps = oe_capabilities();
    ?>
    <h2>Uprawnienia</h2>
    <p>Administrator ma zawsze oba uprawnienia. Zaznacz, które inne role mają do nich dostęp.</p>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="oe_zapisz_uprawnienia">
        <?php wp_nonce_field( 'oe_zapisz_uprawnienia' ); ?>
        <table class="widefat striped" style="max-width:720px">
            <thead><tr><th>Rola</th>
            <?php foreach ( $caps as $label ) : ?><th><?php echo esc_html( $label ); ?></th><?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php foreach ( wp_roles()->roles as $slug => $role ) :
                if ( $slug === 'administrator' ) continue;
                ?>
                <tr>
                    <td><?php echo esc_html( translate_user_role( $role['name'] ) ); ?></td>
                    <?php foreach ( $caps as $cap => $label ) : ?>
                        <td><input type="checkbox" name="oe_role[<?php echo esc_attr( $slug ); ?>][]" value="<?php echo esc_attr( $cap ); ?>"
                            <?php checked( ! empty( $role['capabilities'][ $cap ] ) ); ?>></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php submit_button( 'Zapisz uprawnienia', 'secondary' ); ?>
    </form>
    <?php
}

add_action( 'admin_post_oe_zapisz_uprawnienia', function() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Brak uprawnień.' );
    check_admin_referer( 'oe_zapisz_uprawnienia' );

    $posted = isset( $_POST['oe_role'] ) && is_array( $_POST['oe_role'] ) ? wp_unslash( $_POST['oe_role'] ) : array();
    foreach ( wp_roles()->role_objects as $slug => $role ) {
        if ( $slug === 'administrator' ) continue;
        $wanted = isset( $posted[ $slug ] ) && is_array( $posted[ $slug ] ) ? $posted[ $slug ] : array();
        foreach ( array_keys( oe_capabilities() ) as $cap ) {
            if ( in_array( $cap, $wanted, true ) ) {
                $role->add_cap( $cap );
            } else {
                $role->remove_cap( $cap );
            }
        }
    }
    oe_grant_admin_caps();

    wp_safe_redirect( add_query_arg( 'oe_msg', 'uprawnienia_ok', admin_url( 'edit.php?post_type=oe_egzamin&page=oe-ustawienia' ) ) );
    exit;
} );
