<?php

namespace Tests\Feature;

use StellarWP\AdminNotice\AdminNotice;
use WP_UnitTestCase;

/**
 * @testdox Capability check behavior
 *
 * @covers StellarWP\AdminNotice\AdminNotice
 */
class CapabilitiesTest extends WP_UnitTestCase
{
    /**
     * @test
     */
    public function it_should_render_a_notice_if_no_capability_check_is_specified()
    {
        $this->assertStringContainsString(
            'Some message',
            (new AdminNotice('Some message'))->setCapability(null)->render()
        );
    }

    /**
     * @test
     */
    public function it_should_render_a_notice_if_the_current_user_has_the_specified_capability()
    {
        wp_set_current_user($this->factory->user->create([
            'role' => 'administrator',
        ]));

        $this->assertStringContainsString(
            'Some message',
            (new AdminNotice('Some message'))->setCapability('manage_options')->render(),
            'Administrators should have the "manage_options" capability.'
        );
    }

    /**
     * @test
     */
    public function it_should_not_render_a_notice_if_the_current_user_lacks_the_specified_capability()
    {
        wp_set_current_user($this->factory->user->create([
            'role' => 'editor',
        ]));

        $this->assertEmpty(
            (new AdminNotice('Some message'))->setCapability('manage_options')->render(),
            'Editors do not have the "manage_options" capability.'
        );
    }
}
