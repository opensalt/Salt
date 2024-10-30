<?php

namespace App\Domain\Credential;

use Symfony\Component\Uid\Uuid;

class CredentialDefinitionVersion
{
    public const string STATE_DRAFT = 'Draft';
    public const string STATE_PUBLISHED = 'Published';
    public const string STATE_DEPRECATED = 'Deprecated';

    private string $state = self::STATE_DRAFT;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $publishedAt = null;
    private ?\DateTimeImmutable $deprecatedAt = null;

    public function __construct(
        private Uuid $id,
        private array $content = [],
        private int $definitionVersion = 1,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getContent(): array
    {
        return $this->content;
    }

    public function getDefinitionVersion(): int
    {
        return $this->definitionVersion;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getDeprecatedAt(): ?\DateTimeImmutable
    {
        return $this->deprecatedAt;
    }

    public function publish(): void
    {
        $this->state = self::STATE_PUBLISHED;
        $this->publishedAt = new \DateTimeImmutable();
    }

    public function deprecate(): void
    {
        $this->state = self::STATE_DEPRECATED;
        $this->deprecatedAt = new \DateTimeImmutable();
    }

    public function updateContent(array $content): void
    {
        $this->content = $content;
    }
}
