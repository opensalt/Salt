<?php

namespace App\Repository\Framework;

use App\Entity\Framework\CaseApiInterface;
use App\Entity\Framework\LsAssociation;
use App\Entity\Framework\LsDoc;
use App\Util\Compare;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * LsDocRepository.
 *
 * @method LsDoc|null find(int $id, $lockMode = null, $lockVersion = null)
 * @method LsDoc[]|array findByCreator(string $creator)
 * @method LsDoc|null findOneByIdentifier(string $identifier)
 * @method LsDoc|null findOneBy(array $criteria, array $orderBy = null)
 */
class LsDocRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LsDoc::class);
    }

    /**
     * @return LsDoc[]
     */
    public function findForList(): array
    {
        return $this->createQueryBuilder('d')
            ->addSelect('d, s, m')
            ->leftJoin('d.subjects', 's')
            ->leftJoin('d.mirroredFramework', 'm')
            ->orderBy('d.creator', 'ASC')
            ->addOrderBy('d.title', 'ASC')
            ->addOrderBy('d.adoptionStatus', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds an object for the API by ['id'=>identifier, 'class'=>class].
     *
     * @param array{'class': class-string, 'id': string} $id
     *
     * @throws NotFoundHttpException
     */
    public function apiFindOneByClassIdentifier(array $id): CaseApiInterface
    {
        /** @var ?CaseApiInterface $obj */
        $obj = $this->_em->getRepository($id['class'])->findOneBy(['identifier' => $id['id']]);
        if (null === $obj) {
            throw new NotFoundHttpException(sprintf('%s object not found.', $id['class']));
        }

        return $obj;
    }

    public function findOneBySlug(string $slug): ?LsDoc
    {
        if (preg_match('/^\d+$/', $slug)) {
            return $this->find((int) $slug);
        }

        return $this->findOneBy(['urlName' => $slug]);
    }

    public function findAllNonPrivateQueryBuilder(string $alias = 'd'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->where("({$alias}.adoptionStatus != :status OR {$alias}.adoptionStatus IS NULL)")
            ->setParameter('status', LsDoc::ADOPTION_STATUS_PRIVATE_DRAFT)
            ;
    }

    /**
     * @return LsDoc[]
     */
    public function findAllNonPrivate(?CfDocQuery $query = null): array
    {
        if (null === $query) {
            $query = new CfDocQuery();
        }

        $sortBy = match ($query->getSort()) {
            'updatedAt' => 'd.updatedAt',
            'title' => 'd.title',
            'identifier' => 'd.identifier',
            'lastChangeDateTime' => 'd.changedAt',
            default => 'd.id',
        };

        $qb = $this->findAllNonPrivateQueryBuilder('d');
        $qb->setFirstResult($query->getOffset())
            ->setMaxResults($query->getLimit())
            ->addOrderBy($sortBy, $query->getOrderBy())
        ;

        return $qb->getQuery()->getResult();
    }

    /**
     * @return LsDoc[]
     */
    public function findNonPrivateByCreator(string $creator): array
    {
        $qb = $this->findAllNonPrivateQueryBuilder()
            ->andWhere('d.creator = :creator')
            ->setParameter('creator', $creator)
            ;

        return $qb->getQuery()->getResult();
    }

    /**
     * Get a list of all items for an LsDoc.
     *
     * @return array array of LsItems hydrated as an array
     */
    public function findAllChildrenArray(LsDoc $lsDoc): array
    {
        $query = $this->getEntityManager()->createQuery('
            SELECT i, t, a, g, adi, add
            FROM App\Entity\Framework\LsItem i INDEX BY i.id
            LEFT JOIN i.itemType t
            LEFT JOIN i.associations a WITH a.lsDoc = :lsDocId AND a.type = :childOfType
            LEFT JOIN a.group g
            LEFT JOIN a.destinationLsItem adi WITH adi.lsDoc = :lsDocId
            LEFT JOIN a.destinationLsDoc add WITH add.id = :lsDocId
            WHERE i.lsDoc = :lsDocId
        ');
        $query->setParameter('lsDocId', $lsDoc->getId());
        $query->setParameter('childOfType', LsAssociation::CHILD_OF);

        /** @var array $results */
        $results = $query->getResult(Query::HYDRATE_ARRAY);

        foreach ($results as $key => $result) {
            $results[$key]['children'] = [];
        }

        foreach ($results as $key => $result) {
            foreach ($result['associations'] as $association) {
                if (!empty($association['destinationLsItem'])) {
                    $parent = $association['destinationLsItem'];
                    $results[$parent['id']]['children'][] = $result;

                    if (!empty($association['group'])) {
                        $results[$key]['assoc'][$parent['id']] = [
                            'id' => $association['id'],
                            'sequenceNumber' => $association['sequenceNumber'],
                            'group' => $association['group']['id'],
                        ];
                    } else {
                        $results[$key]['assoc'][$parent['id']] = [
                            'id' => $association['id'],
                            'sequenceNumber' => $association['sequenceNumber'],
                            'group' => '',
                        ];
                    }
                } elseif (!empty($association['destinationLsDoc'])) {
                    if (!empty($association['group'])) {
                        $results[$key]['assoc']['doc'] = [
                            'id' => $association['id'],
                            'sequenceNumber' => $association['sequenceNumber'],
                            'group' => $association['group']['id'],
                        ];
                    } else {
                        $results[$key]['assoc']['doc'] = [
                            'id' => $association['id'],
                            'sequenceNumber' => $association['sequenceNumber'],
                            'group' => '',
                        ];
                    }
                }
            }
        }

        foreach ($results as $key => $result) {
            if (!empty($results[$key]['children'])) {
                $this->rankItems($results[$key]['children']);
            }
        }

        return $results;
    }

    /**
     * Rank the items in $itemArray
     *   - by "rank"
     *   - then by "listEnumInSource"
     *   - then by "humanCodingScheme".
     */
    private function rankItems(array &$itemArray): void
    {
        Compare::sortArrayByFields($itemArray, ['sequenceNumber', 'listEnumInSource', 'humanCodingScheme']);
    }

    /**
     * Get a list of ids for all items that have parents for an LsDoc.
     *
     * @return array array of LsItem ids
     */
    public function findAllItemsWithParentsArray(LsDoc $lsDoc): array
    {
        $query = $this->getEntityManager()->createQuery('
            SELECT i.id
            FROM App\Entity\Framework\LsItem i INDEX by i.id
            JOIN i.associations a WITH a.lsDoc = :lsDocId AND a.type = :childOfType
            LEFT JOIN a.destinationLsItem p WITH p.lsDoc = :lsDocId
            LEFT JOIN a.destinationLsDoc pd WITH pd.id = :lsDocId
            WHERE i.lsDoc = :lsDocId
              AND (p.lsDoc IS NOT NULL OR pd.id IS NOT NULL)
        ');
        $query->setParameter('lsDocId', $lsDoc->getId());
        $query->setParameter('childOfType', LsAssociation::CHILD_OF);

        return $query->getResult(Query::HYDRATE_ARRAY);
    }

    /**
     * Get a list of all items for an LsDoc.
     *
     * @return array array of LsItems hydrated as an array
     */
    public function findTopChildrenIds(LsDoc $lsDoc): array
    {
        $query = $this->getEntityManager()->createQuery('
            SELECT i, a, add
            FROM App\Entity\Framework\LsItem i INDEX BY i.id
            JOIN i.associations a WITH a.lsDoc = :lsDocId AND a.type = :childOfType
            JOIN a.destinationLsDoc add WITH add.id = :lsDocId
            WHERE i.lsDoc = :lsDocId
        ');
        $query->setParameter('lsDocId', $lsDoc->getId());
        $query->setParameter('childOfType', LsAssociation::CHILD_OF);

        $results = $query->getResult(Query::HYDRATE_ARRAY);

        $this->rankItems($results);

        return array_keys($results);
    }

    /**
     * Delete an LsDoc and all associated items and associations.
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function deleteDocument(LsDoc $lsDoc, ?\Closure $progressCallback = null): void
    {
        $conn = $this->getEntityManager()->getConnection();

        $params = ['lsDocId' => $lsDoc->getId()];

        if (null === $progressCallback) {
            $progressCallback = static function (string $message = ''): void {
            };
        }

        $stmt = <<<'xENDx'
DELETE FROM salt_object_lock
 WHERE doc_id = :lsDocId
;
xENDx;
        $conn->prepare($stmt)->executeStatement($params);

        $progressCallback('Deleting associations');
        $stmt = <<<'xENDx'
DELETE FROM ls_association
 WHERE ls_doc_id = :lsDocId
    OR origin_lsitem_id IN (
      SELECT i.id
        FROM ls_item i
       WHERE i.ls_doc_id = :lsDocId
    )
    OR destination_lsitem_id IN (
      SELECT i.id
        FROM ls_item i
       WHERE i.ls_doc_id = :lsDocId
    )
;
xENDx;
        $conn->prepare($stmt)->executeStatement($params);

        $progressCallback('Deleting association groups');
        $stmt = <<<'xENDx'
DELETE FROM ls_def_association_grouping
 WHERE ls_doc_id = :lsDocId
;
xENDx;
        $conn->prepare($stmt)->executeStatement($params);

        $progressCallback('Deleting rubric references to items');
        $stmt = <<<'xENDx'
UPDATE rubric_criterion
   SET ls_item_id = NULL
 WHERE ls_item_id IN (
   SELECT id
     FROM ls_item
    WHERE ls_doc_id = :lsDocId
 )
;
xENDx;
        $conn->prepare($stmt)->executeStatement($params);

        $progressCallback('Deleting items');
        $stmt = <<<'xENDx'
DELETE FROM ls_item
 WHERE ls_doc_id = :lsDocId
;
xENDx;
        $conn->prepare($stmt)->executeStatement($params);

        $progressCallback('Deleting document subjects');
        $stmt = <<<'xENDx'
DELETE FROM ls_doc_subject
 WHERE ls_doc_id = :lsDocId
;
xENDx;
        $conn->prepare($stmt)->executeStatement($params);

        $progressCallback('Deleting document import logs');
        $stmt = <<<'xENDx'
DELETE FROM import_logs
 WHERE ls_doc_id = :lsDocId
;
xENDx;
        $conn->prepare($stmt)->executeStatement($params);

        $progressCallback('Deleting acls');
        $stmt = <<<'xENDx'
DELETE FROM salt_user_doc_acl
 WHERE doc_id = :lsDocId
;
xENDx;
        $conn->prepare($stmt)->executeStatement($params);

        $progressCallback('Deleting document attributes');
        $stmt = <<<'xENDx'
DELETE FROM ls_doc_attribute
 WHERE ls_doc_id = :lsDocId
;
xENDx;
        $conn->prepare($stmt)->executeStatement($params);

        $progressCallback('Deleting document');
        $stmt = <<<'xENDx'
DELETE FROM ls_doc
 WHERE id = :lsDocId
;
xENDx;
        $conn->prepare($stmt)->executeStatement($params);

        $progressCallback('Done');
    }

    public function copyDocumentContentToDoc(LsDoc $fromDoc, LsDoc $toDoc, bool $exactMatchAssocs = false): void
    {
        foreach ($fromDoc->getTopLsItems() as $oldItem) {
            $newItem = $oldItem->copyToLsDoc($toDoc, null, $exactMatchAssocs);
            $toDoc->addTopLsItem($newItem);
        }
    }

    public function makeDerivative(LsDoc $oldLsDoc, ?LsDoc $newLsDoc = null): LsDoc
    {
        $em = $this->getEntityManager();
        if (null === $newLsDoc) {
            $newLsDoc = new LsDoc();
            $newLsDoc->setTitle($oldLsDoc->getTitle().' - Derived');
            $newLsDoc->setCreator($oldLsDoc->getCreator());
            $newLsDoc->setVersion($oldLsDoc->getVersion());
            $newLsDoc->setDescription($oldLsDoc->getDescription());
            $newLsDoc->setSubject($oldLsDoc->getSubject());
            $newLsDoc->setNote($oldLsDoc->getNote());
            $newLsDoc->setLanguage($oldLsDoc->getLanguage());
            $newLsDoc->setOrg($oldLsDoc->getOrg());
            $newLsDoc->setUser($oldLsDoc->getUser());
            $newLsDoc->setLicence($oldLsDoc->getLicence());
        }

        foreach ($oldLsDoc->getAssociationGroupings() as $assocGroup) {
            $assocGroup->duplicateToLsDoc($newLsDoc);
        }

        $em->persist($newLsDoc);

        return $newLsDoc;
    }

    public function copyDocumentToItem(LsDoc $fromDoc, LsDoc $toDoc, ?\Closure $progressCallback = null): void
    {
        $em = $this->getEntityManager();

        if (null === $progressCallback) {
            $progressCallback = static function ($message = '') {
            };
        }

        $progressCallback('Adding framework as an item in another framework');

        $item = $toDoc->createItem();
        $item->setFullStatement($fromDoc->getTitle());
        $item->setNotes($fromDoc->getNote());
        $toDoc->addTopLsItem($item);
        $em->persist($item);

        foreach ($fromDoc->getAssociations() as $oldAssoc) {
            $newAssoc = $toDoc->createAssociation();
            $newAssoc->setOriginLsItem($item);
            $newAssoc->setType($oldAssoc->getType());
            $newAssoc->setDestination($oldAssoc->getDestination(), $oldAssoc->getDestinationNodeIdentifier());
            $item->addAssociation($newAssoc);
            $em->persist($newAssoc);
        }

        foreach ($fromDoc->getTopLsItems() as $oldItem) {
            $newItem = $oldItem->duplicateToLsDoc($toDoc);
            $item->addChild($newItem);
        }

        $progressCallback('Done');
    }

    /**
     * Get an array representing the entire CF package.
     */
    public function getPackageArray(LsDoc $doc): array
    {
        $pkg = [
            'CFDocument' => $doc,
            'CFItems' => array_values($this->findAllItems($doc, Query::HYDRATE_OBJECT)),
            'CFAssociations' => array_values($this->findAllAssociations($doc, Query::HYDRATE_OBJECT)),
            'CFDefinitions' => [
                'CFConcepts' => $this->findAllUsedConcepts($doc, Query::HYDRATE_OBJECT),
                'CFSubjects' => $doc->getSubjects(),
                'CFLicenses' => array_values($this->findAllUsedLicences($doc, Query::HYDRATE_OBJECT)),
                'CFItemTypes' => $this->findAllUsedItemTypes($doc, Query::HYDRATE_OBJECT),
                'CFAssociationGroupings' => $this->findAllUsedAssociationGroups($doc, Query::HYDRATE_OBJECT),
            ],
        ];

        $rubrics = $this->findAllUsedRubrics($doc, Query::HYDRATE_OBJECT);
        if (0 < count($rubrics)) {
            $pkg['CFRubrics'] = $rubrics;
        }

        return $pkg;
    }

    /**
     * Get a list of all items for an LsDoc.
     *
     * @psalm-param AbstractQuery::HYDRATE_* $format
     *
     * @return array array of LsItems hydrated as an array
     */
    public function findAllItems(LsDoc $lsDoc, int $format = AbstractQuery::HYDRATE_ARRAY, int $start = 0, int $limit = 0): array
    {
        $query = $this->getEntityManager()->createQuery('
            SELECT i, t, a, adi, add, c
            FROM App\Entity\Framework\LsItem i INDEX BY i.id
            LEFT JOIN i.itemType t
            LEFT JOIN i.concepts c
            LEFT JOIN i.associations a WITH a.lsDoc = :lsDocId
            LEFT JOIN a.destinationLsItem adi WITH adi.lsDoc = :lsDocId
            LEFT JOIN a.destinationLsDoc add WITH add.id = :lsDocId
            WHERE i.lsDoc = :lsDocId
              AND i.id > :start
            ORDER BY i.id
        ');
        if ($limit > 0) {
            $query->setMaxResults($limit);
        }
        $query->setParameter('lsDocId', $lsDoc->getId());
        $query->setParameter('start', $start);

        return $query->getResult($format);
    }

    /**
     * Get a list of all items for an LsDoc.
     *
     * @psalm-param AbstractQuery::HYDRATE_* $format
     *
     * @return array array of LsItems hydrated as an array
     */
    public function findAllItemsForCFPackage(LsDoc $lsDoc, int $format = AbstractQuery::HYDRATE_ARRAY, int $start = 0, int $limit = 0): array
    {
        $query = $this->getEntityManager()->createQuery('
            SELECT i, t, c
            FROM App\Entity\Framework\LsItem i INDEX BY i.id
            LEFT JOIN i.itemType t
            LEFT JOIN i.concepts c
            WHERE i.lsDoc = :lsDocId
              AND i.id > :start
            ORDER BY i.id
        ');
        if ($limit > 0) {
            $query->setMaxResults($limit);
        }
        $query->setParameter('lsDocId', $lsDoc->getId());
        $query->setParameter('start', $start);

        return $query->getResult($format);
    }

    /**
     * Get a list of all item types used in a document.
     *
     * @psalm-param AbstractQuery::HYDRATE_* $format
     *
     * @return array array of LsDefItemTypes
     */
    public function findAllUsedItemTypes(LsDoc $lsDoc, int $format = AbstractQuery::HYDRATE_ARRAY): array
    {
        $query = $this->getEntityManager()->createQuery('
            SELECT DISTINCT t
            FROM App\Entity\Framework\LsDefItemType t, App\Entity\Framework\LsItem i
            WHERE i.lsDoc = :lsDocId
              AND i.itemType = t
        ');
        $query->setParameter('lsDocId', $lsDoc->getId());

        return $query->getResult($format);
    }

    /**
     * Get a list of all associations for an LsDoc.
     *
     * @psalm-param AbstractQuery::HYDRATE_* $format
     *
     * @return array array of LsAssociations hydrated as an array
     */
    public function findAllAssociations(LsDoc $lsDoc, int $format = AbstractQuery::HYDRATE_ARRAY): array
    {
        $query = $this->getEntityManager()->createQuery('
            SELECT a, ag, adi, aoi, add
            FROM App\Entity\Framework\LsAssociation a INDEX BY a.id
            LEFT JOIN a.group ag
            LEFT JOIN a.destinationLsItem adi WITH adi.lsDoc = :lsDocId
            LEFT JOIN a.originLsItem aoi WITH aoi.lsDoc = :lsDocId
            LEFT JOIN a.destinationLsDoc add WITH add.id = :lsDocId
            WHERE a.lsDoc = :lsDocId
        ');
        $query->setParameter('lsDocId', $lsDoc->getId());

        return $query->getResult($format);
    }

    /**
     * Get a list of all associations for an LsDoc.
     *
     * @psalm-param AbstractQuery::HYDRATE_* $format
     *
     * @return iterable<array>|iterable<LsAssociation>
     */
    public function findAllAssociationsIterator(LsDoc $lsDoc, int $format = AbstractQuery::HYDRATE_ARRAY, int $start = 0, int $limit = 0): iterable
    {
        $query = $this->getEntityManager()->createQuery('
            SELECT a, ag, adi, aoi, add
            FROM App\Entity\Framework\LsAssociation a INDEX BY a.id
            LEFT JOIN a.group ag
            LEFT JOIN a.destinationLsItem adi WITH adi.lsDoc = :lsDocId
            LEFT JOIN a.originLsItem aoi WITH aoi.lsDoc = :lsDocId
            LEFT JOIN a.destinationLsDoc add WITH add.id = :lsDocId
            WHERE a.lsDoc = :lsDocId
              AND a.id > :start
            ORDER BY a.id
        ');
        if ($limit > 0) {
            $query->setMaxResults($limit);
        }
        $query->setParameter('start', $start);
        $query->setParameter('lsDocId', $lsDoc->getId());

        return $query->toIterable([], $format);
    }

    /**
     * Get a list of all association groups used in an LsDoc.
     *
     * @psalm-param AbstractQuery::HYDRATE_* $format
     *
     * @return array array of LsAssociations hydrated as an array
     */
    public function findAllUsedAssociationGroups(LsDoc $lsDoc, int $format = AbstractQuery::HYDRATE_ARRAY): array
    {
        $query = $this->getEntityManager()->createQuery('
            SELECT DISTINCT ag
            FROM App\Entity\Framework\LsDefAssociationGrouping ag, App\Entity\Framework\LsAssociation a
            WHERE a.lsDoc = :lsDocId
              AND a.group = ag
        ');
        $query->setParameter('lsDocId', $lsDoc->getId());

        return $query->getResult($format);
    }

    /**
     * Get a list of all concepts used in a document.
     *
     * @psalm-param AbstractQuery::HYDRATE_* $format
     *
     * @return array array of LsDefItemTypes
     */
    public function findAllUsedConcepts(LsDoc $lsDoc, int $format = AbstractQuery::HYDRATE_ARRAY): array
    {
        $query = $this->getEntityManager()->createQuery('
            SELECT DISTINCT c
            FROM App\Entity\Framework\LsDefConcept c, App\Entity\Framework\LsItem i
            WHERE i.lsDoc = :lsDocId
              AND c MEMBER OF i.concepts
        ');
        $query->setParameter('lsDocId', $lsDoc->getId());

        return $query->getResult($format);
    }

    /**
     * Get a list of all licences used in a document.
     *
     * @psalm-param AbstractQuery::HYDRATE_* $format
     *
     * @return array array of LsDefItemTypes
     */
    public function findAllUsedLicences(LsDoc $lsDoc, int $format = AbstractQuery::HYDRATE_ARRAY): array
    {
        // get licences for items
        $query = $this->getEntityManager()->createQuery('
            SELECT DISTINCT l
            FROM App\Entity\Framework\LsDefLicence l INDEX BY l.id, App\Entity\Framework\LsItem i
            WHERE (i.lsDoc = :lsDocId AND i.licence = l)
        ');
        $query->setParameter('lsDocId', $lsDoc->getId());

        $results = $query->getResult($format);

        // get licence for the doc
        $query = $this->getEntityManager()->createQuery('
            SELECT DISTINCT l
            FROM App\Entity\Framework\LsDefLicence l INDEX BY l.id, App\Entity\Framework\LsDoc d
            WHERE (d.id = :lsDocId AND d.licence = l)
        ');
        $query->setParameter('lsDocId', $lsDoc->getId());

        $docResults = $query->getResult($format);

        // merge the results so a licence only appears once
        foreach ($docResults as $result) {
            $results[$result->getId()] = $result;
        }

        return $results;
    }

    /**
     * Get a list of all licences used in a document.
     *
     * @psalm-param AbstractQuery::HYDRATE_* $format
     *
     * @return array array of LsDefItemTypes
     */
    public function findAllUsedRubrics(LsDoc $lsDoc, int $format = AbstractQuery::HYDRATE_ARRAY): array
    {
        $query = $this->getEntityManager()->createQuery('
            SELECT DISTINCT r
            FROM App\Entity\Framework\CfRubric r
            JOIN r.criteria c
            JOIN c.item i
            WHERE i.lsDoc = :lsDocId
        ');
        $query->setParameter('lsDocId', $lsDoc->getId());

        return $query->getResult($format);
    }

    /**
     * Get a list of all association groups used in an LsDoc.
     *
     * @psalm-param AbstractQuery::HYDRATE_* $format
     *
     * @return array array of LsAssociations hydrated as an array
     */
    public function findAllDocAssociationGroups(LsDoc $lsDoc, int $format = AbstractQuery::HYDRATE_OBJECT): array
    {
        $query = $this->getEntityManager()->createQuery('
            SELECT ag
            FROM App\Entity\Framework\LsDefAssociationGrouping ag
            WHERE ag.lsDoc = :lsDocId
        ');
        $query->setParameter('lsDocId', $lsDoc->getId());

        return $query->getResult($format);
    }

    /**
     * Get a list of all associations for an LsDoc where the nodes are known items.
     *
     * @return array array of LsAssociations hydrated as an array
     */
    public function findAllAssociationsForCapturedNodes(LsDoc $lsDoc): array
    {
        $query = $this->getEntityManager()->createQuery('
            SELECT a, ag, adi, add, odi, odd
            FROM App\Entity\Framework\LSAssociation a INDEX BY a.id
            LEFT JOIN a.group ag
            LEFT JOIN a.originLsItem odi WITH odi.lsDoc = :lsDocId
            LEFT JOIN a.originLsDoc odd WITH odd.id = :lsDocId
            LEFT JOIN a.destinationLsItem adi WITH adi.lsDoc = :lsDocId
            LEFT JOIN a.destinationLsDoc add WITH add.id = :lsDocId
            WHERE a.lsDoc = :lsDocId
              AND (odi.id IS NOT NULL OR odd.id IS NOT NULL)
              AND (adi.id IS NOT NULL OR add.id IS NOT NULL)
        ');
        $query->setParameter('lsDocId', $lsDoc->getId());

        return $query->getResult(Query::HYDRATE_ARRAY);
    }

    public function findAssociatedDocs(LsDoc $lsDoc): array
    {
        $docs = [];

        // Where the framework has a destination item in the document
        $qb = $this->createQueryBuilder('d')
            ->select('d.id, d.identifier, d.uri, d.title')
            ->distinct()
            ->join('d.lsItems', 'i')
            ->join('i.associations', 'a')
            ->join('a.destinationLsItem', 'i2')
            ->where('i2.lsDoc = :doc')
            ->setParameter('doc', $lsDoc->getId())
        ;
        $results = $qb->getQuery()->getResult(Query::HYDRATE_ARRAY);
        foreach ($results as $doc) {
            $docs[$doc['identifier']] = [
                'autoLoad' => 'true',
                'url' => $doc['uri'],
                'title' => $doc['title'],
            ];
        }

        // Where the framework has an origin item in the document
        $qb = $this->createQueryBuilder('d')
            ->select('d.id, d.identifier, d.uri, d.title')
            ->distinct()
            ->join('d.lsItems', 'i')
            ->join('i.associations', 'a')
            ->join('a.originLsItem', 'i2')
            ->where('i2.lsDoc = :doc')
            ->setParameter('doc', $lsDoc->getId())
        ;
        $results = $qb->getQuery()->getResult(Query::HYDRATE_ARRAY);
        foreach ($results as $doc) {
            $docs[$doc['identifier']] = [
                'autoLoad' => 'true',
                'url' => $doc['uri'],
                'title' => $doc['title'],
            ];
        }

        // Where the framework has a destination document as the document
        $qb = $this->createQueryBuilder('d')
            ->select('d.id, d.identifier, d.uri, d.title')
            ->distinct()
            ->join('d.lsItems', 'i')
            ->join('i.associations', 'a')
            ->join('a.destinationLsDoc', 'd2')
            ->where('d2.id = :doc')
            ->setParameter('doc', $lsDoc->getId())
        ;
        $results = $qb->getQuery()->getResult(Query::HYDRATE_ARRAY);
        foreach ($results as $doc) {
            $docs[$doc['identifier']] = [
                'autoLoad' => 'true',
                'url' => $doc['uri'],
                'title' => $doc['title'],
            ];
        }

        // Where the framework has an origin document as the document
        $qb = $this->createQueryBuilder('d')
            ->select('d.id, d.identifier, d.uri, d.title')
            ->distinct()
            ->join('d.lsItems', 'i')
            ->join('i.associations', 'a')
            ->join('a.originLsDoc', 'd2')
            ->where('d2.id = :doc')
            ->setParameter('doc', $lsDoc->getId())
        ;
        $results = $qb->getQuery()->getResult(Query::HYDRATE_ARRAY);
        foreach ($results as $doc) {
            $docs[$doc['identifier']] = [
                'autoLoad' => 'true',
                'url' => $doc['uri'],
                'title' => $doc['title'],
            ];
        }

        // Where there is an association belonging to the framework to an item in the document
        $qb = $this->createQueryBuilder('d')
            ->select('d.id, d.identifier, d.uri, d.title')
            ->distinct()
            ->join('d.docAssociations', 'a')
            ->join('a.destinationLsItem', 'i2')
            ->where('i2.lsDoc = :doc')
            ->setParameter('doc', $lsDoc->getId())
        ;
        $results = $qb->getQuery()->getResult(Query::HYDRATE_ARRAY);
        foreach ($results as $doc) {
            $docs[$doc['identifier']] = [
                'autoLoad' => 'true',
                'url' => $doc['uri'],
                'title' => $doc['title'],
            ];
        }

        // Where there is an association belonging to the framework to an item in the document
        $qb = $this->createQueryBuilder('d')
            ->select('d.id, d.identifier, d.uri, d.title')
            ->distinct()
            ->join('d.docAssociations', 'a')
            ->join('a.originLsItem', 'i2')
            ->where('i2.lsDoc = :doc')
            ->setParameter('doc', $lsDoc->getId())
        ;
        $results = $qb->getQuery()->getResult(Query::HYDRATE_ARRAY);
        foreach ($results as $doc) {
            $docs[$doc['identifier']] = [
                'autoLoad' => 'true',
                'url' => $doc['uri'],
                'title' => $doc['title'],
            ];
        }

        return $docs;
    }

    /**
     * Get a list of all items for an LsDoc.
     *
     * @psalm-param AbstractQuery::HYDRATE_* $format
     *
     * @return array array of LsItems hydrated as an array
     */
    public function findItemsForExportDoc(LsDoc $lsDoc, int $format = AbstractQuery::HYDRATE_ARRAY): array
    {
        $query = $this->getEntityManager()->createQuery('
            SELECT i, t,
              CASE WHEN a.sequenceNumber IS NULL THEN 1 ELSE 0 END as HIDDEN seq_is_null,
              a.sequenceNumber as HIDDEN seq
            FROM App\Entity\Framework\LsItem i INDEX BY i.id
            LEFT JOIN i.itemType t
            LEFT JOIN i.associations a WITH a.lsDoc = :lsDocId AND a.type = :childOfType
            WHERE i.lsDoc = :lsDocId
            ORDER BY seq_is_null ASC, seq ASC, i.listEnumInSource ASC, i.humanCodingScheme
        ');
        $query->setParameter('lsDocId', $lsDoc->getId());
        $query->setParameter('childOfType', LsAssociation::CHILD_OF);

        return $query->getResult($format);
    }

    /**
     * Get a list of all items for an LsDoc.
     *
     * @return array array of LsItems hydrated as an array
     */
    public function findAssociationsForExportDoc(LsDoc $lsDoc): array
    {
        $query = $this->getEntityManager()->createQuery(sprintf('
            SELECT a, g, oi.id AS oi_id, oi.identifier AS oi_identifier, oi.lsDocIdentifier AS oi_lsDocIdentifier, di.id AS di_id, di.identifier AS di_identifier, di.lsDocIdentifier as di_lsDocIdentifier
            FROM %s a INDEX BY a.id
            LEFT JOIN a.group g
            LEFT JOIN a.originLsItem oi
            LEFT JOIN a.destinationLsItem di
            WHERE a.lsDoc = :lsDocId
            ORDER BY a.sequenceNumber ASC
        ', LsAssociation::class));
        $query->setParameter('lsDocId', $lsDoc->getId());

        return array_map(
            function ($rec) {
                $ret = $rec[0];
                $ret['originLsItem'] = [
                    'id' => $rec['oi_id'],
                    'identifier' => $rec['oi_identifier'],
                    'lsDocIdentifier' => $rec['oi_lsDocIdentifier'],
                ];
                $ret['destinationLsItem'] = [
                    'id' => $rec['di_id'],
                    'identifier' => $rec['di_identifier'],
                    'lsDocIdentifier' => $rec['di_lsDocIdentifier'],
                ];

                return $ret;
            },
            $query->getResult(AbstractQuery::HYDRATE_ARRAY)
        );
    }
}
