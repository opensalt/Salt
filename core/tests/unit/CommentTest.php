<?php

use Doctrine\ORM\EntityManager;
use App\Entity\Comment\Comment;
use App\Entity\Framework\LsItem;
use App\Entity\Comment\CommentUpvote;
use Ramsey\Uuid\Uuid;

class CommentTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    // tests
    public function testAddComment()
    {
        $this->tester->ensureUserExistsWithRole('Editor');
        $user = $this->tester->getLastUser();
        $em = $this->getModule('Doctrine')->em;
        $comment = new Comment();
        $itemId = $this->addLsItem();
        $item = $em->getRepository(LsItem::class)->find($itemId);

        $comment->setContent('unit test comment');
        $comment->setParent(null);
        $comment->setUser($user);
        $comment->setItem($item);

        $em->persist($comment);
        $em->flush();

        $this->tester->seeInRepository(Comment::class, ['content' => 'unit test comment']);
    }

    public function testUpdateComment()
    {
        $commentId = $this->createComment('new comment');

        $em = $this->getModule('Doctrine')->em;

        $comment = $em->find(Comment::class, $commentId);
        $comment->setContent('updated content');

        $em->persist($comment);
        $em->flush();

        $this->assertEquals('updated content', $comment->getContent());
        $this->tester->seeInRepository(Comment::class, ['content' => 'updated content']);
    }

    public function testDeleteComment()
    {
        $commentId = $this->createComment('deleted comment');

        $em = $this->getModule('Doctrine')->em;
        $commentsCount = count($this->tester->grabEntitiesFromRepository(Comment::class));
        $comment = $em->find(Comment::class, $commentId);

        $em->remove($comment);
        $em->flush();
        $newCommentsCount = count($this->tester->grabEntitiesFromRepository(Comment::class));

        $this->assertEquals($commentsCount - 1, $newCommentsCount);
    }

    public function testUpvoteComment()
    {
        /** @var EntityManager $em */
        $em = $this->getModule('Doctrine')->em;
        $commentId = $this->createComment('upvoted comment');
        $comment = $em->find(Comment::class, $commentId);
        $upvotes = $comment->getUpvoteCount();

        $user = $this->tester->getLastUser();

        $commentUpvote = new CommentUpvote();
        $commentUpvote->setComment($comment);
        $commentUpvote->setUser($user);
        $em->persist($commentUpvote);
        $em->flush();

        $em->detach($comment);
        $comment = $em->find(Comment::class, $commentId);
        $upvotesCount = $comment->getUpvoteCount();
        $this->assertEquals($upvotes + 1, $upvotesCount);
    }

    public function testDownvoteComment()
    {
        /** @var EntityManager $em */
        $em = $this->getModule('Doctrine')->em;
        $commentRepo = $em->getRepository(Comment::class);
        $commentId = $this->createComment('comment');
        $comment = $commentRepo->find($commentId);

        $upvotes = $comment->getUpvoteCount();
        $user = $this->tester->getLastUser();

        // Add an upvote
        $commentRepo->addUpvoteForUser($comment, $user);
        $em->flush();
        $em->detach($comment);
        $comment = $commentRepo->find($commentId);
        $upvotesCount = $comment->getUpvoteCount();
        $this->assertEquals($upvotes + 1, $upvotesCount);

        // Remove the upvote
        $commentRepo->removeUpvoteForUser($comment, $user);
        $em->flush();
        $em->detach($comment);
        $comment = $commentRepo->find($commentId);
        $upvotesCount = $comment->getUpvoteCount();
        $this->assertEquals($upvotes, $upvotesCount);
    }

    private function createComment($content)
    {
        $this->tester->ensureUserExistsWithRole('Editor');
        $em = $this->getModule('Doctrine')->em;
        $user = $this->tester->getLastUser();
        $itemId = $this->addLsItem();
        $item = $em->getRepository(LsItem::class)->find($itemId);

        $commentId = $this->tester->haveInRepository(Comment::class,
            [
                'item' => $item,
                'content' => $content,
                'parent' => null,
                'fullname' => 'codeception',
                'user' => $user
            ]
        );

        return $commentId;
    }

    public function testAddCommentMoreThan255Chars()
    {
        $this->tester->ensureUserExistsWithRole('Editor');
        $user = $this->tester->getLastUser();
        $em = $this->getModule('Doctrine')->em;
        $comment = new Comment();
        $itemId = $this->addLsItem();
        $item = $em->getRepository(LsItem::class)->find($itemId);
        $comment->setItem($item);
        $comment->setContent("Lorem Ipsum is simply dummy text of the printing and
            typesetting industry. Lorem Ipsum has been the industry's standard dummy
            text ever since the 1500s, when an unknown printer took a galley of type
            and scrambled it to make a type specimen book. It has survived not only
            five centuries, but also the leap into electronic typesetting,
            remaining essentially unchanged. It was popularised in the 1960s with the
            release of Letraset sheets containing Lorem Ipsum passages, and more recently
            with desktop publishing software like Aldus PageMaker including versions of
            Lorem Ipsum.");
        $comment->setParent(null);
        $comment->setUser($user);

        $em->persist($comment);
        $em->flush();

        $this->tester->seeInRepository(Comment::class, ['item' => $itemId]);
    }

    public function addLsItem()
    {
        $identifier = Uuid::uuid4()->toString();
        $docIdentifier = Uuid::uuid4()->toString();
        $lsItemId = $this->tester->haveInRepository(LsItem::class,
            [
                'identifier' => $identifier,
                'lsDocIdentifier' => $docIdentifier,
                'fullStatement' => 'codeception'
            ]
        );

        return $lsItemId;
    }
}
