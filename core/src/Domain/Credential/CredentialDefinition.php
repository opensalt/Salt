<?php

declare(strict_types=1);

namespace App\Domain\Credential;

use App\Domain\Credential\Command\ChangeDefinitionContent;
use App\Domain\Credential\Command\ChangeDefinitionHierarchy;
use App\Domain\Credential\Command\ChangeDefinitionOrganization;
use App\Domain\Credential\Command\CreateCredentialDefinitionDraft;
use App\Domain\Credential\Command\DeprecateCredentialDefinition;
use App\Domain\Credential\Command\PublishCredentialDefinition;
use App\Domain\Credential\Event\CredentialDefinitionWasDeprecated;
use App\Domain\Credential\Event\CredentialDefinitionWasPublished;
use App\Domain\Credential\Event\DefinitionContentWasChanged;
use App\Domain\Credential\Event\DefinitionHierarchyWasChanged;
use App\Domain\Credential\Event\DefinitionOrganizationWasChanged;
use App\Domain\Credential\Event\DraftCredentialDefinitionWasCreated;
use App\Domain\Credential\Event\NewCredentialDefinitionVersionWasCreated;
use App\Infrastructure\AddUserId;
use Ecotone\EventSourcing\Attribute\AggregateType;
use Ecotone\EventSourcing\Attribute\Stream;
use Ecotone\Messaging\Attribute\Parameter\Reference;
use Ecotone\Messaging\Support\Assert;
use Ecotone\Modelling\Attribute\CommandHandler;
use Ecotone\Modelling\Attribute\EventSourcingAggregate;
use Ecotone\Modelling\Attribute\EventSourcingHandler;
use Ecotone\Modelling\Attribute\Identifier;
use Ecotone\Modelling\WithAggregateVersioning;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Uid\Uuid;

#[EventSourcingAggregate]
#[AddUserId]
#[Stream(self::STREAM)]
#[AggregateType('credential_definition')]
final class CredentialDefinition
{
    use WithAggregateVersioning;

    public const string STREAM = 'credential_definition_stream';
    public const string CREATE_DRAFT_CREDENTIAL_DEFINITION = 'credential_definition.create_draft';

    #[Identifier]
    private Uuid $id;
    private string $hierarchyParent;
    private int $organization;
    /** @var CredentialDefinitionVersion[] */
    private array $versions = [];

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getState(): string
    {
        $lastVer = $this->getLastVersion();
        Assert::notNull($lastVer, 'The Credential Definition has no versions.');

        return $lastVer->getState();
    }

    public function getHierarchyParent(): string
    {
        return $this->hierarchyParent;
    }

    public function getOrganization(): int
    {
        return $this->organization;
    }

    /**
     * @return CredentialDefinitionVersion[]
     */
    public function getVersions(): array
    {
        return $this->versions;
    }

    public function getLastVersionNumber(): int
    {
        $keys = array_keys($this->versions);

        Assert::isTrue(0 !== count($keys), 'The credential definition has no versions');

        return max($keys);
    }

    public function getLastVersion(): CredentialDefinitionVersion
    {
        $key = $this->getLastVersionNumber();

        return $this->versions[$key];
    }

