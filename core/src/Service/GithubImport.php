<?php

namespace App\Service;

use App\Entity\Framework\ImportLog;
use App\Entity\Framework\LsAssociation;
use App\Entity\Framework\LsDoc;
use App\Entity\Framework\LsItem;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

class GithubImport
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * Parse an Github document into a LsDoc/LsItem hierarchy.
     *
     * @param array $missingFieldsLog
     */
    public function parseCSVGithubDocument(array $lsItemKeys, string $fileContent, string $lsDocId, string $frameworkToAssociate, $missingFieldsLog): void
    {
        $csvContent = str_getcsv($fileContent, "\n");
        $headers = [];
        $content = [];

        foreach ($csvContent as $i => $row) {
            $tempContent = [];
            $row = str_getcsv($row, ',');

            if (0 === $i) {
                $headers = $row;
                continue;
            }

            foreach ($headers as $h => $col) {
                if ($h < count($row)) {
                    $tempContent[$col] = $row[$h];
                }
            }

            $content[] = $tempContent;
        }

        $this->saveCSVGithubDocument($lsItemKeys, $content, $lsDocId, $frameworkToAssociate, $missingFieldsLog);
    }

    /**
     * Save an Github document into a LsDoc/LsItem hierarchy.
     *
     * @param array $lsItemKeys
     * @param array $content
     * @param array $missingFieldsLog
     */
    public function saveCSVGithubDocument($lsItemKeys, $content, $lsDocId, $frameworkToAssociate, $missingFieldsLog): void
    {
        $em = $this->getEntityManager();
        $lsDoc = $em->getRepository(LsDoc::class)->find($lsDocId);

        if (null !== $missingFieldsLog && count($missingFieldsLog) > 0) {
            foreach ($missingFieldsLog as $messageError) {
                $errorLog = new ImportLog();
                $errorLog->setLsDoc($lsDoc);
                $errorLog->setMessage($messageError);
                $errorLog->setMessageType('warning');

                $em->persist($errorLog);
            }
        } else {
            $successLog = new ImportLog();
            $successLog->setLsDoc($lsDoc);
            $successLog->setMessage('Items successfully imported.');
            $successLog->setMessageType('info');

            $em->persist($successLog);
        }

        // Build the lsItems array
        $lsItems = [];
        $sequenceNumbers = [];
        $humanCodingValues = [];
        for ($i = 0, $iMax = count($content); $i < $iMax; ++$i) {
            $lineContent = $content[$i];
            if (!$this->isValidItemContent($lineContent, $lsItemKeys)) {
                $lsItems[$i] = null;
                continue;
            }

            $lsItem = $this->parseCSVGithubStandard($lsDoc, $lsItemKeys, $lineContent);

            if (!empty($lsItem->getHumanCodingScheme())) {
                $humanCodingValues[$lsItem->getHumanCodingScheme()] = $i;
            }

            $seq = $lineContent[trim($lsItemKeys['sequenceNumber'] ?? null)] ?? null;
            if (!is_numeric($seq)) {
                $seq = null;
            }
            $sequenceNumbers[$i] = $seq;

            $lsItems[$i] = $lsItem;
        }

        // Build associations
        for ($i = 0, $iMax = count($content); $i < $iMax; ++$i) {
            if (null === ($lsItems[$i] ?? null)) {
                continue;
            }

            $lsItem = $lsItems[$i];

            // Check if the lsItems UUID exists in the ls_association table and
            // if it is do not create a new association. This allows content
            // updates but doesn't double up the associations.
            $thisItemsUUID = $lsItem->getIdentifier();
            $associationExists = $em->getRepository(LsAssociation::class)->findOneBy(['originNodeIdentifier' => $thisItemsUUID]);
            $logDetails = date('Y/m/d h:i:s A ')."The lsItem with the Human coding scheme of {$content[$i]['Human Coding Scheme']} and UUID of {$thisItemsUUID} has been added.";

            if (!$associationExists) {
                // Log new items added.
                $errorLog = new ImportLog();
                $errorLog->setLsDoc($lsDoc);
                $errorLog->setMessage($logDetails);
                $errorLog->setMessageType('warning');
                $em->persist($errorLog);

                // check if the item returns a humancodingscheme
                $parent = $content[$i][$lsItemKeys['isChildOf'] ?? null] ?? null;
                if (empty($parent)) {
                    $humanCoding = $lsItem->getHumanCodingScheme();
                    $parent = substr($humanCoding, 0, strrpos($humanCoding, '.'));
                }

                if (array_key_exists($parent, $humanCodingValues)) {
                    $lsItems[$humanCodingValues[$parent]]->addChild($lsItem, null, $sequenceNumbers[$i]);
                } else {
                    $lsDoc->addTopLsItem($lsItem, null, $sequenceNumbers[$i]);
                }

                $this->saveAssociations($i, $content, $lsItemKeys, $lsItem, $lsDoc, $frameworkToAssociate);
            }
        }
    }

    /**
     * @param int       $position
     * @param array     $content
     * @param array     $lsItemKeys
     * @param string    $frameworkToAssociate
     */
    public function saveAssociations($position, $content, $lsItemKeys, LsItem $lsItem, LsDoc $lsDoc, $frameworkToAssociate): void
    {
        $fieldsAndTypes = LsAssociation::allTypesForImportFromCSV();
        // We don't use is_child_of because that it already used to create parents relations before. :)
        // checking each association field
        foreach ($fieldsAndTypes as $fieldName => $assocType) {
            if (array_key_exists($fieldName, $lsItemKeys) && $cfAssociations = $content[$position][trim($lsItemKeys[$fieldName])]) {
                foreach (explode(',', $cfAssociations) as $cfAssociation) {
                    $this->addItemRelated($lsDoc, $lsItem, $cfAssociation, $frameworkToAssociate, $assocType);
                }
            }
        }
    }

    /**
     * @param string  $cfAssociation
     * @param string  $frameworkToAssociate
     * @param string  $assocType
     */
    public function addItemRelated(LsDoc $lsDoc, LsItem $lsItem, $cfAssociation, $frameworkToAssociate, $assocType): void
    {
        $em = $this->getEntityManager();

        if ('' !== trim($cfAssociation)) {
            if ('all' === $frameworkToAssociate) {
                $itemsAssociated = $em->getRepository(LsItem::class)
                    ->findAllByIdentifierOrHumanCodingSchemeByValue($cfAssociation);
            } else {
                $itemsAssociated = $em->getRepository(LsItem::class)
                    ->findByAllIdentifierOrHumanCodingSchemeByLsDoc($frameworkToAssociate, $cfAssociation);
            }

            if (count($itemsAssociated) > 0) {
                foreach ($itemsAssociated as $itemAssociated) {
                    $this->saveAssociation($lsDoc, $lsItem, $itemAssociated, $assocType);
                }
            } else {
                $this->saveAssociation($lsDoc, $lsItem, $cfAssociation, $assocType);
            }
        }
    }

    /**
     * @param string|LsItem $elementAssociated
     * @param string $assocType
     */
    public function saveAssociation(LsDoc $lsDoc, LsItem $lsItem, $elementAssociated, $assocType): void
    {
        $association = new LsAssociation();
        $association->setType($assocType);
        $association->setLsDoc($lsDoc);
        $association->setOrigin($lsItem);

        if (is_string($elementAssociated)) {
            if (Uuid::isValid($elementAssociated)) {
                $association->setDestinationNodeIdentifier($elementAssociated);
            } elseif (false === !filter_var($elementAssociated, FILTER_VALIDATE_URL)) {
                $association->setDestinationNodeUri($elementAssociated);
                $association->setDestinationNodeIdentifier(Uuid::uuid5(Uuid::NAMESPACE_URL, $elementAssociated));
            } else {
                $encodedHumanCodingScheme = $this->encodeHumanCodingScheme($elementAssociated);
                $association->setDestinationNodeUri($encodedHumanCodingScheme);
                $association->setDestinationNodeIdentifier(Uuid::uuid5(Uuid::NAMESPACE_URL, $encodedHumanCodingScheme));
            }
        } else {
            $association->setDestination($elementAssociated);
        }

        $this->getEntityManager()->persist($association);
    }

    /**
     * @param string $humanCodingScheme
     */
    protected function encodeHumanCodingScheme($humanCodingScheme): string
    {
        $prefix = 'data:text/x-ref-unresolved;base64,';

        return $prefix.base64_encode($humanCodingScheme);
    }

    /**
     * @param array $lsItemKeys
     * @param array $data
     */
    public function parseCSVGithubStandard(LsDoc $lsDoc, $lsItemKeys, $data): LsItem
    {
        $em = $this->getEntityManager();

        // Query the db for matching UUIDs.
        $resultOfSearch = $em->getRepository(LsItem::class)->findOneBy(['identifier' => $data[$lsItemKeys['identifier']]]);

        $itemAttributes = ['humanCodingScheme', 'abbreviatedStatement', 'conceptKeywords', 'language', 'license', 'notes'];

        // If not in the DB create a new lsItem.
        if (null === $resultOfSearch) {
            $lsItem = $this->assignValuesToItem(new LsItem(), $lsDoc, $lsItemKeys, $data, $itemAttributes);
            $em->persist($lsItem);

            return $lsItem;
        }

        // Update if in db and changed.
        $fullStatement = $resultOfSearch->getFullStatement();
        if ($data[$lsItemKeys['fullStatement']] !== $fullStatement) {
            $lsItem = $this->assignValuesToItem($resultOfSearch, $lsDoc, $lsItemKeys, $data, $itemAttributes);
            $em->persist($lsItem);

            // Log if we make an update.
            $logDetails = date('Y/m/d h:i:s A ')."The lsItem with the Human coding scheme of {$data[$lsItemKeys['humanCodingScheme']]} has been updated.";
            $errorLog = new ImportLog();
            $errorLog->setLsDoc($lsDoc);
            $errorLog->setMessage($logDetails);
            $errorLog->setMessageType('warning');

            $em->persist($errorLog);

            return $lsItem;
        }

        return $resultOfSearch;
    }

    /**
     * It returns true|false is a line from content has the requried columns.
     */
    private function isValidItemContent(array $lineContent, array $lsItemKeys): bool
    {
        return ($lineContent[$lsItemKeys['fullStatement']] ?? '') !== '';
    }

    /**
     * assign values relatd with the key to a Item.
     */
    private function assignValuesToItem(LsItem $lsItem, LsDoc $lsDoc, array $lsItemKeys, array $lineContent, array $keys): LsItem
    {
        $lsItem->setLsDoc($lsDoc);
        $lsItem->setFullStatement($lineContent[$lsItemKeys['fullStatement']]);

        if (array_key_exists($lsItemKeys['identifier'], $lineContent) && Uuid::isValid($lineContent[$lsItemKeys['identifier']])) {
            $lsItem->setIdentifier($lineContent[$lsItemKeys['identifier']]);
            $lsItem->setUri('local:'.$lineContent[$lsItemKeys['identifier']]);
        }

        foreach ($keys as $key) {
            if ('license' === $key) {
                // Skip loading licence from the CSV for now
                continue;
            }

            if (array_key_exists($key, $lsItemKeys) && array_key_exists($lsItemKeys[$key], $lineContent)) {
                $lsItem->{'set'.ucfirst($key)}($lineContent[$lsItemKeys[$key]]);
            }
        }

        return $lsItem;
    }
}
