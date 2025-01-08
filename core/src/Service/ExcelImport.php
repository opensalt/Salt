<?php

namespace App\Service;

use App\Entity\Framework\AdditionalField;
use App\Entity\Framework\ImportLog;
use App\Entity\Framework\LsAssociation;
use App\Entity\Framework\LsDefAssociationGrouping;
use App\Entity\Framework\LsDefItemType;
use App\Entity\Framework\LsDefLicence;
use App\Entity\Framework\LsDoc;
use App\Entity\Framework\LsItem;
use App\Util\EducationLevelSet;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Ramsey\Uuid\Uuid;

final class ExcelImport
{
    private static ?array $itemCustomFields = null;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        if (null === self::$itemCustomFields) {
            $customFieldsArray = $this->getEntityManager()->getRepository(AdditionalField::class)
                ->findBy(['appliesTo' => LsItem::class]);
            self::$itemCustomFields = array_map(static fn (AdditionalField $cf) => $cf->getName(), $customFieldsArray);
        }
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    public function importExcel(string $excelFilePath): LsDoc
    {
        set_time_limit(180); // increase time limit for large files

        $phpExcelObject = IOFactory::load($excelFilePath);

        /** @var LsItem[] $items */
        $items = [];
        $itemSmartLevels = [];
        $children = [];

        /** @var LsItem[] $smartLevels */
        $smartLevels = [];

        $sheet = $phpExcelObject->getSheetByName('CF Doc');
        if (null === $sheet) {
            throw new \RuntimeException('CF Doc sheet does not exist in the workbook');
        }
        $doc = $this->saveDoc($sheet);
        $children[$doc->getIdentifier()] = $doc->getIdentifier();

        $sheet = $phpExcelObject->getSheetByName('CF Item');
        if (null === $sheet) {
            throw new \RuntimeException('CF Item sheet does not exist in the workbook');
        }
        $lastRow = $sheet->getHighestRow();

        // Save items
        for ($i = 2; $i <= $lastRow; ++$i) {
            $item = $this->saveItem($sheet, $doc, $i);
            if (null === $item) {
                continue;
            }

            $items[$item->getIdentifier()] = $item;

            $smartLevel = (string) $this->getCellValueOrNull($sheet, 4, $i);
            if (!empty($smartLevel)) {
                $smartLevels[$smartLevel] = $item;
                $itemSmartLevels[$item->getIdentifier()] = $smartLevel;
            }
        }

        $associationsIdentifiers = [];
        foreach ($items as $item) {
            $smartLevel = $itemSmartLevels[$item->getIdentifier()];
            $levels = explode('.', $smartLevel);
            $seq = array_pop($levels);
            $parentLevel = implode('.', $levels);

            if (!is_numeric($seq)) {
                $seq = null;
            } else {
                $seq = (int) $seq;
            }

            $children[$item->getIdentifier()] = $doc->getIdentifier();

            if (in_array($parentLevel, $itemSmartLevels, true)) {
                $assoc = $this->getEntityManager()->getRepository(LsAssociation::class)->findOneBy([
                    'originNodeIdentifier' => $item->getIdentifier(),
                    'type' => LsAssociation::CHILD_OF,
                    'destinationNodeIdentifier' => $smartLevels[$parentLevel]->getIdentifier(),
                ]);

                if (null === $assoc) {
                    $assoc = $smartLevels[$parentLevel]->addChild($item, null, $seq);
                } else {
                    $assoc->setSequenceNumber($seq);
                }
            } else {
                $assoc = $this->getEntityManager()->getRepository(LsAssociation::class)->findOneBy([
                    'originNodeIdentifier' => $item->getIdentifier(),
                    'type' => LsAssociation::CHILD_OF,
                    'destinationNodeIdentifier' => $item->getLsDoc()->getIdentifier(),
                ]);

                if (null === $assoc) {
                    $assoc = $doc->createChildItem($item, null, $seq);
                } else {
                    $assoc->setSequenceNumber($seq);
                }
            }

            $associationsIdentifiers[$assoc->getIdentifier()] = null;
        }

        $items[$doc->getIdentifier()] = $doc;

        $sheet = $phpExcelObject->getSheetByName('CF Association');
        if (null === $sheet) {
            throw new \RuntimeException('CF Association sheet does not exist in the workbook');
        }
        $lastRow = $sheet->getHighestRow();

        for ($i = 2; $i <= $lastRow; ++$i) {
            $assoc = $this->saveAssociation($sheet, $doc, $i, $items, $children);
            if (null !== $assoc) {
                $associationsIdentifiers[$assoc->getIdentifier()] = null;
            }
        }

        $this->checkRemovedItems($doc, $items);
        $this->checkRemovedAssociations($doc, $associationsIdentifiers);

        return $doc;
    }

