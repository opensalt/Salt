<?php

namespace App\Entity\FrontMatter;

use App\Domain\FrontMatter\DTO\FrontMatterDto;
use App\Infrastructure\AddUserId;
use App\Repository\FrontMatterRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ecotone\Modelling\Attribute\Aggregate;
use Ecotone\Modelling\Attribute\CommandHandler;
use Ecotone\Modelling\Attribute\Identifier;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[AddUserId]
#[Aggregate]
#[ORM\Entity(repositoryClass: FrontMatterRepository::class)]
#[UniqueEntity('filename')]
class FrontMatter
{
    #[Identifier]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $filename = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $source = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $lastUpdated;

    private function __construct()
    {
        $this->lastUpdated = new \DateTimeImmutable();
    }

    public function getId(): ?int
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
        $this->lastUpdated = $lastUpdated;

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
