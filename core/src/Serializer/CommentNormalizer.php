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

    public function normalize(mixed $data, ?string $format = null, array $context = []): ?array
    {
        if (!$data instanceof Comment) {
            throw new \InvalidArgumentException('Expecting a Comment');
        }

        return [
            'fullname' => $data->getFullname(),
            'id' => $data->getId(),
            'parent' => $data->getParentId(),
            'content' => $data->getContent(),
            'document' => $data->getDocument()?->getId(),
            'item' => $data->getItem()?->getId(),
            'upvote_count' => $data->getUpvoteCount(),
            'created' => $data->getCreatedAt()->format('Y-m-d\TH:i:s+00:00'),
            'modified' => $data->getUpdatedAt()->format('Y-m-d\TH:i:s+00:00'),
            'file_mime_type' => $data->getFileMimeType(),
            'file_url' => $data->getFileUrl(),
            'created_by_current_user' => $data->isCreatedByCurrentUser(),
            'user_has_upvoted' => $data->hasUserUpvoted(),
        ];
    }
}
