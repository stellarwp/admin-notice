<?php

namespace Tests\Unit;

use StellarWP\AdminNotice\AdminNotice;
use StellarWP\AdminNotice\Exceptions\ImmutableValueException;
use SteveGrunwell\PHPUnit_Markup_Assertions\MarkupAssertionsTrait;
use WP_UnitTestCase;

/**
 * @testdox The StellarWP\AdminNotice\AdminNotice class
 *
 * @covers StellarWP\AdminNotice\AdminNotice
 */
class AdminNoticeTest extends WP_UnitTestCase
{
    use MarkupAssertionsTrait;

    public function set_up()
    {
        parent::set_up();

        remove_all_actions('admin_enqueue_scripts');
        wp_dequeue_script(AdminNotice::SCRIPT_HANDLE);
    }

    /**
     * @test
     */
    public function it_should_expose_protected_properties_via_magic_getter()
    {
        $notice = new AdminNotice('Some message', AdminNotice::TYPE_SUCCESS);

        $this->assertSame('Some message', $notice->message);
        $this->assertSame(AdminNotice::TYPE_SUCCESS, $notice->type);
        $this->assertFalse($notice->dismissible);
    }

    /**
     * @test
     * @depends it_should_expose_protected_properties_via_magic_getter
     */
    public function it_should_expose_a_fluent_API_for_setting_properties()
    {
        $notice = (new AdminNotice('Some message'))
            ->setAlt(true)
            ->setDismissible(true)
            ->setInline(true);

        $this->assertTrue($notice->alt);
        $this->assertTrue($notice->dismissible);
        $this->assertTrue($notice->inline);
    }

    /**
     * @test
     */
    public function it_should_treat_properties_as_immutable_when_set_directly()
    {
        $notice = new AdminNotice('Some message');

        $this->expectException(ImmutableValueException::class);
        $notice->message = 'Some new message';
    }

    /**
     * @test
     */
    public function it_should_render_when_cast_to_a_string()
    {
        $notice = new AdminNotice('Some message');

        $this->assertSame($notice->render(), (string) $notice);
    }

    /**
     * @test
     */
    public function dismissedByUserAt_should_return_a_DateTime_representing_when_the_notice_was_dismissed()
    {
        $now    = time();
        $userId = $this->factory->user->create();

        update_user_meta($userId, AdminNotice::USER_META_KEY, [
            'some-key' => $now,
        ]);

        $dismissedAt = AdminNotice::factory('Some message')
            ->setDismissible(true, 'some-key')
            ->dismissedByUserAt($userId);

        $this->assertInstanceOf(\DateTimeImmutable::class, $dismissedAt);
        $this->assertSame($now, (int) $dismissedAt->format('U'));
    }

    /**
     * @test
     */
    public function dismissedByUserAt_should_return_null_if_there_is_no_record_of_the_notice_being_dismissed()
    {
        $userId = $this->factory->user->create();

        update_user_meta($userId, AdminNotice::USER_META_KEY, [
            'some-other-key' => time(),
        ]);

        $this->assertNull(AdminNotice::factory('Some message')
            ->setDismissible(true, 'some-key')
            ->dismissedByUserAt($userId));
    }

    /**
     * @test
     */
    public function dismissForUser_should_track_dismissed_notices_for_the_given_user_ID()
    {
        $now    = time();
        $userId = $this->factory->user->create();

        $notice = AdminNotice::factory('Some message')
            ->setDismissible(true, 'some-key')
            ->dismissForUser($userId);

        $this->assertGreaterThanOrEqual($now, $notice->dismissedByUserAt($userId)->format('U'));
    }

    /**
     * @test
     */
    public function dismissForUser_should_default_to_the_current_user_ID()
    {
        $now    = time();
        $userId = $this->factory->user->create();
        wp_set_current_user($userId);

        $notice = AdminNotice::factory('Some message')
            ->setDismissible(true, 'some-key')
            ->dismissForUser();

        $this->assertGreaterThanOrEqual($now, $notice->dismissedByUserAt($userId)->format('U'));
    }

    /**
     * @test
     */
    public function dismissForUser_should_not_track_dismissed_notices_if_not_dismissible()
    {
        $userId = $this->factory->user->create();

        $notice = AdminNotice::factory('Some message')
            ->setDismissible(false)
            ->dismissForUser($userId);

        $this->assertNull($notice->dismissedByUserAt($userId));
    }