    private function saveDoc(Worksheet $sheet): LsDoc
    {
        $docRepo = $this->getEntityManager()->getRepository(LsDoc::class);
        $doc = $docRepo->findOneByIdentifier($this->getCellValueOrNull($sheet, 1, 2));

        if (null === $doc) {
            $doc = new LsDoc();
        }

        /* $doc->setIdentifier($this->getCellValueOrNull($sheet, 1, 2)); */
        $doc->setCreator($this->getCellValueOrNull($sheet, 2, 2));
        $doc->setTitle($this->getCellValueOrNull($sheet, 3, 2));
        // col 4 - lastChangeDate
        $doc->setOfficialUri($this->getCellValueOrNull($sheet, 5, 2));
        $doc->setPublisher($this->getCellValueOrNull($sheet, 6, 2));
        $doc->setDescription($this->getCellValueOrNull($sheet, 7, 2));
        $subject = $this->getCellValueOrNull($sheet, 8, 2);
        if (!empty($subject)) {
            $doc->setSubject(explode('|', $subject));
        } else {
            $doc->setSubject(null);
        }
        $doc->setLanguage($this->getCellValueOrNull($sheet, 9, 2));
        $doc->setVersion($this->getCellValueOrNull($sheet, 10, 2));
        $doc->setAdoptionStatus($this->getCellValueOrNull($sheet, 11, 2));

        if (!empty($this->getCellValueOrNull($sheet, 12, 2))) {
            $doc->setStatusStart(
                new \DateTime(
                    NumberFormat::toFormattedString(
                        $this->getCellValueOrNull($sheet, 12, 2),
                        'YYYY-MM-DD'
                    )
                )
            );
        } else {
            $doc->setStatusStart(null);
        }

        if (!empty($this->getCellValueOrNull($sheet, 13, 2))) {
            $doc->setStatusEnd(
                new \DateTime(
                    NumberFormat::toFormattedString(
                        $this->getCellValueOrNull($sheet, 13, 2),
                        'YYYY-MM-DD'
                    )
                )
            );
        } else {
            $doc->setStatusEnd(null);
        }

        if (null !== $this->getCellValueOrNull($sheet, 14, 2) && null !== $this->getCellValueOrNull($sheet, 15, 2)) {
            $licence = $this->getLicence($sheet);
            $doc->setLicence($licence);
        } else {
            $doc->setLicence(null);
        }

        $doc->setNote($this->getCellValueOrNull($sheet, 16, 2));

        $this->getEntityManager()->persist($doc);

        return $doc;
    }

    private function getLicence(Worksheet $sheet): LsDefLicence
    {
        $title = $this->getCellValueOrNull($sheet, 14, 2);
        $licenceText = $this->getCellValueOrNull($sheet, 15, 2);

        // creates licence if it doesn't exists locally
        $licence = new LsDefLicence();

        $licence->setTitle($title);
        $licence->setLicenceText($licenceText);

        $this->getEntityManager()->persist($licence);

        return $licence;
    }

