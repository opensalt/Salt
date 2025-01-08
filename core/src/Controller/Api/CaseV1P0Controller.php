<?php

namespace App\Controller\Api;

use App\Entity\ChangeEntry;
use App\Entity\Framework\CaseApiInterface;
use App\Entity\Framework\CfRubric;
use App\Entity\Framework\CfRubricCriterion;
use App\Entity\Framework\CfRubricCriterionLevel;
use App\Entity\Framework\LsAssociation;
use App\Entity\Framework\LsDefAssociationGrouping;
use App\Entity\Framework\LsDefConcept;
use App\Entity\Framework\LsDefItemType;
use App\Entity\Framework\LsDefLicence;
use App\Entity\Framework\LsDefSubject;
use App\Entity\Framework\LsDoc;
use App\Entity\Framework\LsItem;
use App\Repository\ChangeEntryRepository;
use App\Repository\Framework\CfDocQuery;
use App\Repository\Framework\LsAssociationRepository;
use App\Repository\Framework\LsDocRepository;
use App\Security\Permission;
use App\Service\LoggerTrait;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route(path: '/ims/case/v1p0')]
class CaseV1P0Controller extends AbstractController
{
    use LoggerTrait;

    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly string          $assetsVersion,
        private readonly ManagerRegistry $managerRegistry,
    ) {
    }

    #[Route(path: '/CFDocuments.{_format}', name: 'api_v1p0_cfdocuments', defaults: ['_format' => 'json'], methods: ['GET'])]
    public function getPublicCfDocuments(Request $request, LsDocRepository $docRepository): Response
    {
        $limit = (int) $request->query->get('limit', '100');
        $offset = (int) $request->query->get('offset', '0');
        $sort = (string) $request->query->get('sort', null);
        $orderBy = (string) $request->query->get('orderBy', 'asc');

        /*
        $filter = $request->query->get('filter', '');
        $fields = $request->query->get('fields', []);
        */

        $query = new CfDocQuery();
        $query->limit = 100000;
        $query->offset = 0;
        $query->sort = $sort;
        $query->orderBy = $orderBy;

        $results = $docRepository->findAllNonPrivate($query);

        $docs = [];
        $docCount = 0;
        $lastModified = new \DateTime('now - 10 years');
        foreach ($results as $doc) {
            if (LsDoc::ADOPTION_STATUS_PRIVATE_DRAFT === $doc->getAdoptionStatus()
                && !$this->isGranted(Permission::FRAMEWORK_LIST, $doc)) {
                continue;
            }

            $docs[] = $doc;
            ++$docCount;

            if ($lastModified < $doc->getUpdatedAt()) {
                $lastModified = $doc->getUpdatedAt();
            }
        }

        $docs = array_slice($docs, $offset, $limit);

        $this->info('CASE API: getPublicCfDocuments', []);

        $response = $this->generateBaseResponse($lastModified);
        if ($response->isNotModified($request)) {
            return $response;
        }

        $groups = ['default', 'LsDoc'];
        if ('updatedAt' === $sort) {
            $groups[] = 'updatedAt';
        }
        $response->setContent(
            $this->serializer->serialize(['CFDocuments' => $docs], 'json', [
                'groups' => $groups,
                'json_encode_options' => \JSON_UNESCAPED_SLASHES|\JSON_PRESERVE_ZERO_FRACTION,
            ])
        );
        $response->headers->set('X-Total-Count', (string) $docCount);

        return $response;
    }

    #[Route(path: '/CFPackages/{id}.{_format}', name: 'api_v1p0_cfpackage', defaults: ['_format' => 'json'], methods: ['GET'])]
    public function getCfPackage(Request $request, #[MapEntity(expr: 'repository.findOneByIdentifier(id)')] LsDoc $obj): Response
    {
        $id = $obj->getIdentifier();

        $this->info('CASE API: package returned', ['id' => $id]);

        /** @var ChangeEntryRepository $changeRepo */
        $changeRepo = $this->managerRegistry->getRepository(ChangeEntry::class);
        $lastChange = $changeRepo->getLastChangeTimeForDoc($obj);

        $lastModified = $obj->getUpdatedAt();
        if (null !== ($lastChange['changed_at'] ?? null)) {
            $lastModified = new \DateTime($lastChange['changed_at'], new \DateTimeZone('UTC'));
        }
        $response = $this->generateBaseResponse($lastModified);
        if ($response->isNotModified($request)) {
            return $response;
        }

        $response->setContent(
            $this->serializer->serialize($obj, 'json', [
                'groups' => ['default', 'CfPackage'],
                'json_encode_options' => \JSON_UNESCAPED_SLASHES|\JSON_PRESERVE_ZERO_FRACTION,
                'generate-package' => 'v1p0',
            ])
        );

        return $response;
    }

    #[Route(path: '/CFItemAssociations/{id}.{_format}', name: 'api_v1p0_cfitemassociations', defaults: ['_format' => 'json'], methods: ['GET'])]
    #[Route(path: '/CFItems/{id}/associations.{_format}', name: 'api_v1p0_cfitemassociations2', defaults: ['_format' => 'json'], methods: ['GET'])]
    public function getCfItemAssociations(Request $request, #[MapEntity(expr: 'repository.findOneByIdentifier(id)')] LsItem $obj, LsAssociationRepository $associationRepository): Response
    {
        $item = $obj;
        $id = $obj->getIdentifier();
        $itemDocId = $obj->getLsDoc()->getId();

        $associations = [];
        $lastModified = $item->getUpdatedAt();

        $results = $associationRepository->findAllAssociationsFor($id);
        foreach ($results as $association) {
            /** @var LsDoc $associationDoc */
            $associationDoc = $association->getLsDoc();
            if ($itemDocId !== $associationDoc->getId()
                && LsDoc::ADOPTION_STATUS_PRIVATE_DRAFT === $associationDoc->getAdoptionStatus()
                && !$this->isGranted(Permission::FRAMEWORK_LIST, $associationDoc)) {
                continue;
            }

            $originDoc = $association->getOriginLsDoc() ?? $association->getOriginLsItem()?->getLsDoc();
            if (null !== $originDoc
                && $itemDocId !== $originDoc->getId()
                && LsDoc::ADOPTION_STATUS_PRIVATE_DRAFT === $originDoc->getAdoptionStatus()
                && !$this->isGranted(Permission::FRAMEWORK_LIST, $originDoc)) {
                continue;
            }

            $destDoc = $association->getDestinationLsDoc() ?? $association->getDestinationLsItem()?->getLsDoc();
            if (null !== $destDoc
                && $itemDocId !== $destDoc->getId()
                && LsDoc::ADOPTION_STATUS_PRIVATE_DRAFT === $destDoc->getAdoptionStatus()
                && !$this->isGranted(Permission::FRAMEWORK_LIST, $destDoc)) {
                continue;
            }

            $associations[] = $association;
            if ($association->getUpdatedAt() > $lastModified) {
                $lastModified = $association->getUpdatedAt();
            }
        }

        $this->info('CASE API: item associations returned', ['id' => $id]);

        $response = $this->generateBaseResponse($lastModified);
        if ($response->isNotModified($request)) {
            return $response;
        }

        $response->setContent(
            $this->serializer->serialize([
                'CFItem' => $item,
                'CFAssociations' => $associations,
            ], 'json', [
                'groups' => ['default', 'LsItem', 'LsAssociation'],
                'json_encode_options' => \JSON_UNESCAPED_SLASHES|\JSON_PRESERVE_ZERO_FRACTION,
            ])
        );
        $response->headers->set('X-Total-Count', [(string) count($associations)]);

        return $response;
    }

    #[Route(path: '/CFAssociationGroupings/{id}.{_format}', name: 'api_v1p0_cfassociationgrouping', defaults: ['class' => LsDefAssociationGrouping::class, '_format' => 'json'], methods: ['GET'])]
    #[Route(path: '/CFAssociations/{id}.{_format}', name: 'api_v1p0_cfassociation', defaults: ['class' => LsAssociation::class, '_format' => 'json'], methods: ['GET'])]
    #[Route(path: '/CFDocuments/{id}.{_format}', name: 'api_v1p0_cfdocument', defaults: ['class' => LsDoc::class, '_format' => 'json'], methods: ['GET'])]
    #[Route(path: '/CFItems/{id}.{_format}', name: 'api_v1p0_cfitem', defaults: ['class' => LsItem::class, '_format' => 'json'], methods: ['GET'])]
    #[Route(path: '/CFLicenses/{id}.{_format}', name: 'api_v1p0_cflicense', defaults: ['class' => LsDefLicence::class, '_format' => 'json'], methods: ['GET'])]
    #[Route(path: '/CFRubrics/{id}.{_format}', name: 'api_v1p0_cfrubric', defaults: ['class' => CfRubric::class, '_format' => 'json'], methods: ['GET'])]
    #[Route(path: '/CFRubricCriteria/{id}.{_format}', name: 'api_v1p0_cfrubriccriterion', defaults: ['class' => CfRubricCriterion::class, '_format' => 'json'], methods: ['GET'])]
    #[Route(path: '/CFRubricCriterionLevels/{id}.{_format}', name: 'api_v1p0_cfrubriccriterionlevel', defaults: ['class' => CfRubricCriterionLevel::class, '_format' => 'json'], methods: ['GET'])]
    public function getObject(Request $request, LsDocRepository $repo, string $class, string $id): Response
    {
        $obj = $repo->apiFindOneByClassIdentifier(['class' => $class, 'id' => $id]);

        return $this->generateObjectResponse($request, $obj);
    }

    #[Route(path: '/CFConcepts/{id}.{_format}', name: 'api_v1p0_cfconcept', defaults: ['class' => LsDefConcept::class, '_format' => 'json'], methods: ['GET'])]
    #[Route(path: '/CFItemTypes/{id}.{_format}', name: 'api_v1p0_cfitemtype', defaults: ['class' => LsDefItemType::class, '_format' => 'json'], methods: ['GET'])]
    #[Route(path: '/CFSubjects/{id}.{_format}', name: 'api_v1p0_cfsubject', defaults: ['class' => LsDefSubject::class, '_format' => 'json'], methods: ['GET'])]
    public function getObjectCollection(Request $request, LsDocRepository $repo, string $class, string $id): Response
    {
        $obj = $repo->apiFindOneByClassIdentifier(['class' => $class, 'id' => $id]);

        return $this->generateObjectCollectionResponse($request, $obj);
    }

    /**
     * Generate a base response.
     */
    protected function generateBaseResponse(\DateTimeInterface $lastModified): Response
    {
        $response = new Response();

        $response->setEtag(md5($lastModified->format('U.u').$this->assetsVersion), true);
        $response->setLastModified($lastModified);
        $response->setMaxAge(60);
        $response->setSharedMaxAge(60);
        $response->setPublic();
        $response->setVary('Accept-Encoding, Accept', true);

        return $response;
    }

    /**
     * Generate a response for a single object.
     */
    protected function generateObjectResponse(Request $request, CaseApiInterface $obj): Response
    {
        $this->info('CASE API: Returned object', ['type' => $obj::class, 'id' => $obj->getIdentifier()]);

        $response = $this->generateBaseResponse($obj->getUpdatedAt());

        if ($response->isNotModified($request)) {
            return $response;
        }

        $className = substr(strrchr($obj::class, '\\'), 1);
        $response->setContent(
            $this->serializer->serialize($obj, 'json', [
                'groups' => ['default', $className],
                'json_encode_options' => \JSON_UNESCAPED_SLASHES|\JSON_PRESERVE_ZERO_FRACTION,
            ])
        );

        return $response;
    }

    /**
     * Generate a response for a collection of objects.
     */
    protected function generateObjectCollectionResponse(Request $request, CaseApiInterface $obj): Response
    {
        $this->info('CASE API: Returned object', ['type' => $obj::class, 'id' => $obj->getIdentifier()]);

        $response = $this->generateBaseResponse(new \DateTime());

        if ($response->isNotModified($request)) {
            return $response;
        }

        $collection = explode('/', $request->getPathInfo())[4];

        $className = substr(strrchr($obj::class, '\\'), 1);
        $response->setContent(
            $this->serializer->serialize([$collection => [$obj]], 'json', [
                'groups' => ['default', $className],
                'json_encode_options' => \JSON_UNESCAPED_SLASHES|\JSON_PRESERVE_ZERO_FRACTION,
            ])
        );

        return $response;
    }
}
