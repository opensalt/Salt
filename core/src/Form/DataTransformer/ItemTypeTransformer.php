<?php

namespace App\Form\DataTransformer;

use App\Entity\Framework\LsDefItemType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * Data transformer which can create new ItemTypes.
 *
 * Class ItemTypeTransformer
 */
class ItemTypeTransformer implements DataTransformerInterface
{
    private PropertyAccessor $accessor;

    public function __construct(
        private EntityManagerInterface $em,
        private string $className,
        private ?string $textProperty = null,
        private string $primaryKey = 'id'
    ) {
        $this->accessor = PropertyAccess::createPropertyAccessor();

        if (LsDefItemType::class !== $this->className) {
            throw new \InvalidArgumentException("Class {$className} not supported in ItemTypeTransformer");
        }
    }

    /**
     * Transform entity to array.
     *
     * @param mixed $entity
     */
    public function transform($entity): array
    {
        $data = [];
        if (empty($entity)) {
            return $data;
        }

        $text = (null === $this->textProperty)
            ? (string) $entity
            : $this->accessor->getValue($entity, $this->textProperty);

        $data[$this->accessor->getValue($entity, $this->primaryKey)] = $text;

        return $data;
    }

    /**
     * Transform single id value to an entity.
     *
     * @param string $value
     *
     * @return mixed|object|null
     */
    public function reverseTransform($value)
    {
        if (empty($value)) {
            return null;
        }

        // Add a potential new tag entry
        $valuePrefix = substr($value, 0, 2);
        if ('__' === $valuePrefix) {
            // In that case, we have a new entry
            $cleanValue = substr($value, 2);
            $entity = new LsDefItemType();
            $entity->setCode($cleanValue);
            $entity->setTitle($cleanValue);
            $entity->setHierarchyCode($cleanValue);
            $this->em->persist($entity);

            return $entity;
        }

        // We do not search for a new entry, as it does not exist yet, by definition
        try {
            $entity = $this->em->createQueryBuilder()
                ->select('entity')
                ->from($this->className, 'entity')
                ->where('entity.'.$this->primaryKey.' = :id')
                ->setParameter('id', $value)
                ->getQuery()
                ->getSingleResult();
        } catch (\Exception) {
            // this will happen if the form submits invalid data
            throw new TransformationFailedException(sprintf('The choice "%s" does not exist or is not unique', $value));
        }

        return $entity;
    }
}
