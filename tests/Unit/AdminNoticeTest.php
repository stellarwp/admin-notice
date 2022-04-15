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

    /**
     * @test
     */
    public function it_should_expose_protected_properties_via_magic_getter()
    {
        $notice = new AdminNotice('Some message', AdminNotice::TYPE_SUCCESS, 'some-id');

        $this->assertSame('Some message', $notice->message);
        $this->assertSame(AdminNotice::TYPE_SUCCESS, $notice->type);
        $this->assertSame('some-id', $notice->id);
        $this->assertTrue($notice->dismissible);
    }

    /**
     * @test
     * @depends it_should_expose_protected_properties_via_magic_getter
     */
    public function it_should_generate_an_ID_if_one_is_not_provided()
    {
        $notice = new AdminNotice('Some message', AdminNotice::TYPE_INFO, '');

        $this->assertNotEmpty($notice->id, 'An ID should have been generated for this notice.');
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
            ->setId('some-id')
            ->setInline(true)
            ->setType(AdminNotice::TYPE_SUCCESS);

        $this->assertTrue($notice->alt);
        $this->assertTrue($notice->dismissible);
        $this->assertSame('some-id', $notice->id);
        $this->assertTrue($notice->inline);
        $this->assertSame(AdminNotice::TYPE_SUCCESS, $notice->type);
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
    public function display_should_print_the_rendered_notice()
    {
        $notice = new AdminNotice('Some message');

        $this->expectOutputString($notice->render());
        $notice->display();
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
     */
    public function render_should_include_data_attributes_for_dismissal()
    {
        $this->markTestIncomplete();

        $notice = new AdminNotice('Some message');

        $this->assertHasElementWithAttributes([
            'data-id'    => $notice->id,
            'data-nonce' => wp_create_nonce(AdminNotice::NONCE_DISMISS_NOTICE)
        ], $notice->render());
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
        $notice = (new AdminNotice('Some message'))
            ->setDismissible(true);

        $this->assertContainsSelector('.notice.is-dismissible', $notice->render());
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
}
