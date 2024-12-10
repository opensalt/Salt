<?php

namespace App\Domain\Issuer\Entity;

use App\Domain\Issuer\Command\AddIssuerCommand;
use App\Domain\Issuer\Command\UpdateIssuerCommand;
use App\Domain\Issuer\Event\IssuerWasAdded;
use App\Domain\Issuer\Event\IssuerWasUpdated;
use App\Infrastructure\AddUserId;
use Ecotone\EventSourcing\Attribute\AggregateType;
use Ecotone\EventSourcing\Attribute\Stream;
use Ecotone\Modelling\Attribute\CommandHandler;
use Ecotone\Modelling\Attribute\EventSourcingAggregate;
use Ecotone\Modelling\Attribute\EventSourcingHandler;
use Ecotone\Modelling\Attribute\Identifier;
use Ecotone\Modelling\WithAggregateVersioning;
use Symfony\Component\Uid\Uuid;

#[EventSourcingAggregate]
#[AddUserId]
#[Stream(self::STREAM)]
#[AggregateType(self::AGGREGATE_TYPE)]
class Issuer
{
    use WithAggregateVersioning;

    public const string STREAM = 'issuer_stream';
    public const string AGGREGATE_TYPE = 'issuer';

    #[Identifier]
    private Uuid $id;

    private string $name;
    private ?string $did = null;
    private ?string $contact = null;
    private ?string $notes = null;
    private ?bool $trusted = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function getDid(): ?string
    {
        return $this->did;
    }

    public function getContact(): ?string
    {
        return $this->contact;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getTrusted(): ?bool
    {
        return $this->trusted;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    #[CommandHandler]
    public static function addIssuerToRegistry(AddIssuerCommand $command): array
    {
        return [
            new IssuerWasAdded(
                $command->id ?? Uuid::v7(),
                $command->name,
                $command->did,
                $command->contact,
                $command->notes,
                $command->trusted
            ),
        ];
    }

    #[EventSourcingHandler]
    public function applyIssuerWasAdded(IssuerWasAdded $event): void
    {
        $this->id = $event->id;
        $this->name = $event->name;
        $this->did = $event->did ?? null;
        $this->contact = $event->contact ?? null;
        $this->notes = $event->notes ?? null;
        $this->trusted = $event->trusted ?? null;
    }

    #[CommandHandler]
    public function update(UpdateIssuerCommand $command): array
    {
        return [
            new IssuerWasUpdated(
                $command->id,
                $command->name,
                $command->did,
                $command->contact,
                $command->notes,
                $command->trusted
            ),
        ];
    }

    #[EventSourcingHandler]
    public function applyIssuerWasUpdated(IssuerWasUpdated $event): void
    {
        $this->name = $event->name;
        $this->did = $event->did ?? null;
        $this->contact = $event->contact ?? null;
        $this->notes = $event->notes ?? null;
        $this->trusted = $event->trusted ?? null;
    }
}
