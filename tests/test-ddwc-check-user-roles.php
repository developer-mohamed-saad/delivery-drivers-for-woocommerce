<?php
/**
 * Tests for ddwc_check_user_roles helper function.
 */

class DDWCTest_Check_User_Roles extends WP_UnitTestCase {

    /**
     * Should return true when user has a matching role.
     */
    public function test_returns_true_with_correct_role() {
        $user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
        $this->assertTrue( ddwc_check_user_roles( [ 'administrator' ], $user_id ) );
    }

    /**
     * Should return false when user lacks the required role.
     */
    public function test_returns_false_without_role() {
        $user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
        $this->assertFalse( ddwc_check_user_roles( [ 'administrator' ], $user_id ) );
    }

    /**
     * Should return false when an unknown user ID is provided.
     */
    public function test_returns_false_with_invalid_user_id() {
        $this->assertFalse( ddwc_check_user_roles( [ 'subscriber' ], 999999 ) );
    }
}
