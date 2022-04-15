<?php

namespace Tests\Feature;

use StellarWP\AdminNotice\AdminNotice;
use WP_UnitTestCase;

/**
 * @testdox Dismissible notice behavior
 *
 * @covers StellarWP\AdminNotice\AdminNotice
 */
class DismissalTest extends WP_UnitTestCase
{
    /**
     * The generated test user ID.
     *
     * @var int
     */
    protected $userId;

    public function set_up()
    {
        parent::set_up();

        // Automatically set up a user and its context.
        $this->userId = $this->factory->user->create();
        wp_set_current_user($this->userId);
    }

    /**
     * @test
     */
    public function it_should_render_a_notice_that_the_user_has_not_yet_dismissed()
    {
        $notice = AdminNotice::factory('Some message')
            ->setDismissible(true, 'some-key');

        $this->assertNotEmpty($notice->render());
    }

    /**
     * @test
     */
    public function it_should_not_render_a_notice_if_the_user_has_previously_dismissed_it()
    {
        $notice = AdminNotice::factory('Some message')
            ->setDismissible(true, 'some-key')
            ->dismissForUser($this->userId);

        $this->assertEmpty($notice->render());
    }

    /**
     * @test
     */
    public function notices_with_the_same_dismissibleKey_should_respect_dismissal_history()
    {
        AdminNotice::factory('Some message')
            ->setDismissible(true, 'some-key')
            ->dismissForUser($this->userId);

        $notice = AdminNotice::factory('Some message')
            ->setDismissible(true, 'some-key');

        $this->assertEmpty($notice->render(), 'The previously-dismissed notice should not have been rendered.');
    }
}
