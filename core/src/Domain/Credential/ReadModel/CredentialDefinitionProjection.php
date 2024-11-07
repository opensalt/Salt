<?php

namespace App\Domain\Credential\ReadModel;

use App\Domain\Credential\CredentialDefinition;
use App\Domain\Credential\Event\DefinitionContentWasChanged;
use App\Domain\Credential\Event\DefinitionHierarchyWasChanged;
use App\Domain\Credential\Event\DefinitionOrganizationWasChanged;
use App\Domain\Credential\Event\DraftCredentialDefinitionWasCreated;
use App\Domain\Credential\Event\NewCredentialDefinitionVersionWasCreated;
use Ecotone\EventSourcing\Attribute\Projection;
use Ecotone\EventSourcing\Attribute\ProjectionDelete;
use Ecotone\EventSourcing\Attribute\ProjectionReset;
use Ecotone\Messaging\Attribute\Parameter\Reference;
use Ecotone\Messaging\Store\Document\DocumentStore;
use Ecotone\Modelling\Attribute\EventHandler;
use Ecotone\Modelling\Attribute\QueryHandler;

#[Projection(self::NAME, CredentialDefinition::STREAM)]
class CredentialDefinitionProjection
{
    public const string NAME = 'credential_definition_list';

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

    #[EventHandler(DraftCredentialDefinitionWasCreated::NAME)]
    public function whenDraftCredentialDefinitionWasCreated(DraftCredentialDefinitionWasCreated $event): void
    {
        $json = json5_decode($event->content, true);

        $this->documentStore->addDocument(
            self::NAME,
            $event->id,
            [
                'id' => $event->id->toBase58(),
                'versionId' => $event->versionId->toBase58(),
                'hierarchyParent' => $event->hierarchyParent,
                'org' => $event->organization,
                'state' => 'Draft',
                'name' => $json['name'] ?? 'Unknown',
                //'content' => $event->content,
            ]
        );
    }

    #[EventHandler(DefinitionContentWasChanged::NAME)]
    public function whenDefinitionContentWasChanged(DefinitionContentWasChanged $event): void
    {
        $json = json5_decode($event->newContent, true);

        $curDoc = $this->documentStore->getDocument(self::NAME, $event->id);
        $curDoc['name'] = $json['name'] ?? 'Unknown';

        $this->documentStore->updateDocument(
            self::NAME,
            $event->id,
            $curDoc
        );
    }

    #[EventHandler(NewCredentialDefinitionVersionWasCreated::NAME)]
    public function whenNewCredentialDefinitionWasCreated(NewCredentialDefinitionVersionWasCreated $event): void
    {
        $json = json5_decode($event->content, true);

        $this->documentStore->updateDocument(
            self::NAME,
            $event->id,
            [
                'id' => $event->id->toBase58(),
                'versionId' => $event->versionId->toBase58(),
                'hierarchyParent' => $event->hierarchyParent,
                'org' => $event->organization,
                'state' => 'Draft',
                'name' => $json['name'] ?? 'Unknown',
                //'content' => $event->content,
            ]
        );
    }

    #[EventHandler(DefinitionHierarchyWasChanged::NAME)]
    public function whenDefinitionHierarchyWasChanged(DefinitionHierarchyWasChanged $event): void
    {
        $docVersion = $this->documentStore->getDocument(self::NAME, $event->id);
        $docVersion['hierarchyParent'] = $event->hierarchyParent;
        $this->documentStore->updateDocument(self::NAME, $event->id, $docVersion);
    }

    #[EventHandler(DefinitionOrganizationWasChanged::NAME)]
    public function whenDefinitionOrganizationWasChanged(DefinitionOrganizationWasChanged $event): void
    {
        $docVersion = $this->documentStore->getDocument(self::NAME, $event->id);
        $docVersion['org'] = $event->organization;
        $this->documentStore->updateDocument(self::NAME, $event->id, $docVersion);
    }

    #[QueryHandler('getAllCredentialDefinitions')]
    public function getCredentialDefinitions(): array
    {
        return $this->documentStore->getAllDocuments(self::NAME);
    }
}
