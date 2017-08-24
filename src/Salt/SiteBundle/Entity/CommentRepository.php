<?php

namespace Salt\SiteBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Salt\UserBundle\Entity\User;

/**
 * CommentRepository
 *
 * @method Comment[] findByItem(string $itemRef)
 */
class CommentRepository extends EntityRepository
{
    /**
     * @param string $itemType
     * @param int $itemId
     * @param User $user
     * @param string $content
     * @param int $parentId
     * @return Comment
     */
    public function addComment($itemType, $itemId, User $user, string $content, $parentId = null)
    {
        $comment = new Comment();
        $comment->setContent(trim($content));
        $comment->setUser($user);
        $comment->setFullname($user->getUsername().' - '.$user->getOrg()->getName());
        $comment->setItem($itemType.':'.$itemId);
        $comment->setCreatedByCurrentUser(true);

        $parent = $this->find($parentId);
        $comment->setParent($parent);

        $this->getEntityManager()->persist($comment);
        $this->getEntityManager()->flush($comment);

        return $comment;
    }
    /**
     * @param array $id
     * @return array|Comment[]
     */
    public function findByTypeItem(array $id): array
    {
        return $this->findByItem($id['itemType'].':'.$id['itemId']);
    }
}
