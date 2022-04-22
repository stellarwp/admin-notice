<?php

namespace StellarWP\AdminNotice;

/**
 * Ajax handler for tracking dismissed notices.
 *
 * This class is self-contained: simply call `DismissalHandler::listen()` somewhere in your codebase
 * and the appropriate WP-Ajax handler will be registered.
 */
class DismissalHandler
{
    /**
     * Handle the dismissal of an admin notice.
     *
     * @return void
     */
    public static function handle()
    {
        if (empty($_POST['notice']) || empty($_POST['_wpnonce'])) {
            wp_send_json_error('Required fields missing.', 422);
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if (! wp_verify_nonce(wp_unslash($_POST['_wpnonce']), AdminNotice::NONCE_DISMISS_NOTICE)) {
            wp_send_json_error('Nonce validation failed.', 403);
        }

        AdminNotice::dismissNoticeForUser(sanitize_text_field(wp_unslash($_POST['notice'])), get_current_user_id());

        wp_send_json_success();
    }

    /**
     * Add the appropriate hooks to track dismissed admin notices.
     *
     * @return void
     */
    public static function listen()
    {
        add_action('wp_ajax_' . AdminNotice::ACTION_DISMISSAL, [static::class, 'handle']);
    }
}
