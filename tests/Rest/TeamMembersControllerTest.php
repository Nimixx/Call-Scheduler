<?php

declare(strict_types=1);

namespace CallScheduler\Tests\Rest;

use WP_REST_Request;
use WP_UnitTestCase;

class TeamMembersControllerTest extends WP_UnitTestCase
{
    public function set_up(): void
    {
        parent::set_up();
        do_action('rest_api_init');
    }

    public function test_returns_empty_array_when_no_team_members(): void
    {
        $request = new WP_REST_Request('GET', '/cs/v1/team-members');
        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
        $this->assertIsArray($response->get_data());
        $this->assertEmpty($response->get_data());
    }

    public function test_returns_team_members_with_correct_structure(): void
    {
        $user_id = $this->factory->user->create([
            'display_name' => 'John Doe',
            'user_email' => 'john@example.com',
        ]);
        update_user_meta($user_id, 'cs_is_team_member', '1');

        $request = new WP_REST_Request('GET', '/cs/v1/team-members');
        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertCount(1, $data);
        $this->assertEquals($user_id, $data[0]['id']);
        $this->assertEquals('John Doe', $data[0]['name']);
        $this->assertEquals('john@example.com', $data[0]['email']);
    }

    public function test_excludes_non_team_members(): void
    {
        $team_member = $this->factory->user->create([
            'display_name' => 'Team Member',
        ]);
        update_user_meta($team_member, 'cs_is_team_member', '1');

        $regular_user = $this->factory->user->create([
            'display_name' => 'Regular User',
        ]);

        $request = new WP_REST_Request('GET', '/cs/v1/team-members');
        $response = rest_do_request($request);

        $data = $response->get_data();
        $this->assertCount(1, $data);
        $this->assertEquals('Team Member', $data[0]['name']);
    }
}