    private function saveItem(Worksheet $sheet, LsDoc $doc, int $row): ?LsItem
    {
        $item = null;

        $identifier = $this->getCellValueOrNull($sheet, 1, $row);
        if (empty($identifier)) {
            $identifier = null;
        } elseif (Uuid::isValid($identifier)) {
            $item = $this->getEntityManager()->getRepository(LsItem::class)
                ->findOneBy(['identifier' => $identifier, 'lsDocIdentifier' => $doc->getIdentifier()]);
        }

        if (null === $item && !empty($this->getCellValueOrNull($sheet, 2, $row))) {
            $item = $doc->createItem($identifier);
        }

        if (null === $item) {
            return null;
        }

        $item->setFullStatement($this->getCellValueOrNull($sheet, 2, $row));
        $item->setHumanCodingScheme($this->getCellValueOrNull($sheet, 3, $row));
        // col 4 - smart level
        $item->setListEnumInSource($this->getCellValueOrNull($sheet, 5, $row));
        $item->setAbbreviatedStatement($this->getCellValueOrNull($sheet, 6, $row));
        $item->setConceptKeywordsString($this->getCellValueOrNull($sheet, 7, $row));
        $item->setNotes($this->getCellValueOrNull($sheet, 8, $row));
        $item->setLanguage($this->getCellValueOrNull($sheet, 9, $row));
        $this->setEducationalAlignment($item, $this->getCellValueOrNull($sheet, 10, $row));

        $itemTypeTitle = $this->getCellValueOrNull($sheet, 11, $row);
        $itemType = $this->findItemType($itemTypeTitle);
        $item->setItemType($itemType);

        // col 12 - licence

        // col 13+ - additional fields
        $this->addAdditionalFields($row, $item, $sheet);

        $this->getEntityManager()->persist($item);

        return $item;
    }

    private function saveAssociation(Worksheet $sheet, LsDoc $doc, int $row, array $items, array $children): ?LsAssociation
    {
        $fieldNames = [
            1 => 'identifier',
            2 => 'originNodeURI',
            3 => 'originNodeIdentifier',
            4 => 'originNodeHumanCodingScheme',
            5 => 'associationType',
            6 => 'destinationNodeURI',
            7 => 'destinationNodeIdentifier',
            8 => 'destinationNodeHumanCodingScheme',
            9 => 'associationGroupIdentifier',
            10 => 'associationGroupName',
        ];

        $itemRepo = $this->getEntityManager()->getRepository(LsItem::class);
        $association = null;
        $fields = [];

        foreach ($fieldNames as $col => $name) {
            $value = $this->getCellValueOrNull($sheet, $col, $row);
            if (null !== $value) {
                $value = (string) $value;
            }
            $fields[$name] = $value;
        }

        if (LsAssociation::CHILD_OF === $fields['associationType'] && array_key_exists($fields['originNodeIdentifier'], $children)) {
            return null;
        }

        if (empty($fields['identifier'])) {
            $fields['identifier'] = null;
        } elseif (Uuid::isValid($fields['identifier'])) {
            $association = $this->getEntityManager()->getRepository(LsAssociation::class)
                ->findOneBy(['identifier' => $fields['identifier'], 'lsDocIdentifier' => $doc->getIdentifier()]);
        }

        if (null === $association) {
            $association = $this->getEntityManager()->getRepository(LsAssociation::class)->findOneBy([
                'originNodeIdentifier' => $fields['originNodeIdentifier'],
                'type' => $fields['associationType'],
                'destinationNodeIdentifier' => $fields['destinationNodeIdentifier'],
            ]);

            if (null === $association) {
                $association = $doc->createAssociation($fields['identifier']);
            }
        }

        if (array_key_exists($fields['originNodeIdentifier'], $items)) {
            $association->setOrigin($items[$fields['originNodeIdentifier']]);
        } else {
            $ref = 'data:text/x-ref-unresolved,'.$fields['originNodeIdentifier'];
            $association->setOrigin($ref, $fields['originNodeIdentifier']);
        }

        if (array_key_exists($fields['destinationNodeIdentifier'], $items)) {
            $association->setDestination($items[$fields['destinationNodeIdentifier']]);
        } elseif ($item = $itemRepo->findOneByIdentifier($fields['destinationNodeIdentifier'])) {
            $items[$item->getIdentifier()] = $item;
            $association->setDestination($item);
        } else {
            $ref = 'data:text/x-ref-unresolved,'.$fields['destinationNodeIdentifier'];
            $association->setDestination($ref, $fields['destinationNodeIdentifier']);
        }

        $allTypes = [];
        foreach (LsAssociation::allTypes() as $type) {
            $allTypes[str_replace(' ', '', strtolower($type))] = $type;
        }

        $associationType = str_replace(' ', '', strtolower($fields['associationType']));

        if (array_key_exists($associationType, $allTypes)) {
            $association->setType($allTypes[$associationType]);
        } else {
            $log = new ImportLog();
            $log->setLsDoc($doc);
            $log->setMessageType('error');
            $log->setMessage("Invalid Association Type ({$fields['associationType']} on row {$row}.");

            $this->getEntityManager()->persist($log);

            return null;
        }

        if (!empty($fields['associationGroupIdentifier'])) {
            $associationGrouping = new LsDefAssociationGrouping();
            $associationGrouping->setLsDoc($doc);
            $associationGrouping->setTitle($fields['associationGroupName']);
            $association->setGroup($associationGrouping);
            $this->getEntityManager()->persist($associationGrouping);
        }

        $this->getEntityManager()->persist($association);

        return $association;
    }

