<?php

declare(strict_types=1);

namespace CallScheduler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Consultant entity - public-facing abstraction over WordPress users
 */
final class Consultant
{
    public readonly int $id;
    public readonly string $publicId;
    public readonly int $wpUserId;
    public readonly string $displayName;
    public readonly ?string $title;
    public readonly ?string $bio;
    public readonly bool $isActive;
    public readonly string $createdAt;

    public function __construct(
        int $id,
        string $publicId,
        int $wpUserId,
        string $displayName,
        ?string $title,
        ?string $bio,
        bool $isActive,
        string $createdAt
    ) {
        $this->id = $id;
        $this->publicId = $publicId;
        $this->wpUserId = $wpUserId;
        $this->displayName = $displayName;
        $this->title = $title;
        $this->bio = $bio;
        $this->isActive = $isActive;
        $this->createdAt = $createdAt;
    }

    /**
     * Create from database row
     */
    public static function fromRow(object $row): self
    {
        return new self(
            (int) $row->id,
            $row->public_id,
            (int) $row->wp_user_id,
            $row->display_name,
            $row->title,
            $row->bio,
            (bool) $row->is_active,
            $row->created_at
        );
    }

    /**
     * Generate unique 8-char public ID
     */
    public static function generatePublicId(): string
    {
        return bin2hex(random_bytes(4));
    }
}
