<?php

declare(strict_types=1);

namespace CallScheduler\Tests;

use CallScheduler\Consultant;
use CallScheduler\ConsultantRepository;
use WP_UnitTestCase;

class ConsultantRepositoryTest extends WP_UnitTestCase
{
    private ConsultantRepository $repository;
    private int $wpUserId;

    public function set_up(): void
    {
        parent::set_up();
        $this->repository = new ConsultantRepository();
        $this->wpUserId = $this->factory->user->create(['display_name' => 'Test User']);
    }

    public function test_creates_consultant_for_user(): void
    {
        $consultant = $this->repository->createForUser($this->wpUserId);

        $this->assertInstanceOf(Consultant::class, $consultant);
        $this->assertEquals($this->wpUserId, $consultant->wpUserId);
        $this->assertEquals('Test User', $consultant->displayName);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{8}$/', $consultant->publicId);
        $this->assertTrue($consultant->isActive);
    }

    public function test_finds_consultant_by_public_id(): void
    {
        $created = $this->repository->createForUser($this->wpUserId);

        $found = $this->repository->findByPublicId($created->publicId);

        $this->assertNotNull($found);
        $this->assertEquals($created->id, $found->id);
    }

    public function test_finds_consultant_by_wp_user_id(): void
    {
        $created = $this->repository->createForUser($this->wpUserId);

        $found = $this->repository->findByWpUserId($this->wpUserId);

        $this->assertNotNull($found);
        $this->assertEquals($created->id, $found->id);
    }

    public function test_returns_null_for_nonexistent_public_id(): void
    {
        $found = $this->repository->findByPublicId('nonexist');

        $this->assertNull($found);
    }

    public function test_get_active_consultants(): void
    {
        $this->repository->createForUser($this->wpUserId);

        $user2 = $this->factory->user->create(['display_name' => 'User 2']);
        $this->repository->createForUser($user2);

        $active = $this->repository->getActiveConsultants();

        $this->assertCount(2, $active);
    }

    public function test_deactivates_consultant(): void
    {
        $consultant = $this->repository->createForUser($this->wpUserId);

        $this->repository->setActive($consultant->id, false);

        $found = $this->repository->findByPublicId($consultant->publicId);
        $this->assertFalse($found->isActive);
    }

    public function test_updates_consultant_profile(): void
    {
        $consultant = $this->repository->createForUser($this->wpUserId);

        $this->repository->updateProfile($consultant->id, 'New Name', 'Sales Rep', 'Bio text');

        $found = $this->repository->findByPublicId($consultant->publicId);
        $this->assertEquals('New Name', $found->displayName);
        $this->assertEquals('Sales Rep', $found->title);
        $this->assertEquals('Bio text', $found->bio);
    }
}