    private function getCellValueOrNull(Worksheet $sheet, int $col, int $row): mixed
    {
        if (!$sheet->cellExists([$col, $row])) {
            return null;
        }

        return $sheet->getCell([$col, $row])->getValue();
    }

    private function checkRemovedItems(LsDoc $doc, array $array): void
    {
        $docRepo = $this->getEntityManager()->getRepository(LsDoc::class);
        $repo = $this->getEntityManager()->getRepository(LsItem::class);

        $existingItems = $docRepo->findAllItems($doc);

        $existingItems = array_filter($existingItems, static fn ($item) => !array_key_exists($item['identifier'], $array));

        foreach ($existingItems as $existingItem) {
            $element = $repo->findOneByIdentifier($existingItem['identifier']);

            if (null !== $element) {
                $repo->removeItemAndChildren($element);
            }
        }
    }

    private function checkRemovedAssociations(LsDoc $doc, array $array): void
    {
        $docRepo = $this->getEntityManager()->getRepository(LsDoc::class);
        $repo = $this->getEntityManager()->getRepository(LsAssociation::class);

        $existingAssociations = $docRepo->findAllAssociations($doc);

        $existingAssociations = array_filter($existingAssociations, static fn ($association) => !array_key_exists($association['identifier'], $array));

        foreach ($existingAssociations as $existingAssociation) {
            $element = $repo->findOneByIdentifier($existingAssociation['identifier']);

            if (null !== $element) {
                $repo->removeAssociation($element);
            }
        }
    }

    private function findItemType(?string $itemTypeTitle): ?LsDefItemType
    {
        static $itemTypes = [];

        if (null === $itemTypeTitle) {
            return null;
        }

        $itemTypeTitle = trim($itemTypeTitle);
        if ('' === $itemTypeTitle) {
            return null;
        }

        if (isset($itemTypes[$itemTypeTitle])) {
            return $itemTypes[$itemTypeTitle];
        }

        $itemType = $this->getEntityManager()->getRepository(LsDefItemType::class)
            ->findOneByTitle($itemTypeTitle);

        if (null === $itemType) {
            $itemType = new LsDefItemType();
            $itemType->setTitle($itemTypeTitle);
            $itemType->setCode($itemTypeTitle);
            $itemType->setHierarchyCode($itemTypeTitle);
            $this->getEntityManager()->persist($itemType);
        }

        $itemTypes[$itemTypeTitle] = $itemType;

        return $itemType;
    }

    private function addAdditionalFields(int $row, LsItem $item, Worksheet $sheet): void
    {
        $column = 13;

        while (null !== $this->getCellValueOrNull($sheet, $column, 1)) {
            $customField = $this->getCellValueOrNull($sheet, $column, 1);

            if (in_array($customField, self::$itemCustomFields, true)) {
                $value = $this->getCellValueOrNull($sheet, $column, $row);
                $item->setAdditionalField($customField, $value);
            }
            ++$column;
        }
    }

    private function setEducationalAlignment(LsItem $item, ?string $passedGradeString): void
    {
        $item->setEducationalAlignment(EducationLevelSet::fromString($passedGradeString)->toString());
    }
}
