<?php

defined( 'ABSPATH' ) || exit;


/**
 * Hide the admin bar for non-admin users.
 */
add_action(
  'after_setup_theme',
  /**
   * Fires after the theme is loaded.
   */
  function ()
  {
    if ( ! is_admin() && ! current_user_can( 'manage_options' ) ) {
      show_admin_bar( false );
    }
  }
);


/**
 * Re-define nocache headers.
 */
add_filter(
  'nocache_headers',
  /**
   * Filters the cache-controlling headers.
   *
   * @param array $headers {
   *   Header names and field values.
   *   @type string $Expires       Expires header.
   *   @type string $Cache-Control Cache-Control header.
   * }
   *
   * @link https://developer.wordpress.org/reference/functions/wp_get_nocache_headers/
   */
  function ( $headers )
  {
    return wp_parse_args(
      array(
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma'        => 'no-cache',
        'Expires'       => gmdate( DateTime::RFC7231, time() ),
      ),
      $headers
    );
  }
);

/**
 * Always apply nocache headers.
 */
add_filter(
  'wp_headers',
  /**
   * Filters the HTTP headers before they're sent to the browser.
   *
   * @param string[] $headers Associative array of headers to be sent.
   * @param WP       $wp      Current WordPress environment instance.
   */
  function ( $headers/*, $wp*/ )
  {
    return wp_parse_args( wp_get_nocache_headers(), $headers );
  }
);


/**
 * Handle #loginout# menu item placeholders.
 *
 * @param array $items An array of menu item post objects.
 */
add_filter(
  'wp_get_nav_menu_items',
  /**
   * Filters the navigation menu items being returned.
   *
   * @param array  $items An array of menu item post objects.
   * @param object $menu  The menu object.
   * @param array  $args  An array of arguments used to retrieve menu item objects.
   *
   * @link https://developer.wordpress.org/reference/hooks/wp_get_nav_menu_items/
   */
  function ( $items/*, $menu, $args*/ )
  {
    global $pagenow;

    if ( $pagenow === 'nav-menus.php' || defined( 'DOING_AJAX' ) ) {
      return $items;
    }

    $items_visible = array();

    /**
     * @see https://developer.wordpress.org/reference/functions/auth_redirect/
     */
    $redirect = ( strpos( $_SERVER['REQUEST_URI'], '/options.php' ) && wp_get_referer() ) ? wp_get_referer() : set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );

    foreach ( $items as $item ) {
      if ( ! empty( $item->url ) && 0 === strpos( $item->url, '#loginout#' ) ) {
        if ( ! is_user_logged_in() ) {
          // $item->url   = esc_url( wp_login_url( $redirect ) );
          // $item->title = __( 'Log in', 'trader' );
        } else {
          $item->url   = esc_url( wp_logout_url() );
          $item->title = __( 'Log out', 'trader' );

          $items_visible[] = $item;
        }
      } else {
        $items_visible[] = $item;
      }
    }

    return $items_visible;
  }
);


/**
 * Add support to easily manage loginout menu item in the admin nav menu screen. WIP !!
 *
 * @link https://wordpress.org/plugins/login-logout-menu/
 */
// add_action(
// 'admin_head-nav-menus.php',
// **
// * Fires in head section for a specific admin page.
// *
// * The dynamic portion of the hook, `$hook_suffix`, refers to the hook suffix for the admin page.
// *
// * @link https://developer.wordpress.org/reference/hooks/admin_head-hook_suffix/
// */
// function ()
// {
// **
// * Adds a meta box to one or more screens.
// *
// * @link https://developer.wordpress.org/reference/functions/add_meta_box/
// */
// add_meta_box(
// 'trader-loginout-menu-item',
// 'Login/Logout menu item',
// **
// * The admin nav menu screen output callback.
// *
// * @link https://developer.wordpress.org/reference/functions/wp_nav_menu_setup/
// * @link https://developer.wordpress.org/reference/functions/wp_nav_menu_item_link_meta_box/
// */
// function ( $args )
// {},
// 'nav-menus',
// 'side',
// 'low',
// array()
// );
// }
// );
