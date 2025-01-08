<?php

namespace App\Domain\Issuer\ReadModel;

use App\Domain\Issuer\DTO\IssuerDto;
use App\Domain\Issuer\Entity\Issuer;
use App\Domain\Issuer\Event\IssuerDidWasAdded;
use App\Domain\Issuer\Event\IssuerDidWasRemoved;
use Ecotone\EventSourcing\Attribute\Projection;
use Ecotone\EventSourcing\Attribute\ProjectionDelete;
use Ecotone\EventSourcing\Attribute\ProjectionReset;
use Ecotone\Messaging\Attribute\Parameter\Reference;
use Ecotone\Messaging\Store\Document\DocumentStore;
use Ecotone\Modelling\Attribute\EventHandler;
use Ecotone\Modelling\Attribute\QueryHandler;
use Ecotone\Modelling\QueryBus;

#[Projection(name: self::NAME, fromStreams: Issuer::STREAM)]
class IssuerByDidProjection
{
    public const string NAME = 'issuer_did';
    public const string QUERY_ISSUER_BY_DID = 'getIssuerByDid';

    public function __construct(
        #[Reference] private readonly DocumentStore $documentStore,
        #[Reference] private readonly QueryBus $queryBus,
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

    #[EventHandler(IssuerDidWasAdded::NAME)]
    public function whenIssuerDidAdded(IssuerDidWasAdded $event): void
    {
        $dto = $this->queryBus->sendWithRouting(IssuerListProjection::QUERY_ISSUER_BY_ID, ['id' => $event->id]);

        $this->documentStore->addDocument(
            self::NAME,
            $event->did,
            ['id' => $event->id->toRfc4122()]
        );
    }

    #[EventHandler(IssuerDidWasRemoved::NAME)]
    public function whenIssuerDidRemoved(IssuerDidWasRemoved $event): void
    {
        $this->documentStore->deleteDocument(
            self::NAME,
            $event->did
        );
    }

    #[QueryHandler(self::QUERY_ISSUER_BY_DID)]
    public function getIssuerByDid(array $query): IssuerDto
    {
        $didRec = $this->documentStore->getDocument(self::NAME, $query['did'] ?? null);

        return $this->queryBus->sendWithRouting(IssuerListProjection::QUERY_ISSUER_BY_ID, ['id' => $didRec['id']]);
    }
}
