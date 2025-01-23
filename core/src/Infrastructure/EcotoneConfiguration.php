<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Credential\Entity\CredentialDefinition;
use Ecotone\Dbal\Configuration\DbalConfiguration;
use Ecotone\Messaging\Attribute\ServiceContext;

class EcotoneConfiguration
{
    #[ServiceContext]
    public function getDbalConfiguration(): DbalConfiguration
    {
        return DbalConfiguration::createWithDefaults()
            ->withDoctrineORMRepositories(true)
            ->withDocumentStore(
                enableDocumentStoreStandardRepository: true,
                documentStoreRelatedAggregates: [CredentialDefinition::class]
            )
        ;
    }
}