    #[CommandHandler]
    public static function createDraft(CreateCredentialDefinitionDraft $command, #[Reference] RouterInterface $router): array
    {
        $id = Uuid::v7();
        $verId = Uuid::v7();
        $createdAt = new \DateTimeImmutable();
        $content = $command->content;
        $content['id'] = $router->generate('credential_show', ['id' => $id->toBase58(), 'versionId' => $verId->toBase58()], Router::ABSOLUTE_URL);

        return [new DraftCredentialDefinitionWasCreated(
            $command->hierarchyParent,
            $command->organization,
            json_encode($content),
            $id,
            $verId,
            $createdAt
        )];
    }

    #[EventSourcingHandler]
    public function applyDraftCredentialDefinitionWasCreated(DraftCredentialDefinitionWasCreated $event): void
    {
        $this->id = $event->id;
        $this->hierarchyParent = $event->hierarchyParent;
        $this->organization = $event->organization;

        $content = json_decode($event->content, true);

        $ver = new CredentialDefinitionVersion($event->versionId, $content, 1);

        $this->versions[1] = $ver;
    }

    #[CommandHandler]
    public function publish(PublishCredentialDefinition $publish): array
    {
        Assert::isTrue($publish->id->equals($this->id), 'Id being published does not match id requested');
        $lastVer = $this->getLastVersion();

        Assert::null($lastVer->getPublishedAt(), 'Credential definition has already been published.');

        return [new CredentialDefinitionWasPublished($this->id, $lastVer->getId())];
    }

    #[EventSourcingHandler]
    public function applyCredentialDefinitionWasPublished(CredentialDefinitionWasPublished $event): void
    {
        $lastVerNumber = $this->getLastVersionNumber();
        Assert::isTrue($this->versions[$lastVerNumber]->getId()->equals($event->versionId), 'Version being published does not match version requested');

        $this->versions[$lastVerNumber]->publish();
    }

    #[CommandHandler]
    public function deprecate(DeprecateCredentialDefinition $deprecate): array
    {
        $lastVer = $this->getLastVersion();

        Assert::null($lastVer->getDeprecatedAt(), 'Credential definition is already deprecated.');
        Assert::notNull($lastVer->getPublishedAt(), 'Credential definition cannot be deprecated unless it is published.');

        return [new CredentialDefinitionWasDeprecated($this->id, $lastVer->getId())];
    }

    #[EventSourcingHandler]
    public function applyCredentialDefinitionWasDeprecated(CredentialDefinitionWasDeprecated $event): void
    {
        $lastVerNumber = $this->getLastVersionNumber();
        $this->versions[$lastVerNumber]->deprecate();
    }

    #[CommandHandler]
    public function changeContent(ChangeDefinitionContent $command, #[Reference] RouterInterface $router): array
    {
        $lastVer = $this->getLastVersion();

        if (!$lastVer->getPublishedAt()) {
            // Not published, so we can just make the change
            return [new DefinitionContentWasChanged($this->id, $lastVer->getId(), $command->newContent)];
        }

        $verId = Uuid::v7();
        $createdAt = new \DateTimeImmutable();

        $content = json5_decode($command->newContent, true);
        $content['id'] = $router->generate('credential_show', ['id' => $command->id->toBase58(), 'versionId' => $verId->toBase58()], Router::ABSOLUTE_URL);
        $content = json_encode($content);

        if (!$lastVer->getDeprecatedAt()) {
            return [
                new CredentialDefinitionWasDeprecated($this->id, $lastVer->getId()),
                new NewCredentialDefinitionVersionWasCreated($this->id, $verId, $this->hierarchyParent, $this->organization, $content, $createdAt),
            ];
        }

        return [new NewCredentialDefinitionVersionWasCreated($this->id, $verId, $this->hierarchyParent, $this->organization, $content, $createdAt)];
    }

    #[EventSourcingHandler]
    public function applyChangeDefinitionContent(DefinitionContentWasChanged $event): void
    {
        $lastVerNumber = $this->getLastVersionNumber();
        Assert::isTrue($this->versions[$lastVerNumber]->getId()->equals($event->versionId), 'Version id does not match draft definition version');

        $this->versions[$lastVerNumber]->updateContent(json_decode($event->newContent, true));
    }

    #[EventSourcingHandler]
    public function applyCreateNewCredentialDefinitionVersion(NewCredentialDefinitionVersionWasCreated $event): void
    {
        $lastVer = $this->getLastVersion();

        if (null === $lastVer->getPublishedAt()) {
            throw new \DomainException('Credential definition cannot be replaced unless it is published.');
        }

        $nextVerNumber = $lastVer->getDefinitionVersion() + 1;

        $newVer = new CredentialDefinitionVersion($event->versionId, json5_decode($event->content, true), $nextVerNumber);
        $this->versions[$nextVerNumber] = $newVer;
    }

    #[CommandHandler]
    public function changeHierarchyParent(ChangeDefinitionHierarchy $command): array
    {
        if ($this->hierarchyParent === $command->hierarchyParent) {
            return [];
        }

        return [new DefinitionHierarchyWasChanged($this->id, $command->hierarchyParent)];
    }

    #[EventSourcingHandler]
    public function hierarchyWasChanged(DefinitionHierarchyWasChanged $event): void
    {
        $this->hierarchyParent = $event->hierarchyParent;
    }

    #[CommandHandler]
    public function changeOrganization(ChangeDefinitionOrganization $command): array
    {
        if ($this->organization === $command->organization) {
            return [];
        }

        return [new DefinitionOrganizationWasChanged($this->id, $command->organization)];
    }

    #[EventSourcingHandler]
    public function organizationWasChanged(DefinitionOrganizationWasChanged $event): void
    {
        $this->organization = $event->organization;
    }
}
