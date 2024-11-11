<?php

namespace App\Service;

use App\Entity\Framework\IdentifiableInterface;
use App\Entity\Framework\LsAssociation;
use App\Entity\Framework\LsDefAssociationGrouping;
use App\Entity\Framework\LsDefConcept;
use App\Entity\Framework\LsDefGrade;
use App\Entity\Framework\LsDefItemType;
use App\Entity\Framework\LsDefLicence;
use App\Entity\Framework\LsDefSubject;
use App\Entity\Framework\LsDoc;
use App\Entity\Framework\LsItem;
use App\Entity\Framework\ObjectLock;
use App\Entity\LockableInterface;
use App\Entity\User\User;
use App\Exception\AlreadyLockedException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class FrameworkService
{
    private ObjectManager $em;

    public function __construct(
        ManagerRegistry $registry,
        private TokenStorageInterface $tokenStorage,
    ) {
        $this->em = $registry->getManager();
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function addDocument(LsDoc $doc): LsDoc
    {
        $this->em->persist($doc);

        // Determine the owner
        $user = $doc->getUser() ?? $this->getCurrentUser();
        if (null === $user) {
            $doc->setOwnedBy(null);

            return $doc;
        }

        // If the owner has already been set then return (such as for console commands)
        if (null !== $doc->getOrg()) {
            $doc->setUser(null);
            $doc->setOwnedBy('organization');

            return $doc;
        }

        // Set the user or org (default) based on what was stipulated
        if ('user' === $doc->getOwnedBy()) {
            $doc->setUser($user);
            $doc->setOrg(null);
        } else {
            $doc->setUser(null);
            $doc->setOrg($user->getOrg());
            $doc->setOwnedBy('organization');
        }

        return $doc;
    }

    public function deleteFramework(LsDoc $doc, ?\Closure $callback = null): void
    {
        $this->em
            ->getRepository(LsDoc::class)
            ->deleteDocument($doc, $callback);
    }

    public function persistItem(LsItem $item): void
    {
        $this->em->persist($item);
    }

    public function deleteItem(LsItem $item): void
    {
        $this->em->getRepository(LsItem::class)->removeItem($item);
    }

    public function deleteItemWithChildren(LsItem $item): void
    {
        $this->em->getRepository(LsItem::class)->removeItemAndChildren($item);
    }

    public function persistAssociation(LsAssociation $association): void
    {
        $this->em->persist($association);
    }

    public function addExemplarToItem(LsItem $item, string $url, ?string $annotation = null): LsAssociation
    {
        $lsAssociation = new LsAssociation();
        $lsAssociation->setLsDoc($item->getLsDoc());
        $lsAssociation->setOriginLsItem($item);
        $lsAssociation->setType(LsAssociation::EXEMPLAR);
        $lsAssociation->setAnnotation($annotation);
        $lsAssociation->setDestinationNodeUri($url);
        $lsAssociation->setDestinationNodeIdentifier(Uuid::uuid5(Uuid::NAMESPACE_URL, $url));

        // TODO: setDestinationTitle is not currently a table field.
        //$lsAssociation->setDestinationTitle($request->request->get("exemplarDescription"));

        $this->em->persist($lsAssociation);

        return $lsAssociation;
    }

    public function addTreeAssociation(LsDoc $doc, array $origin, string $type, array $dest, ?string $assocGroup = null, ?string $annotation = null): LsAssociation
    {
        $association = new LsAssociation();
        $association->setLsDoc($doc);
        $types = explode('|', $type, 2);
        $association->setType($types[0]);
        $association->setSubtype($types[1] ?? null);
        $association->setAnnotation($annotation);

        // deal with origin and dest items, which can be specified by id or by identifier
        // if externalDoc is specified for either one, mark this document as "autoLoad": "true" in the doc's externalDocuments
        $itemRepo = $this->em->getRepository(LsItem::class);

        if (!empty($origin['id'])) {
            $originItem = $itemRepo->findOneBy(['id' => $origin['id']]);
            if (null === $originItem) {
                throw new \InvalidArgumentException('origin id is not a valid id');
            }
            $association->setOrigin($originItem);
        } else {
            if (!empty($origin['externalDoc'])) {
                $doc->setExternalDocAutoLoad($origin['externalDoc'], 'true');
                $this->em->persist($doc);
            }
            $originItem = $origin['identifier'];
            $association->setOrigin($origin['uri'], $originItem);
        }

        if (!empty($dest['id'])) {
            $destItem = $itemRepo->findOneBy(['id' => $dest['id']]);
            if (null === $destItem) {
                throw new \InvalidArgumentException('destination id is not a valid id');
            }
            $association->setDestination($destItem);
        } else {
            if (!empty($dest['externalDoc'])) {
                $doc->setExternalDocAutoLoad($dest['externalDoc'], 'true');
                $this->em->persist($doc);
            }
            $destItem = $dest['identifier'];
            $association->setDestination($dest['uri'], $destItem);
        }

        // set assocGroup if provided
        if (null !== $assocGroup) {
            $assocGroupRepo = $this->em->getRepository(LsDefAssociationGrouping::class);
            $assocGroupObj = $assocGroupRepo->findOneBy(['id' => $assocGroup]);
            $association->setGroup($assocGroupObj);
        }

        $this->em->persist($association);

        return $association;
    }

    public function deleteAssociation(LsAssociation $association): void
    {
        $this->em->remove($association);
    }

    public function updateTreeItems(LsDoc $doc, array $items): array
    {
        $rv = ['return' => [], 'changes' => []];

        foreach ($items as $lsItemId => $updates) {
            $this->updateTreeItem($doc, $lsItemId, $updates, $rv);
        }

        return $rv;
    }

    public function updateTreeItem(LsDoc $doc, string $itemId, array $updates, array &$rv): void
    {
        // Note that $lsItemId may be of the form "copy-<uuid>" when copying from another framework
        $assocGroupRepo = $this->em->getRepository(LsDefAssociationGrouping::class);

        // set assocGroup if supplied; pass this in when necessary below
        $assocGroup = null;
        if (array_key_exists('assocGroup', $updates)) {
            $assocGroup = $assocGroupRepo->find($updates['assocGroup']);
        }

        $lsItem = $this->getTreeItemForUpdate($doc, $updates, $itemId, $assocGroup);

        if (null === $lsItem) {
            return;
        }

        // return the id and fullStatement of the item, whether it's new or it already existed
        $rv['return'][$itemId] = [
            'originalKey' => $updates['originalKey'],
            'lsItem' => $lsItem,
            'lsItemIdentifier' => $lsItem->getIdentifier(),
            'fullStatement' => $lsItem->getFullStatement(),
        ];

        if (array_key_exists('deleteChildOf', $updates)) {
            $this->deleteTreeChildAssociations($lsItem, $updates, $itemId, $rv);
        } elseif (array_key_exists('updateChildOf', $updates)) {
            $this->updateTreeChildOfAssociations($lsItem, $updates, $itemId, $rv);
        }

        // create new childOf association if specified
        if (array_key_exists('newChildOf', $updates)) {
            $this->addTreeChildOfAssociations($lsItem, $updates, $itemId, $rv, $assocGroup);
        }

        $lsItem->setUpdatedAt(new \DateTime());
    }

    public function persistAssociationGroup(LsDefAssociationGrouping $associationGroup): void
    {
        $this->em->persist($associationGroup);
    }

    public function deleteAssociationGroup(LsDefAssociationGrouping $associationGroup): void
    {
        $this->em->remove($associationGroup);
    }

    public function persistConcept(LsDefConcept $concept): void
    {
        $this->em->persist($concept);
    }

    public function deleteConcept(LsDefConcept $concept): void
    {
        $this->em->remove($concept);
    }

    public function persistGrade(LsDefGrade $grade): void
    {
        $this->em->persist($grade);
    }

    public function deleteGrade(LsDefGrade $grade): void
    {
        $this->em->remove($grade);
    }

    public function persistItemType(LsDefItemType $itemType): void
    {
        $this->em->persist($itemType);
    }

    public function deleteItemType(LsDefItemType $itemType): void
    {
        $this->em->remove($itemType);
    }

    public function persistLicence(LsDefLicence $licence): void
    {
        $this->em->persist($licence);
    }

    public function deleteLicence(LsDefLicence $licence): void
    {
        $this->em->remove($licence);
    }

    public function persistSubject(LsDefSubject $subject): void
    {
        $this->em->persist($subject);
    }

    public function deleteSubject(LsDefSubject $subject): void
    {
        $this->em->remove($subject);
    }

    /**
     * @throws AlreadyLockedException
     */
    public function lockObject(LockableInterface $doc, User $user): ObjectLock
    {
        $lockRepo = $this->em->getRepository(ObjectLock::class);

        return $lockRepo->acquireLock($doc, $user);
    }

    public function unlockObject(LockableInterface $doc, ?User $user = null): void
    {
        $lockRepo = $this->em->getRepository(ObjectLock::class);

        $lockRepo->releaseLock($doc, $user);
    }

    public function getNextChildSequenceNumber(IdentifiableInterface $parent): int
    {
        static $lastSeqNums = [];

        $identifier = $parent->getIdentifier();

        if (array_key_exists($identifier, $lastSeqNums)) {
            return ++$lastSeqNums[$identifier];
        }

        $assocRepo = $this->em->getRepository(LsAssociation::class);

        /** @var LsAssociation[] $assocs */
        $assocs = $assocRepo->findAllChildAssociationsFor($identifier);

        $lastSeqNum = 0;
        foreach ($assocs as $assoc) {
            if (($assoc->getSequenceNumber() ?? 0) > $lastSeqNum) {
                $lastSeqNum = $assoc->getSequenceNumber();
            }
        }

        $lastSeqNums[$identifier] = ++$lastSeqNum;

        return $lastSeqNums[$identifier];
    }

    /**
     * Get the item to update, either the original or a copy based on the update array.
     */
    protected function getTreeItemForUpdate(LsDoc $lsDoc, array $updates, string $lsItemId, ?LsDefAssociationGrouping $assocGroup = null): ?LsItem
    {
        $lsItemRepo = $this->em->getRepository(LsItem::class);

        if (!array_key_exists('copyFromId', $updates)) {
            return $lsItemRepo->find($lsItemId);
        }

        // copy item if copyFromId is specified
        $originalItem = $lsItemRepo->find($updates['copyFromId']);

        if (null === $originalItem) {
            return null;
        }

        $lsItem = $originalItem->copyToLsDoc($lsDoc, $assocGroup);

        // if addCopyToTitle is set, add "Copy of " to fullStatement and abbreviatedStatement
        if (array_key_exists('addCopyToTitle', $updates)) {
            $title = 'Copy of '.$lsItem->getFullStatement();
            $lsItem->setFullStatement($title);

            $abbreviatedStatement = $lsItem->getAbbreviatedStatement();
            if (null !== $abbreviatedStatement) {
                $abbreviatedStatement = 'Copy of '.$abbreviatedStatement;
                $lsItem->setAbbreviatedStatement($abbreviatedStatement);
            }
        }

        $this->em->persist($lsItem);

        return $lsItem;
    }

    /**
     * Remove the appropriate childOf associations for the item based on the update array.
     */
    protected function deleteTreeChildAssociations(LsItem $lsItem, array $updates, string $lsItemId, array &$rv): void
    {
        $assocRepo = $this->em->getRepository(LsAssociation::class);

        // delete childOf association if specified
        if ('all' !== $updates['deleteChildOf']['assocId']) {
            $assoc = $assocRepo->find($updates['deleteChildOf']['assocId']);
            if (null === $assoc) {
                return;
            }

            $assocRepo->removeAssociation($assoc);
            $rv['return'][$lsItemId]['deleteChildOf'] = $updates['deleteChildOf']['assocId'];

            if (!array_key_exists('assoc-d', $rv['changes'])) {
                $rv['changes']['assoc-d'] = [];
            }
            $rv['changes']['assoc-d'][$assoc->getId()] = $assoc->getIdentifier();
        } else {
            // if we got "all" for the assocId, it means that we're updating a new item for which the client didn't know an assocId.
            // so in this case, it's OK to just delete any existing childof association and create a new one below
            $deleted = $assocRepo->removeAllAssociationsOfType($lsItem, LsAssociation::CHILD_OF);

            if (0 < \count($deleted) && !array_key_exists('assoc-d', $rv['changes'])) {
                $rv['changes']['assoc-d'] = [];
            }
            foreach ($deleted as $assoc) {
                /** @var LsAssociation $assoc */
                $rv['changes']['assoc-d'][$assoc->getId()] = $assoc->getIdentifier();
            }
        }
    }

    /**
     * Update the childOf associations based on the update array.
     */
    protected function updateTreeChildOfAssociations(LsItem $lsItem, array $updates, string $lsItemId, array &$rv): void
    {
        $assocRepo = $this->em->getRepository(LsAssociation::class);

        // update childOf association if specified
        $assoc = $assocRepo->find($updates['updateChildOf']['assocId']);
        if (null === $assoc) {
            return;
        }

        // as of now the only thing we update is sequenceNumber
        if (array_key_exists('sequenceNumber', $updates['updateChildOf'])) {
            if ($assoc->getSequenceNumber() !== (int) $updates['updateChildOf']['sequenceNumber']) {
                $assoc->setSequenceNumber((int) $updates['updateChildOf']['sequenceNumber']);

                $rv['return'][$lsItemId]['association'] = $assoc;
                $rv['return'][$lsItemId]['sequenceNumber'] = $updates['updateChildOf']['sequenceNumber'];

                if (!array_key_exists('assoc-u', $rv['changes'])) {
                    $rv['changes']['assoc-u'] = [];
                }
                $rv['changes']['assoc-u'][$assoc->getId()] = $assoc->getIdentifier();
            }
        }
    }

    /**
     * Add new childOf associations based on the update array.
     *
     * @throws \UnexpectedValueException
     */
    protected function addTreeChildOfAssociations(LsItem $lsItem, array $updates, string $lsItemId, array &$rv, ?LsDefAssociationGrouping $assocGroup = null): void
    {
        // parent could be a doc or item
        if ('item' === $updates['newChildOf']['parentType']) {
            $lsItemRepo = $this->em->getRepository(LsItem::class);
            $parentItem = $lsItemRepo->find($updates['newChildOf']['parentId']);
        } else {
            $docRepo = $this->em->getRepository(LsDoc::class);
            $parentItem = $docRepo->find($updates['newChildOf']['parentId']);
        }

        $rv['return'][$lsItemId]['association'] = $lsItem->addParent($parentItem, $updates['newChildOf']['sequenceNumber'], $assocGroup);
        $rv['return'][$lsItemId]['sequenceNumber'] = $updates['newChildOf']['sequenceNumber'];

        if (!array_key_exists('assoc-a', $rv['changes'])) {
            $rv['changes']['assoc-a'] = [];
        }
        $rv['changes']['assoc-a'][] = $rv['return'][$lsItemId]['association'];
    }

    /**
     * Get a user from the Security Token Storage.
     *
     * @see TokenInterface::getUser()
     */
    protected function getCurrentUser(): ?User
    {
        if (null === $token = $this->tokenStorage->getToken()) {
            return null;
        }

        $user = $token->getUser();

        if (!$user instanceof User) {
            // e.g. anonymous authentication
            return null;
        }

        return $user;
    }
}
