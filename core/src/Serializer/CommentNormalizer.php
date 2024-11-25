<?php

namespace App\Serializer;

use App\Entity\Comment\Comment;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class CommentNormalizer implements NormalizerInterface
{
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Comment;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Comment::class => true];
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): ?array
    {
        if (!$object instanceof Comment) {
            throw new \InvalidArgumentException('Expecting a Comment');
        }

        return [
            'fullname' => $object->getFullname(),
            'id' => $object->getId(),
            'parent' => $object->getParentId(),
            'content' => $object->getContent(),
            'document' => $object->getDocument()?->getId(),
            'item' => $object->getItem()?->getId(),
            'upvote_count' => $object->getUpvoteCount(),
            'created' => $object->getCreatedAt()->format('Y-m-d\TH:i:s+00:00'),
            'modified' => $object->getUpdatedAt()->format('Y-m-d\TH:i:s+00:00'),
            'file_mime_type' => $object->getFileMimeType(),
            'file_url' => $object->getFileUrl(),
            'created_by_current_user' => $object->isCreatedByCurrentUser(),
            'user_has_upvoted' => $object->hasUserUpvoted(),
        ];
    }
}
