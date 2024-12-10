<?php

namespace App\Domain\Issuer\ReadModel;

use App\Domain\Issuer\DTO\IssuerDto;
use App\Domain\Issuer\Entity\Issuer;
use App\Domain\Issuer\Event\IssuerWasAdded;
use App\Domain\Issuer\Event\IssuerWasUpdated;
use Ecotone\EventSourcing\Attribute\Projection;
use Ecotone\EventSourcing\Attribute\ProjectionDelete;
use Ecotone\EventSourcing\Attribute\ProjectionReset;
use Ecotone\Messaging\Attribute\Parameter\Reference;
use Ecotone\Messaging\Store\Document\DocumentStore;
use Ecotone\Modelling\Attribute\EventHandler;
use Ecotone\Modelling\Attribute\QueryHandler;

#[Projection(name: self::NAME, fromStreams: Issuer::STREAM)]
class IssuerByDidProjection
{
    public const string NAME = 'issuer_did';
    public const string QUERY_ISSUER_BY_DID = 'getIssuerByDid';

    public function __construct(
        #[Reference] private readonly DocumentStore $documentStore,
    ) {
    }

    #[ProjectionReset]
    public function reset(): void
    {
        $this->documentStore->dropCollection(self::NAME);
    }

    #[ProjectionDelete]
    public function delete(): void
    {
        $this->documentStore->dropCollection(self::NAME);
    }

    #[EventHandler(IssuerWasAdded::NAME)]
    public function whenIssuerAdded(IssuerWasAdded $event): void
    {
        if (!$event->did) {
            return;
        }

        $dto = new IssuerDto();
        $dto->id = $event->id->toRfc4122();
        $dto->name = $event->name;
        $dto->did = $event->did;
        $dto->contact = $event->contact;
        $dto->notes = $event->notes;
        $dto->trusted = $event->trusted;

        $this->documentStore->addDocument(
            self::NAME,
            $dto->did,
            $dto
        );
    }

    #[EventHandler(IssuerWasUpdated::NAME)]
    public function whenIssuerUpdated(IssuerWasUpdated $event): void
    {
        if (!$event->did) {
            return;
        }

        $dto = new IssuerDto();
        $dto->id = $event->id->toRfc4122();
        $dto->name = $event->name;
        $dto->did = $event->did;
        $dto->contact = $event->contact;
        $dto->notes = $event->notes;
        $dto->trusted = $event->trusted;

        $this->documentStore->updateDocument(
            self::NAME,
            $dto->did,
            $dto
        );
    }

    #[QueryHandler(self::QUERY_ISSUER_BY_DID)]
    public function getIssuerByDid(array $query): array
    {
        return $this->documentStore->getDocument(self::NAME, $query['did'] ?? null);
    }
}
