<?php

namespace App\Domain\FrontMatter\Entity;

use App\Domain\FrontMatter\DTO\FrontMatterDto;
use App\Infrastructure\AddUserId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ecotone\Modelling\Attribute\Aggregate;
use Ecotone\Modelling\Attribute\CommandHandler;
use Ecotone\Modelling\Attribute\Identifier;
use Ramsey\Uuid\Doctrine\UuidBinaryType;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[AddUserId]
#[Aggregate]
#[ORM\Entity(repositoryClass: FrontMatterRepository::class)]
#[UniqueEntity('filename')]
class FrontMatter
{
    #[Identifier]
    #[ORM\Id]
    #[ORM\Column(type: UuidBinaryType::NAME)]
    private UuidInterface $id;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $filename = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $source = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $lastUpdated;

    private function __construct(?UuidInterface $id = null)
    {
        $this->id = $id ?? Uuid::uuid7();
        $this->lastUpdated = new \DateTimeImmutable();
    }

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getLastUpdated(): \DateTimeInterface
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(\DateTimeInterface $lastUpdated): static
    {
        $this->lastUpdated = \DateTimeImmutable::createFromInterface($lastUpdated);

        return $this;
    }

    #[CommandHandler('createFrontMatter')]
    public static function create(FrontMatterDto $dto): self
    {
        $template = new self();
        $template->setFilename($dto->filename);
        $template->setSource($dto->source);

        return $template;
    }

    #[CommandHandler('updateFrontMatter')]
    public function update(FrontMatterDto $dto): void
    {
        $this->setFilename($dto->filename);
        $this->setSource($dto->source);
        $this->setLastUpdated(new \DateTimeImmutable());
    }
}