    /**
     * @test
     */
    public function dismissForUser_should_not_track_dismissed_notices_without_an_ID()
    {
        $userId = $this->factory->user->create();

        $notice = AdminNotice::factory('Some message')
            ->setDismissible(true, null)
            ->dismissForUser($userId);

        $this->assertNull($notice->dismissedByUserAt($userId));
    }

    /**
     * @test
     */
    public function dismissForUser_should_update_dismissal_timestamps_if_called_multiple_times()
    {
        $now    = time();
        $userId = $this->factory->user->create();
        update_user_meta($userId, AdminNotice::USER_META_KEY, [
            'some-key' => $now - HOUR_IN_SECONDS,
        ]);

        $notice = AdminNotice::factory('Some message')
            ->setDismissible(true, 'some-key')
            ->dismissForUser($userId);

        $this->assertGreaterThanOrEqual($now, $notice->dismissedByUserAt($userId)->format('U'));
    }

    /**
     * @test
     */
    public function display_should_print_the_rendered_notice()
    {
        $notice = new AdminNotice('Some message');

        $this->expectOutputString($notice->render());
        $notice->display();
    }

    /**
     * @test
     * @testdox queue() should attach this notice to the "admin_notices" hook
     */
    public function queue_should_attach_this_notice_to_the_admin_notices_hook()
    {
        $notice = (new AdminNotice('Some message'))
            ->queue();

        $this->assertIsNumeric(has_action('admin_notices', [$notice, 'display']));

        $this->expectOutputString($notice->render());
        do_action('admin_notices');
    }

    /**
     * @test
     * @testdox queue() should attach this notice to the "admin_notices" hook
     */
    public function queue_should_be_able_to_accept_an_alternate_priority()
    {
        $notice = (new AdminNotice('Some message'))
            ->queue(9999);

        $this->assertSame(9999, has_action('admin_notices', [$notice, 'display']));
    }

    /**
     * @test
     */
    public function render_should_return_the_markup_of_the_notice()
    {
        $notice = new AdminNotice('Some message');

        $this->assertElementContains('<p>Some message</p>', 'div.notice', $notice->render());
    }

    /**
     * @test
     * @testWith ["error"]
     *           ["info"]
     *           ["success"]
     *           ["warning"]
     */
    public function render_should_apply_the_class_matching_the_notice_type($type)
    {
        $this->assertContainsSelector(
            '.notice.notice-' . $type,
            (new AdminNotice('Some message', $type))->render()
        );
    }

    /**
     * @test
     * @testdox render() should apply the .notice-alt class if $alt is true
     */
    public function render_should_apply_the_notice_alt_class_if_alt_is_true()
    {
        $notice = (new AdminNotice('Some message'))
            ->setAlt(true);

        $this->assertContainsSelector('.notice.notice-alt', $notice->render());
    }

    /**
     * @test
     * @testdox render() should apply the .is-dismissible class if $alt is true
     */
    public function render_should_apply_the_is_dismissible_class_if_dismissible_is_true()
    {
        $markup = (new AdminNotice('Some message'))
            ->setDismissible(true)
            ->render();

        $this->assertContainsSelector('.notice.is-dismissible', $markup);
        $this->assertStringNotContainsString(
            'data-id="',
            $markup,
            'If no ID was provided, one should not be written to the DOM.'
        );
        $this->assertStringNotContainsString(
            'data-nonce="',
            $markup,
            'No ID means no saving, so no nonce is necessary.'
        );
    }

    /**
     * @test
     */
    public function render_should_render_a_notice_that_the_user_has_not_yet_dismissed()
    {
        $notice = AdminNotice::factory('Some message')
            ->setDismissible(true, 'some-key');

        $this->assertNotEmpty($notice->render());
    }

    /**
     * @test
     */
    public function render_should_not_render_a_notice_if_the_user_has_previously_dismissed_it()
    {
        $userId = $this->factory->user->create();
        wp_set_current_user($userId);

        $notice = AdminNotice::factory('Some message')
            ->setDismissible(true, 'some-key')
            ->dismissForUser($userId);

        $this->assertEmpty($notice->render());
    }

