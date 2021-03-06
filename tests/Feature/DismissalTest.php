<?php

namespace Tests\Feature;

use StellarWP\AdminNotice\AdminNotice;
use StellarWP\AdminNotice\DismissalHandler;
use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;

/**
 * @testdox Dismissible notice behavior
 *
 * @covers StellarWP\AdminNotice\DismissalHandler
 */
class DismissalTest extends WP_Ajax_UnitTestCase
{
    public function set_up()
    {
        parent::set_up();

        $_POST = [];

        wp_set_current_user($this->factory->user->create());
    }

    /**
     * @test
     */
    public function the_dismissal_handler_should_track_dismissed_notices_for_the_user()
    {
        $response = $this->sendAjaxRequest([
            'notice'   => 'some-notice',
            '_wpnonce' => wp_create_nonce(AdminNotice::NONCE_DISMISS_NOTICE),
        ]);

        $this->assertTrue($response->success, 'Expected a successful response.');

        $notice = AdminNotice::factory('Some notice')
            ->setDismissible(true, 'some-notice');

        $this->assertTrue($notice->dismissedByUser(), 'Expected the notice to have been marked as dismissed.');
    }

    /**
     * @test
     */
    public function the_dismissal_handler_should_do_nothing_if_required_fields_are_missing()
    {
        $response = $this->sendAjaxRequest([]);

        $this->assertFalse($response->success, 'Did not expect a successful response.');
    }

    /**
     * @test
     */
    public function the_dismissal_handler_should_do_nothing_if_nonce_validation_fails()
    {
        $response = $this->sendAjaxRequest([
            'notice'   => 'some-notice',
            '_wpnonce' => 'some-invalid-nonce',
        ]);

        $this->assertFalse($response->success, 'Did not expect a successful response.');
    }

    /**
     * Make an Ajax request.
     *
     * @param Array<string,mixed> $post The contents to include in the $_POST superglobal.
     *
     * @return {success: bool, data: mixed} The JSON-decoded response.
     */
    protected function sendAjaxRequest($post = [])
    {
        // Prevent deprecation warnings from breaking the JSON.
        $level = error_reporting(E_ERROR);

        try {
            $_POST = array_merge($_POST, $post);

            DismissalHandler::listen();
            $this->_handleAjax(AdminNotice::ACTION_DISMISSAL);
        } catch (WPAjaxDieContinueException $e) {
            error_reporting($level);
            return json_decode($this->_last_response, false);
        }

        error_reporting($level);
        $this->fail('Did not catch the expected WPAjaxDieContinueException.');
    }
}