    /**
     * @test
     */
    public function notices_with_the_same_dismissibleKey_should_respect_dismissal_history()
    {
        $userId = $this->factory->user->create();
        wp_set_current_user($userId);

        AdminNotice::factory('Some message')
            ->setDismissible(true, 'some-key')
            ->dismissForUser($userId);

        $notice = AdminNotice::factory('Some message')
            ->setDismissible(true, 'some-key');

        $this->assertEmpty($notice->render(), 'The previously-dismissed notice should not have been rendered.');
    }

    /**
     * @test
     */
    public function render_should_include_data_attributes_for_dismissal_if_an_ID_was_provided()
    {
        $notice = (new AdminNotice('Some message'))
            ->setDismissible(true, 'some-id');

        $this->assertHasElementWithAttributes([
            'data-id'    => 'some-id',
            'data-nonce' => wp_create_nonce(AdminNotice::NONCE_DISMISS_NOTICE),
        ], $notice->render());
    }

    /**
     * @test
     */
    public function render_should_include_not_include_data_attributes_if_dismissible_is_false()
    {
        $notice = (new AdminNotice('Some message'))
            ->setDismissible(true, 'some-id')
            ->setDismissible(false);

        $this->assertNotHasElementWithAttributes([
            'data-id'    => 'some-id',
            'data-nonce' => wp_create_nonce(AdminNotice::NONCE_DISMISS_NOTICE),
        ], $notice->render());
    }

    /**
     * @test
     */
    public function render_should_enqueue_the_appropriate_script_when_rendering_a_dismissible_notice_with_a_key()
    {
        AdminNotice::factory('Some message')
            ->setDismissible(true, 'some-key')
            ->render();

        do_action('admin_enqueue_scripts');
        $this->assertTrue(wp_script_is(AdminNotice::SCRIPT_HANDLE, 'enqueued'));
    }

    /**
     * @test
     */
    public function render_should_not_enqueue_scripts_when_rendering_a_dismissible_notice_without_a_key()
    {
        AdminNotice::factory('Some message')
            ->setDismissible(true, null)
            ->render();

        do_action('admin_enqueue_scripts');
        $this->assertFalse(wp_script_is(AdminNotice::SCRIPT_HANDLE, 'enqueued'));
    }

    /**
     * @test
     */
    public function render_should_not_enqueue_scripts_when_rendering_a_non_dismissible_notice()
    {
        $notice = AdminNotice::factory('Some message')
            ->setDismissible(false);

        $notice->render();

        do_action('admin_enqueue_scripts');
        $this->assertFalse(wp_script_is(AdminNotice::SCRIPT_HANDLE, 'enqueued'));
    }

    /**
     * @test
     * @testdox render() should apply the .inline class if $inline is true
     */
    public function render_should_apply_the_inline_class_if_inline_is_true()
    {
        $notice = (new AdminNotice('Some message'))
            ->setInline(true);

        $this->assertContainsSelector('.notice.inline', $notice->render());
    }

    /**
     * @test
     */
    public function setDismissible_should_not_set_a_dismissibleKey_by_default()
    {
        $notice = (new AdminNotice('Some message'))
            ->setDismissible(true);

        $this->assertEmpty($notice->dismissibleKey);
    }

    /**
     * @test
     */
    public function setDismissible_should_save_a_dismissibleKey_if_provided()
    {
        $notice = (new AdminNotice('Some message'))
            ->setDismissible(true, 'some-id');

        $this->assertSame('some-id', $notice->dismissibleKey);
    }

    /**
     * @test
     */
    public function setDismissible_should_generate_a_dismissibleKey_if_given_true()
    {
        $notice = (new AdminNotice('Some message'))
            ->setDismissible(true, true);

        $this->assertNotEmpty($notice->dismissibleKey);
    }

    /**
     * @test
     */
    public function setDismissible_should_generate_a_dismissibleKey_if_given_false()
    {
        $notice = (new AdminNotice('Some message'))
            ->setDismissible(true, false);

        $this->assertEmpty($notice->dismissibleKey);
    }

    /**
     * @test
     */
    public function the_factory_method_should_return_a_new_instance_with_the_passed_args()
    {
        $notice = AdminNotice::factory('Some message', AdminNotice::TYPE_WARNING);

        $this->assertSame('Some message', $notice->message);
        $this->assertSame(AdminNotice::TYPE_WARNING, $notice->type);
    }
}
