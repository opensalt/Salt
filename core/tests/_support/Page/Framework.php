<?php

namespace Page;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Codeception\Util\Locator;
use Facebook\WebDriver\Exception\StaleElementReferenceException;
use PHPUnit\Framework\ExpectationFailedException;
use Ramsey\Uuid\Uuid;

class Framework implements Context
{
    public static $docPath = '/cftree/doc/';
    public static $lsdocPath = '/cfdoc/';
    public static $creatorsPath = '/api/v1/lor/creators';
    public static $frameworksByCreatorPath = '/api/v1/lor/frameworksByCreator/';
    public static $additionalFieldPath = '/additionalfield';

    public static $fwTitle = '#ls_doc_create_title';
    public static $fwCreatorField = '#ls_doc_create_creator';
    //    public static $frameworkCreatorValue = 'PCG QA Testing';

    protected static $failedCreateCount = 0;

    protected $filename;
    protected $rememberedFramework;
    protected $uploadedFramework;
    protected $importedAsnDoc;
    protected $importedAsnList;
    protected $creatorName = 'OpenSALT Testing';
    protected $frameworkData = [];
    protected $id;
    protected $rememberedCreator;

    /**
     * @var \AcceptanceTester
     */
    protected $I;

    public function __construct(\AcceptanceTester $I)
    {
        $this->I = $I;
    }

    /**
     * @Given /^I am on a framework page$/
     */
    public function iAmOnAFrameworkPage(): Framework
    {
        $I = $this->I;
        $I->iAmOnTheHomepage();
        $I->getLastFrameworkId();
        $I->amOnPage(self::$docPath.$I->getDocId());
        $I->waitForElementNotVisible('#modalSpinner', 120);
        return $this;
    }

    /**
     * @Then /^I should see the framework tree$/
     */
    public function iShouldSeeTheFrameworkTree(): Framework
    {
        $I = $this->I;

        $I->waitForElementVisible('#treeSideLeft span.fancytree-node', 120);
        $I->seeElement('#treeSideLeft span.fancytree-node');

        return $this;
    }

    /**
     * @Given /^I should see the framework information$/
     */
    public function iShouldSeeTheFrameworkInformation(): Framework
    {
        $I = $this->I;

        $I->seeElement('#treeSideRight h4.itemTitle span.itemTitleSpan');

        return $this;
    }

    /**
     * @When /^I download the framework CASE file$/
     */
    public function iDownloadTheFrameworkCaseFile(): Framework
    {
        $I = $this->I;

        $I->click('Export', '#itemInfo');
        $I->waitForElementVisible('#exportModal a.btn-export-case');
        $url = $I->grabAttributeFrom('#exportModal a.btn-export-case', 'href');

        $this->filename = $I->download($url);

        return $this;
    }

    /**
     * @Then /^I should see content in the CASE file$/
     */
    public function iShouldSeeContentInTheCASEFile(): Framework
    {
        $I = $this->I;

        $caseFile = file_get_contents($this->filename);
        $I->assertNotEmpty($caseFile, 'CASE file is empty');

        $parsedJson = json_decode($caseFile, true);
        $I->assertArrayHasKey('CFDocument', $parsedJson, 'CASE file does not have a CFDocument part');
        $I->assertArrayHasKey('CFItems', $parsedJson, 'CASE file does not have a CFItems part');
        $I->assertArrayHasKey('CFAssociations', $parsedJson, 'CASE file does not have a CFAssociations part');

        return $this;
    }

    /**
     * @When /^I click on copy framework modal button$/
     */
    public function iClickOnCopyFrameworkModalButton()
    {
        $this->I->click('#js-copy-framework-modal-button');

        return $this;
    }

    /**
     * @When /^I click on copy framework$/
     */
    public function IClickOnCopyFramework()
    {
        $this->I->click('#copyFrameworkModal .modal-footer .js-btn-copy-button');

        return $this;
    }

    /**
     * @Then /^I should not see the copy framework modal button$/
     */
    public function iShouldNotSeeTheCopyFrameworkModalButton(): Framework
    {
        $this->I->dontSee('#js-copy-framework-modal-button');
        return $this;
    }

    /**
     * @Then /^I should see the copy framework modal button$/
     */
    public function iShouldSeeTheCopyFrameworkModalButton(): Framework
    {
        $this->I->seeElement('#js-copy-framework-modal-button');
        return $this;
    }

    /**
     * @Then /^I should see a success message$/
     */
    public function IShouldSeeASuccessMessage(): Framework
    {
        $this->I->waitForElementVisible('#copyFrameworkModal .alert-success');
        return $this;
    }

    /**
     * @Then /^I should see the copy framework modal$/
     */
    public function iShouldSeeTheCopyFrameworkModal(): Framework
    {
        $this->I->waitForElementVisible('#copyFrameworkModal .js-btn-copy-button');
        return $this;
    }

    /**
     * @When /^I click on copy from button$/
     */
    public function iClickOnCopyFromButton(): Framework
    {
        $this->I->click('#copyFrameworkModal_copyLeftBtn');
        return $this;
    }

    /**
     * @Then /^I should see the copy from button active$/
     */
    public function iShouldSeeTheCopyFromButtonActive(): Framework
    {
        $this->I->click('#copyFrameworkModal_copyLeftBtn');
        return $this;
    }

    /**
     * @Then /^I should see the import dialogue$/
     */
    public function iShouldSeeTheImportDialogue(): Framework
    {
        $I = $this->I;

        $I->waitForElementVisible('#wizard .btn-import-case');

        return $this;
    }

    /**
     * @Given /^I upload the empty remote CASE file$/
     */
    public function iUploadTheEmptyRemoteCASEFile(): Framework
    {
        $I = $this->I;

        $I->waitForElementVisible('#file-url');

        $data = file_get_contents(codecept_data_dir().'EmptyExternalFramework.json');

        $name = sq('EmptyExternalFramework');
        $uuid = Uuid::uuid4()->toString();

        $this->rememberedFramework = $name;
        $this->creatorName = 'OpenSALT Testing';

        $data = str_replace([
            'Test Framework External Empty',
            'd0000000-0000-0000-0000-000000000000',
        ], [$name, $uuid], $data);
        $this->uploadedFramework = $data;

        $filename = tempnam(codecept_data_dir(), 'tmp_eef_');
        unlink($filename);
        file_put_contents($filename.'.json', $data);

        $I->attachFile('input#file-url', str_replace(codecept_data_dir(), '', $filename.'.json'));
        $I->click('a.btn-import-case');
        $I->waitForElementNotVisible('#wizard', 60);

        unlink($filename.'.json');

        return $this;
    }

    /**
     * @When /^I select the framework node$/
     * @When /^I click on the framework node$/
     */
    public function iSelectFrameworkNode(): Framework
    {
        $el = ['xpath' => '(//div[@id="viewmode_tree1"]/ul/li/span)[1]'];

        try {
            $this->I->click($el);
        } catch (StaleElementReferenceException $e) {
            // Wait for 1 second and try again
            $this->I->wait(1);
            $this->I->waitForElementVisible($el, 10);
            $this->I->click($el);
        }

        return $this;
    }

    /**
     * @Given /^I go to the uploaded framework$/
     * @Given /^I go to the created framework$/
     */
    public function iGoToTheUploadedFramework(): Framework
    {
        $I = $this->I;
        $creatorName = $this->creatorName;

        $I->iAmOnTheHomepage();
        $I->click("//span[text()='{$creatorName}']/../..");

        $frameworkName = $this->rememberedFramework;
        $I->waitForElementVisible("//span[text()='{$frameworkName}']");
        $I->click("//span[text()='{$frameworkName}']/../..");

        $I->waitForElementNotVisible('#modalSpinner', 120);
        $I->waitForElementVisible('#itemSection h4.itemTitle', 120);
        $I->setDocId($I->grabValueFrom('#lsDocId'));

        return $this;
    }

    /**
     * @Then /^the downloaded framework should match the uploaded one$/
     */
    public function theDownloadedFrameworkShouldMatchTheUploadedOne()
    {
        $I = $this->I;

        $caseFile = file_get_contents($this->filename);
        $I->assertNotEmpty($caseFile, 'CASE file is empty');

        $parsedJson = json_decode($caseFile, true);
        $uploadedJson = json_decode($this->uploadedFramework, true);

        $I->assertArrayHasKey('CFDocument', $parsedJson, 'CASE file does not have a CFDocument part');

        if (!empty($uploadedJson['CFItems'])) {
            $I->assertArrayHasKey('CFItems', $parsedJson, 'CASE file does not have a CFItems part');
        } else {
            unset($uploadedJson['CFItems']);
        }
        if (!empty($uploadedJson['CFAssociations'])) {
            $I->assertArrayHasKey('CFAssociations', $parsedJson, 'CASE file does not have a CFAssociations part');
        } else {
            unset($uploadedJson['CFAssociations']);
        }

        $diff = $this->arrayDiff(
            $uploadedJson,
            $parsedJson,
            ['lastChangeDateTime', 'CFDefinitions', 'CFItemTypeURI', 'sequenceNumber']
        );
        $I->assertEquals([], $diff, 'Downloaded JSON does not match');

        return $this;
    }

    public function arrayDiff(array $arr1, array $arr2, array $allowedDiffs = []): array
    {
        $diff = [];

        // Check the similarities
        foreach ($arr1 as $k1 => $v1) {
            if (isset($arr2[$k1])) {
                $v2 = $arr2[$k1];
                if (is_array($v1) && is_array($v2)) {
                    // 2 arrays: just go further...
                    // .. and explain it's an update!
                    $changes = $this->arrayDiff($v1, $v2, $allowedDiffs);
                    if (\count($changes) > 0 && !\in_array($k1, $allowedDiffs, true)) {
                        // If we have no change, simply ignore
                        $diff[$k1] = ['upd' => $changes];
                    }
                    unset($arr2[$k1]); // don't forget
                } elseif ($v2 === $v1) {
                    // unset the value on the second array
                    // for the "surplus"
                    unset($arr2[$k1]);
                } else {
                    // Don't mind if arrays or not.
                    if (!\in_array($k1, $allowedDiffs, true)) {
                        $diff[$k1] = ['old' => $v1, 'new' => $v2];
                    }
                    unset($arr2[$k1]);
                }
            } else {
                if (('CFDefinitions' === $k1)
                    && (count($v1['CFConcepts'] ?? []) === 0)
                    && (count($v1['CFSubjects'] ?? []) === 0)
                    && (count($v1['CFLicenses'] ?? []) === 0)
                    && (count($v1['CFItemTypes'] ?? []) === 0)
                    && (count($v1['CFAssociationGroupings'] ?? []) === 0)) {
                        unset($arr1[$k1]);
                        continue;
                    }

                // remove information
                $diff[$k1] = ['old' => $v1];
            }
        }

        // Now, check for new stuff in $arr2
        reset($arr2); // Don't argue it's unnecessary (even I believe you)
        foreach ($arr2 as $k => $v) {
            // OK, it is quite stupid my friend
            if (!\in_array($k, $allowedDiffs, true)) {
                $diff[$k] = ['new' => $v];
            }
        }

        return $diff;
    }

    /**
     * @Given /^I upload the test smartlevel framework$/
     */
    public function iUploadTheTestSmartlevelFramework(): Framework
    {
        $I = $this->I;

        $I->waitForElementVisible('#file-url');

        $data = file_get_contents(codecept_data_dir().'SmartLevelFramework.json');

        $name = sq('SmartLevelFramework');
        $docUuid = Uuid::uuid4()->toString();
        $this->rememberedFramework = $name;
        $this->creatorName = 'OpenSALT Testing';

        $origValues = [
            'SmartLevel Test',
            'd0000000-0000-0000-0000-000000000000',
        ];
        $replacements = [
            $name,
            $docUuid,
        ];

        $decoded = json_decode($data, true);
        foreach ($decoded['CFItems'] as $item) {
            $origValues[] = $item['identifier'];
            $replacements[] = Uuid::uuid4()->toString();
        }
        foreach ($decoded['CFAssociations'] as $item) {
            $origValues[] = $item['identifier'];
            $replacements[] = Uuid::uuid4()->toString();
        }

        $data = str_replace($origValues, $replacements, $data);

        $this->uploadedFramework = $data;

        $filename = tempnam(codecept_data_dir(), 'tmp_eef_');
        unlink($filename);
        file_put_contents($filename.'.json', $data);

        $I->attachFile('input#file-url', str_replace(codecept_data_dir(), '', $filename.'.json'));
        $I->click('a.btn-import-case');
        $I->waitForElementNotVisible('#wizard', 60);

        unlink($filename.'.json');

        return $this;
    }

    /**
     * @Given /^I download the framework excel file$/
     */
    public function iDownloadTheFrameworkExcelFile(): Framework
    {
        $I = $this->I;

        $I->click('Export', '#itemInfo');
        $I->waitForElementVisible('#exportModal a.btn-export-excel');
        $url = $I->grabAttributeFrom('#exportModal a.btn-export-excel', 'href');

        $this->filename = $I->download($url);

        return $this;
    }

    /**
     * @Then /^I should have an excel file with smart levels$/
     */
    public function iShouldHaveAnExcelFileWithSmartLevels(): Framework
    {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($this->filename);
        $ss = $reader->load($this->filename);

        $sheet = $ss->getSheetByName('CF Item');

        $row = 1;
        while (!empty($sheet->getCell('A'.++$row)) && !empty($sheet->getCell('A'.++$row)->getValue())) {
            $item = $sheet->getCell('B'.$row)->getValue();
            $smartLevel = $sheet->getCell('D'.$row)->getValue();

            $item = str_replace('Item ', '', $item);
            $this->I->assertSame($item, $smartLevel, 'Smart level does not match');
        }

        return $this;
    }

    /**
     * @Given /^I upload the utf8\-test CASE file$/
     */
    public function iUploadTheUtf8TestCASEFile()
    {
        $I = $this->I;

        $I->waitForElementVisible('#file-url');

        $data = file_get_contents(codecept_data_dir().'Utf8TestFramework.json');

        $name = sq('Utf8Framework');
        $docUuid = Uuid::uuid4()->toString();
        $this->rememberedFramework = $name;
        $this->creatorName = 'OpenSALT Testing';

        $origValues = [
            "UTF8 \u{1d451} Test",
            'd0000000-0000-0000-0000-000000000000',
        ];
        $replacements = [
            $name,
            $docUuid,
        ];

        $decoded = json_decode($data, true);
        foreach ($decoded['CFItems'] as $item) {
            $origValues[] = $item['identifier'];
            $replacements[] = Uuid::uuid4()->toString();
        }
        foreach ($decoded['CFAssociations'] as $item) {
            $origValues[] = $item['identifier'];
            $replacements[] = Uuid::uuid4()->toString();
        }

        /*
        foreach ($origValues as $i => $origValue) {
            $data = mb_ereg_replace("/{$origValue}/", $replacements[$i], $data);
        }
         */
        $data = str_replace($origValues, $replacements, $data);

        $this->uploadedFramework = $data;

        $filename = tempnam(codecept_data_dir(), 'tmp_eef_');
        unlink($filename);
        file_put_contents($filename.'.json', $data);

        $I->attachFile('input#file-url', str_replace(codecept_data_dir(), '', $filename.'.json'));
        $I->click('a.btn-import-case');
        $I->waitForElementNotVisible('#wizard', 60);

        unlink($filename.'.json');

        return $this;
    }

    /**
     * @Given /^I upload the markdown CASE file$/
     */
    public function iUploadTheMarkdownCASEFile()
    {
        $I = $this->I;

        $I->waitForElementVisible('#file-url');

        $data = file_get_contents(codecept_data_dir().'MarkdownFramework.json');

        $name = sq('MarkdownFramework');
        $docUuid = Uuid::uuid4()->toString();
        $this->rememberedFramework = $name;
        $this->creatorName = 'OpenSALT Testing';

        $origValues = [
            'Test Markdown Framework',
            'd0000000-0000-0000-0000-000000000000',
        ];
        $replacements = [
            $name,
            $docUuid,
        ];

        $decoded = json_decode($data, true);
        foreach ($decoded['CFItems'] as $item) {
            $origValues[] = $item['identifier'];
            $replacements[] = Uuid::uuid4()->toString();
        }
        foreach ($decoded['CFAssociations'] as $item) {
            $origValues[] = $item['identifier'];
            $replacements[] = Uuid::uuid4()->toString();
        }

        /*
        foreach ($origValues as $i => $origValue) {
            $data = mb_ereg_replace("/{$origValue}/", $replacements[$i], $data);
        }
         */
        $data = str_replace($origValues, $replacements, $data);

        $this->uploadedFramework = $data;

        $filename = tempnam(codecept_data_dir(), 'tmp_mdf_');
        unlink($filename);
        file_put_contents($filename.'.json', $data);

        $I->attachFile('input#file-url', str_replace(codecept_data_dir(), '', $filename.'.json'));
        $I->click('a.btn-import-case');
        $I->waitForElementNotVisible('#wizard', 60);

        unlink($filename.'.json');

        return $this;
    }

    /**
     * @Given /^I upload the sequence number CASE file$/
     */
    public function iUploadTheSequenceNumberCASEFile()
    {
        $I = $this->I;

        $I->waitForElementVisible('#file-url');

        $data = file_get_contents(codecept_data_dir().'SequenceNumberFramework.json');

        $name = sq('SeqNumFramework');
        $docUuid = Uuid::uuid4()->toString();
        $this->rememberedFramework = $name;

        $origValues = [
            'Sequence Number Test',
            'd0000000-0000-0000-0000-000000000000',
        ];
        $replacements = [
            $name,
            $docUuid,
        ];

        $decoded = json_decode($data, true);
        foreach ($decoded['CFItems'] as $item) {
            $origValues[] = $item['identifier'];
            $replacements[] = Uuid::uuid4()->toString();
        }
        foreach ($decoded['CFAssociations'] as $item) {
            $origValues[] = $item['identifier'];
            $replacements[] = Uuid::uuid4()->toString();
        }

        /*
        foreach ($origValues as $i => $origValue) {
            $data = mb_ereg_replace("/{$origValue}/", $replacements[$i], $data);
        }
         */
        $data = str_replace($origValues, $replacements, $data);

        $this->uploadedFramework = $data;

        $filename = tempnam(codecept_data_dir(), 'tmp_mdf_');
        unlink($filename);
        file_put_contents($filename.'.json', $data);

        $I->attachFile('input#file-url', str_replace(codecept_data_dir(), '', $filename.'.json'));
        $I->click('a.btn-import-case');
        $I->waitForElementNotVisible('#wizard', 60);

        unlink($filename.'.json');

        return $this;
    }

    /**
     * @When /^I fill in an ASN document identifier$/
     */
    public function iFillInAnASNDocumentIdentifier(): Framework
    {
        $asnDocs = file(codecept_data_dir('SampleASNDocList.txt'));
        $this->importedAsnDoc = trim($asnDocs[random_int(0, count($asnDocs) - 1)]);

        $this->I->fillField('#asn-url', $this->importedAsnDoc);

        return $this;
    }

    /**
     * @Then /^I should see the ASN framework loaded$/
     */
    public function iShouldSeeTheASNFrameworkLoaded(): Framework
    {
        $I = $this->I;

        $I->waitForElementNotVisible('#wizard', 60);

        $I->click('//span[text()="Imported from ASN"]/../..');
        $docList = $I->grabMultiple('//span[text()="Imported from ASN"]/../../ul/li/span/span[@class="fancytree-title"]');

        $I->assertEquals(count($this->importedAsnList) + 1, count($docList), 'Count of imported ASN documents did not increase by 1');

        return $this;
    }

    /**
     * @Then /^I count frameworks imported from ASN$/
     */
    public function iCountFrameworksImportedFromASN(): Framework
    {
        $I = $this->I;

        try {
            $I->click('//span[text()="Imported from ASN"]/../..');
        } catch (\Exception $e) {
            // It is okay if there are none
        }
        $this->importedAsnList = $I->grabMultiple('//span[text()="Imported from ASN"]/../../ul/li/span/span[@class="fancytree-title"]');

        return $this;
    }

    /**
     * @Then /^I should see math in the framework$/
     */
    public function iShouldSeeMathInTheFramework()
    {
        $I = $this->I;

        $I->click('//span[text()="MD.Math"]/../../..');
        $I->seeElement('.lsItemDetails .katex');
    }

    /**
     * @Given /^I should see a table in the framework$/
     */
    public function iShouldSeeTablesInTheFramework(): void
    {
        $I = $this->I;

        $I->click('//span[text()="MD.Table"]/../../..');
        $I->seeElement('.lsItemDetails table');
    }

    /**
     * @Given /^I should see an underline in the framework$/
     */
    public function iShouldSeeUnderlineInTheFramework(): void
    {
        $I = $this->I;

        $I->click('//span[text()="MD.Table"]/../../..');
        $I->seeElement('.lsItemDetails u');
    }

    /**
     * @Given /^I should see a math equation in the framework$/
     */
    public function iShouldSeeMathEquationInTheFramework(): void
    {
        $I = $this->I;

        $I->click('//span[text()="MD.Table"]/../../..');
        $I->seeElement('.lsItemDetails .katex');
    }

    /**
     * @When /^I display modal to edit framework$/
     */
    public function iDisplayModalToEditFramework(): Framework
    {
        $I = $this->I;

        $this->iGoToTheFrameworkDocument();
        $I->waitForElementVisible('//*[@id="documentOptions"]/button[@data-bs-target="#editDocModal"]');
        $I->click('//*[@id="documentOptions"]/button[@data-bs-target="#editDocModal"]');
        $I->waitForElementVisible('#ls_doc_title');

        return $this;
    }

    /**
     * @Then /^I should see licence edit drop-down$/
     */
    public function iShouldSeeLicenceEditDropDown(): void
    {
        $I = $this->I;

        $I->waitForElementVisible('#ls_doc_licence');
    }

    /**
     * @Then /^I should see licence drop-down$/
     */
    public function iShouldSeeLicenceDropDown(): void
    {
        $I = $this->I;

        $I->seeElement('#ls_doc_create_licence');
    }

    /**
     * @Given /^I should see an alpha ordered list in the framework$/
     */
    public function iShouldSeeAlphaOrderedListInTheFramework(): void
    {
        $I = $this->I;

        $I->click('//span[text()="MD.Table"]/../../..');
        $I->seeElement(".lsItemDetails ol {type: 'I'}");
    }

    /**
     * @Then /^I should see "([^"]*)" button$/
     */
    public function iShouldSeeButton($buttonText): void
    {
        $I = $this->I;

        $I->see($buttonText);
    }

    /**
     * @Given /^I click the "([^"]*)" button$/
     */
    public function iClickTheButton($button): void
    {
        $I = $this->I;

        $I->click($button);
    }

    /**
     * @When /^I create a "([^"]*)" framework$/
     */
    public function iCreateAFramework($framework = 'Test Framework'): void
    {
        $I = $this->I;

        $I->amGoingTo('submit a filled in framework create form');

        if (static::$failedCreateCount >= 5) {
            $I->amGoingTo('not bother trying, too many errors creating frameworks already');
            throw new \RuntimeException('Not trying: Too many framework create failures already.');
        }

        /** @var \Faker\Generator $faker */
        $faker = \Faker\Factory::create();

        $description = $faker->sentence();

        $note = $faker->paragraph();
        $framework = sq($framework);
        $this->rememberedFramework = $framework;

        $this->frameworkData = [
            'title' => $framework,
            'creator' => $this->creatorName,
            'officialUri' => 'http://opensalt.net',
            'publisher' => 'PCG',
            'version' => '1.0',
            'description' => $description,
            'language' => 'en',
            'adoptionStatus' => 'Draft',
            'note' => $note,
            'license' => 'Attribution 4.0 International',
            'statusStart' => '2020-01-01',
            'statusEnd' => '2020-01-02',
        ];

        $I->fillField(self::$fwTitle, $framework);
        $I->fillField(self::$fwCreatorField, $this->creatorName);
        $I->fillField('#ls_doc_create_officialUri', $this->frameworkData['officialUri']);
        $I->fillField('#ls_doc_create_publisher', $this->frameworkData['publisher']);
        //       $I->fillField('#ls_doc_create_urlName','OpenSALT');
        $I->fillField('#ls_doc_create_version', $this->frameworkData['version']);
        $I->fillField('#ls_doc_create_description', $description);
        //       $I->selectOption('.select2-search__field', array('text' => 'Math')); //Subject field
        $I->selectOption('ls_doc_create[language]', ['value' => $this->frameworkData['language']]);
        $I->selectOption('ls_doc_create[adoptionStatus]', ['text' => $this->frameworkData['adoptionStatus']]);
        $statusStart = $this->frameworkData['statusStart'];
        //$I->fillField('#ls_doc_statusStart', $statusStart);
        $I->executeJS("$('#ls_doc_create_statusStart').val('{$statusStart}');");
        $statusEnd = $this->frameworkData['statusEnd'];
        $I->executeJS("$('#ls_doc_create_statusEnd').val('{$statusEnd}');");
        $I->fillField('#ls_doc_create_note', $note);
        // Choose one license
        $I->click(Locator::lastElement('.select2-container--bootstrap-5'));
        $I->waitForElementVisible('.select2-results__option--highlighted');
        $I->click('.select2-results__option--highlighted');

        $I->submitForm(['css' => 'form[name="ls_doc_create"]'], []);
        //$I->scrollTo(['css' => 'footer']);
        //$I->clickWithLeftButton(['css' => 'input[value="Create"]']);

        try {
            $I->waitForElementNotVisible('#ls_doc_create', 30);
            $I->waitForElementVisible('#docTitle', 30);
        } catch (\Exception $e) {
            ++static::$failedCreateCount;
            throw $e;
        }

        $I->see($framework, '#docTitle');
        $I->setDocId($I->grabValueFrom('#lsDocId'));
    }

    /**
     * @When /^I create a framework$/
     */
    public function iCreateAFramework1(): void
    {
        $I = $this->I;

        $I->see('Create a new Framework');
        $I->click('Create a new Framework');
        $I->see('Create New Framework Package');
        $this->iCreateAFramework();
    }

    /**
     * @When /^I create a framework with a remembered creator$/
     */
    public function iCreateAFrameworkRememberingTheCreator(): void
    {
        $I = $this->I;

        $I->see('Create a new Framework');
        $I->click('Create a new Framework');
        $I->see('Create New Framework Package');

        /** @var \Faker\Generator $faker */
        $faker = \Faker\Factory::create();
        $this->rememberedCreator = $faker->company();
        $oldCreator = $this->creatorName;
        $this->creatorName = $this->rememberedCreator;

        $this->iCreateAFramework();

        $this->creatorName = $oldCreator;
    }

    /**
     * @Given /^I should see the framework$/
     * @Given /^I should see the framework data$/
     */
    public function iShouldSeeFramework(): void
    {
        $I = $this->I;

        $I->waitForElementVisible('.itemTitleSpan');

        $I->see('Official URL:');
        $I->see($this->frameworkData['officialUri']);
        $I->see('Identifier:');
        $I->see('Creator:');
        $I->see($this->frameworkData['creator']);
        $I->see('Publisher:');
        $I->see($this->frameworkData['publisher']);
        $I->see('Language:');
        $I->see($this->frameworkData['language']);
        $I->see('Adoption Status:');
        $I->see($this->frameworkData['adoptionStatus']);
        $I->see('License:');
        $I->see($this->frameworkData['license']);
    }

    /**
     * @Given /^I delete the framework$/
     */
    public function iDeleteFramework(): void
    {
        $I = $this->I;

        $I->amOnPage(self::$lsdocPath.$I->getDocId());

        $I->click('Delete');
    }

    /**
     * @Given /^I edit the field in framework$/
     */
    public function iEditTheFieldInFramework($field, $data): void
    {
        $I = $this->I;
        $map = [
            'Title' => '#ls_doc_title',
            'Creator' => '#ls_doc_creator',
            'Official URI' => '#ls_doc_officialUri',
            'Publisher' => '#ls_doc_publisher',
            'Version' => '#ls_doc_version',
            'Description' => '#ls_doc_description',
            'Language' => 'ls_doc[language]',
            'Adoption Status' => 'ls_doc[adoptionStatus]',
            'Note' => '#ls_doc_note',
        ];
        $dataMap = [
            'Title' => 'title',
            'Creator' => 'creator',
            'Official URI' => 'officialUri',
            'Publisher' => 'publisher',
            'Version' => 'version',
            'Description' => 'description',
            'Language' => 'language',
            'Adoption Status' => 'adoptionStatus',
            'Note' => 'note',
        ];

        if (in_array($field, ['Language', 'Adoption Status'])) {
            $I->selectOption($map[$field], ['value' => $data]);
        } else {
            $I->fillField($map[$field], $data);
        }

        $this->frameworkData[$dataMap[$field]] = $data;
    }

    public function iGoToTheFrameworkDocument(): void
    {
        $I = $this->I;

        $I->amOnPage(self::$docPath.$I->getDocId());
    }

    /**
     * @Given /^I edit the fields in a framework$/
     */
    public function iEditTheFieldsInFramework(TableNode $table): Framework
    {
        $I = $this->I;

        $this->iGoToTheFrameworkDocument();
        $I->waitForElementVisible('//*[@id="documentOptions"]/button[@data-bs-target="#editDocModal"]');
        $I->click('//*[@id="documentOptions"]/button[@data-bs-target="#editDocModal"]');
        $I->waitForElementVisible('#ls_doc_title');

        $rows = $table->getRows();
        foreach ($rows as $row) {
            $this->iEditTheFieldInFramework($row[0], $row[1]);
        }

        $I->click('//*[@id="editDocModal"]//button[text()="Save Changes"]');

        return $this;
    }

    /**
     * @Given /^I upload an excel file$/
     */
    public function iUploadAnExcelFile(): void
    {
        $I = $this->I;

        $I->waitForElementVisible('#excel-url');

        $I->attachFile('input#excel-url', str_replace(codecept_data_dir(), '', 'spreadsheet_import_sample.xlsx'));
        $I->click('.btn-import-spreadsheet');
        $I->waitForElementNotVisible('#wizard', 60);

        $this->creatorName = 'ImportSpreadsheet';
        $this->rememberedFramework = 'SampleFramework';

        $I->see('ImportSpreadsheet', '.fancytree-title');
    }

    /**
     * @Given /^I upload the adopted CASE file$/
     */
    public function iUploadTheAdoptedCASEFile(): Framework
    {
        $I = $this->I;

        $I->waitForElementVisible('#file-url');

        $data = file_get_contents(codecept_data_dir().'AdoptedTestFramework.json');

        $name = sq('AdoptedFramework');
        $docUuid = Uuid::uuid4()->toString();
        $this->rememberedFramework = $name;

        $origValues = [
            'Adopted Test',
            'd0000000-0000-0000-0000-000000000000',
        ];
        $replacements = [
            $name,
            $docUuid,
        ];

        $decoded = json_decode($data, true);
        foreach ($decoded['CFItems'] as $item) {
            $origValues[] = $item['identifier'];
            $replacements[] = Uuid::uuid4()->toString();
        }
        foreach ($decoded['CFAssociations'] as $item) {
            $origValues[] = $item['identifier'];
            $replacements[] = Uuid::uuid4()->toString();
        }

        /*
        foreach ($origValues as $i => $origValue) {
            $data = mb_ereg_replace("/{$origValue}/", $replacements[$i], $data);
        }
         */
        $data = str_replace($origValues, $replacements, $data);

        $this->uploadedFramework = $data;

        $filename = tempnam(codecept_data_dir(), 'tmp_mdf_');
        unlink($filename);
        file_put_contents($filename.'.json', $data);

        $I->attachFile('input#file-url', str_replace(codecept_data_dir(), '', $filename.'.json'));
        $I->click('a.btn-import-case');
        $I->waitForElementNotVisible('#wizard', 60);

        unlink($filename.'.json');

        return $this;
    }

    /**
     * @When /^I click the first item in the framework$/
     */
    public function iClickTheFirstItemInTheFramework(): Framework
    {
        $I = $this->I;

        $I->click('//div[@id="viewmode_tree1"]/ul/li/ul/li[1]');

        return $this;
    }

    /**
     * @Then /^I should see "([^"]*)" as the first item in the tree's HCS value$/
     */
    public function iShouldSeeAsTheFirstItem(string $hcsValue): void
    {
        $I = $this->I;

        $level1HcsList = $I->grabMultiple('.item-humanCodingScheme');

        $I->assertEquals($hcsValue, current($level1HcsList));
    }

    /**
     * @Then /^I search for "([^"]*)" in the framework$/
     */
    public function iSearchForInTheFramework($item): void
    {
        $I = $this->I;

        $I->fillField('#filterOnTree', $item);
        // Chrome seems to require an explicit "keyup" to trigger the searching
        $I->executeJS("$('#filterOnTree').trigger('keyup');");
        $I->wait(1); // search has a 500ms delay to allow typing
    }

    /**
     * @Given /^I should not see "([^"]*)" in results$/
     */
    public function iShouldNotSeeInSearchResults(string $item): void
    {
        $I = $this->I;

        for ($i = 0; $i < 4; ++$i) {
            try {
                $I->dontSee($item, '#tree1Section');

                return;
            } catch (ExpectationFailedException $e) {
                // Try triggering the search again
                $I->executeJS("$('#filterOnTree').trigger('keyup');");
                $I->wait(1.5); // search has a 500ms delay to allow typing
            }
        }

        $I->dontSee($item, '#tree1Section');
    }

    /**
     * @Given /^I edit the fields in a framework without saving the changes$/
     */
    public function iEditTheFieldsInAFrameworkWithoutSavingTheChanges(TableNode $table): Framework
    {
        $I = $this->I;

        $this->iGoToTheFrameworkDocument();
        $I->waitForElementVisible('//*[@id="documentOptions"]/button[@data-bs-target="#editDocModal"]');
        $I->click('//*[@id="documentOptions"]/button[@data-bs-target="#editDocModal"]');
        $I->waitForElementVisible('#ls_doc_title');

        $rows = $table->getRows();
        foreach ($rows as $row) {
            $this->iEditTheFieldInFramework($row[0], $row[1]);
        }

        return $this;
    }

    /**
     * @Given /^I see the Log View button in the title section$/
     */
    public function iSeeTheSelectorInTitleSection(): void
    {
        $this->I->seeElement('#displayLogBtn');
    }

    /**
     * @Given /^I upload the Item Type CASE file$/
     */
    public function iUploadTheItemTypeCASEFile(): Framework
    {
        $I = $this->I;

        $I->waitForElementVisible('#file-url');

        $data = file_get_contents(codecept_data_dir().'ItemTypeTestFramework.json');

        $name = sq('ItemTypeFramework');
        $docUuid = Uuid::uuid4()->toString();
        $this->rememberedFramework = $name;

        $origValues = [];
        $replacements = [];

        $decoded = json_decode($data, true);

        // Replace title
        $origValues[] = $decoded['CFDocument']['title'];
        $replacements[] = $name;

        // replace document identifier
        $origValues[] = $decoded['CFDocument']['identifier'];
        $replacements[] = $docUuid;

        if (!empty($decoded['CFItems'])) {
            foreach ($decoded['CFItems'] as $item) {
                $origValues[] = $item['identifier'];
                $replacements[] = Uuid::uuid4()->toString();
            }
        }
        if (!empty($decoded['CFAssociations'])) {
            foreach ($decoded['CFAssociations'] as $item) {
                $origValues[] = $item['identifier'];
                $replacements[] = Uuid::uuid4()->toString();
            }
        }
        if (!empty($decoded['CFDefinitions'])) {
            foreach ($decoded['CFDefinitions'] as $content) {
                foreach ($content as $item) {
                    $origValues[] = $item['identifier'];
                    $replacements[] = Uuid::uuid4()->toString();
                }
            }
        }

        /*
        foreach ($origValues as $i => $origValue) {
            $data = mb_ereg_replace("/{$origValue}/", $replacements[$i], $data);
        }
        */
        $data = str_replace($origValues, $replacements, $data);

        $this->uploadedFramework = $data;

        $filename = tempnam(codecept_data_dir(), 'tmp_itf_');
        unlink($filename);
        file_put_contents($filename.'.json', $data);

        $I->attachFile('input#file-url', str_replace(codecept_data_dir(), '', $filename.'.json'));
        $I->click('a.btn-import-case');
        $I->waitForElementNotVisible('#wizard', 60);

        unlink($filename.'.json');

        return $this;
    }

    /**
     * @When /^I download the framework PDF file$/
     */
    public function iDownloadTheFrameworkPdfFile(): Framework
    {
        $I = $this->I;

        $I->click('Export', '#itemInfo');
        $I->waitForElementVisible('#exportModal a.btn-export-pdf');
        $I->click('#exportModal a.btn-export-pdf');
        $url = $I->grabAttributeFrom('#exportModal a.btn-export-pdf', 'href');
        $this->filename = $I->download($url);

        return $this;
    }

    /**
     * @Then /^I should see content in the PDF file$/
     */
    public function iShouldSeeContentInThePDFFile(): Framework
    {
        $I = $this->I;

        $PdfContent = $I->pdf2text($this->filename);
        $I->assertNotEmpty($PdfContent, 'PDF file is empty');
        $I->assertStringContainsString($I->getLastFrameworkTitle(), $PdfContent, 'Exported PDF does not have framework');
        return $this;
    }

    /**
     * @Then /^I update the framework via spreadsheet$/
     */
    public function updateFrameworkSpreadsheet(): void
    {
        $I = $this->I;

        $filename = str_replace(codecept_output_dir(), '', $this->filename);
        rename($this->filename, codecept_data_dir().$filename.'.xlsx');

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile(codecept_data_dir().''.$filename.'.xlsx');
        $ss = $reader->load(codecept_data_dir().$filename.'.xlsx');

        $sheet = $ss->getSheetByName('CF Doc');
        $sheet->setCellValue('B2', 'Test');
        $sheet->setCellValue('C2', 'Framework updated');

        $sheet = $ss->getSheetByName('CF Item');
        $sheet->setCellValue('B3', 'Item updated'); // Change A.B
        $sheet->setCellValue('C3', 'T');
        $sheet->setCellValue('F3', '');

        $sheet->setCellValue('B4', 'New full statement'); // Change A.B.C
        $sheet->setCellValue('C4', 'U');
        $sheet->setCellValue('F4', '');

        // Leave A.B.C.L alone

//        $sheet->removeRow(6); // remove A.B.D
        // Remove A.B.D
        $sheet->setCellValue('A6', null);
        $sheet->setCellValue('B6', null);
        $sheet->setCellValue('C6', null);
        $sheet->setCellValue('D6', null);
        $sheet->setCellValue('E6', null);
        $sheet->setCellValue('F6', null);
        $sheet->setCellValue('G6', null);
        $sheet->setCellValue('H6', null);
        $sheet->setCellValue('I6', null);
        $sheet->setCellValue('J6', null);
        $sheet->setCellValue('K6', null);

        $writer = \PHPOffice\PhpSpreadsheet\IOFactory::createWriter($ss, 'Xlsx');
        $writer->save(codecept_data_dir().$filename.'.mod.xlsx');

        $I->amOnPage(self::$docPath.$I->getDocId());
        $I->waitForElementVisible('//*[@id="documentOptions"]/button[@data-bs-target="#updateFrameworkModal"]', 120);
        $I->see('Update Framework');
        try {
            $I->click('Update Framework');
            $I->waitForElementVisible('#updateFrameworkModal', 10);
        } catch (\Exception $e) {
            $I->click('Update Framework');
            $I->waitForElementVisible('#updateFrameworkModal', 20);
        }
        $I->see('Import Spreadsheet file');
        $I->attachFile('input#excel-url', $filename.'.mod.xlsx');
        $I->click('Import Framework');
        $I->waitForElementNotVisible('#updateFrameworkModal', 60);
        try {
            $I->waitForElementVisible('#modalSpinner', 10);
        } catch (\Exception $e) {
            // Might have been too quick
        }
        $I->waitForElementNotVisible('#modalSpinner', 60);
        $I->waitForJS('return (("undefined" === typeof $) ? 1 : $.active) === 0;', 30);
        $I->waitForJS('return (("undefined" === typeof $) ? 1 : 0) === 0 && $("#tree1Section div.treeDiv ul").length > 0;', 10);
        $I->executeJS("$('#tree1Section div.treeDiv').fancytree('getTree').visit(function(n){n.setExpanded(true);});");
        $I->see('Framework updated');
        $I->dontSee('A.B abc'); // Changed to T ...
        $I->see('T Item updated');
        $I->dontSee('A.B.C def'); // Changed to U ...
        $I->see('U New full statement');
        $I->dontSee('A.B.D ghi'); // Removed;
        $I->see('A.B.C.L jkl'); // Left alone

        unlink(codecept_data_dir().$filename.'.xlsx');
        unlink(codecept_data_dir().$filename.'.mod.xlsx');
    }

    /**
     * @Then /^I add custom fields via spreadsheet$/
     */
    public function spreadsheetCustomFields(): void
    {
        $I = $this->I;

        //$data = file_get_contents(codecept_data_dir().'Utf8TestFramework.json');
        $filename = str_replace(codecept_output_dir(), '', $this->filename);
        rename($this->filename, codecept_data_dir().''.$filename.'.xlsx');

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile(codecept_data_dir().''.$filename.'.xlsx');
        $ss = $reader->load(codecept_data_dir().''.$filename.'.xlsx');

        $sheet = $ss->getSheetByName('CF Item');
        $sheet->setCellValue('M2', 'spreadsheet_custom_field');
        $sheet->setCellValue('B2', 'item custom field'); // change name to find it more easy in the UI
        $sheet->setCellValue('C2', ''); //remove the humanCodingScheme to find the item by the full statement
        $sheet->setCellValue('F2', ''); //remove the abbreviatedStatement to find the item by the full statement

        $writer = \PHPOffice\PhpSpreadsheet\IOFactory::createWriter($ss, 'Xlsx');
        $writer->save(codecept_data_dir().''.$filename.'.xlsx');

        $I->amOnPage(self::$docPath.$I->getDocId());
        $I->waitForElementVisible('//*[@id="documentOptions"]/button[@data-bs-target="#updateFrameworkModal"]', 120);
        $I->see('Update Framework');

        try {
            $I->click('Update Framework');
            $I->waitForElementVisible('#updateFrameworkModal', 10);
        } catch (\Exception $e) {
            $I->click('Update Framework');
            $I->waitForElementVisible('#updateFrameworkModal', 20);
        }

        $I->see('Import Spreadsheet file');
        $I->attachFile('input#excel-url', $filename.'.xlsx');
        $I->click('Import Framework');
        $I->waitForElementNotVisible('#updateFrameworkModal', 60);

        try {
            $I->waitForElementVisible('#modalSpinner', 10);
        } catch (\Exception $e) {
            // Might have been too quick
        }

        $I->waitForElementNotVisible('#modalSpinner', 60);
        $I->waitForJS('return (("undefined" === typeof $) ? 1 : $.active) === 0;', 30);
        $I->waitForJS('return (("undefined" === typeof $) ? 1 : 0) === 0 && $("#tree1Section div.treeDiv ul").length > 0;', 10);
        $I->executeJS("$('#tree1Section div.treeDiv').fancytree('getTree').visit(function(n){n.setExpanded(true);});");

        $I->see('item custom field');
        $I->executeJS("$('.fancytree-title').click()");
        $I->waitForJS('return (("undefined" === typeof $) ? 1 : $.active) === 0;', 30);
        $I->click('More Info');
        $I->see('test_additionalfield');
        $I->see('spreadsheet_custom_field');
    }

    /**
     * @When /^I fetch a list of creators$/
     */
    public function iFetchAListOfCreators(): void
    {
        $I = $this->I;
        $I->haveHttpHeader('Accept', 'application/json');
        $I->sendGET(static::$creatorsPath);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
    }

    /**
     * @Then /^the remembered creator will be in the list$/
     */
    public function theRememberedCreatorWillBeInTheList(): void
    {
        $this->I->seeResponseContainsJson([$this->rememberedCreator]);
    }

    /**
     * @When /^I fetch a list of frameworks by the remembered creator$/
     */
    public function iFetchAListOfFrameworksByTheRememberedCreator(): void
    {
        $I = $this->I;
        $I->haveHttpHeader('Accept', 'application/json');
        $I->sendGET(static::$frameworksByCreatorPath.rawurlencode($this->rememberedCreator));
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
    }

    /**
     * @Then /^the created framework will be in the list$/
     */
    public function theCreatedFrameworkWillBeInTheList(): void
    {
        $this->I->seeResponseContainsJson(['title' => $this->rememberedFramework]);
    }

    /* VISUALIZATION FEATURE */

    /**
     * @Then /^I should not see the visualization view button$/
     */
    public function iShouldNotSeeTheVisualizationViewButton(): Framework
    {
        $this->I->dontSee('#displayVisualizationBtn');
        return $this;
    }

    /**
     * @Then /^I should see the visualization view button$/
     */
    public function iShouldSeeTheVisualizationViewButton(): Framework
    {
        $this->I->seeElement('#displayVisualizationBtn');
        return $this;
    }

    /**
     * @Then /^I create the custom field "([^"]*)"$/
     */
    public function iCreateTheCustomField($additionalField): void
    {
        $I = $this->I;

        $I->amGoingTo('ensure the additional field does not already exist');
        try {
            $this->iDeleteACustomField($additionalField);
            $I->comment('The old additional field has been removed.');
        } catch (\Exception $e) {
            $I->comment('The additional field did not already exist');
        }

        $I->amOnPage(self::$additionalFieldPath);

        $I->see('Add Additional Field');
        $I->click('Add Additional Field');

        $I->fillField('#additional_field_name', $additionalField);
        $I->fillField('#additional_field_displayName', $additionalField);
        $I->selectOption('#additional_field_appliesTo', 'LsItem');
        $I->click('#additional_field_save');

        $I->see($additionalField, Locator::combine('//table/tbody/tr', -1));
    }

    /**
     * @Then /^I delete the custom field "([^"]*)"$/
     */
    public function iDeleteACustomField($additionalField): void
    {
        $I = $this->I;

        $I->amOnPage(self::$additionalFieldPath);
        $I->see($additionalField);

        $I->click('Delete', '//table/tbody/tr[td[4][text()="'.$additionalField.'"]]');
        $I->dontSee($additionalField);
    }

    /**
     * @Then /^I should see the framework created with the spreadsheet data$/
     */
    public function importFrameworkSpreadsheet(): void
    {
        $I = $this->I;

        $I->see($this->rememberedFramework);
        $I->executeJS("$('#tree1Section div.treeDiv').fancytree('getTree').visit(function(n){n.setExpanded(true);});");

        $I->see('S Statement 1');
        $I->see('S.1 Statement 2');
        $I->see('S.2 Statement 3');
        $I->see('S.2.1 Statement 4');

        $I->click('#displayAssocBtn');
        $I->checkOption('#assocViewTable_length .assocViewTableTypeFilters .avTypeFilter input[value="Is Child Of"]');
        $I->checkOption('#assocViewTable_length .assocViewTableTypeFilters .avTypeFilter input[value="Exact Match Of"]');
        $I->checkOption('#assocViewTable_length .assocViewTableTypeFilters .avTypeFilter input[value="Exemplar"]');
        $I->checkOption('#assocViewTable_length .assocViewTableTypeFilters .avTypeFilter input[value="Is Related To"]');
        $I->checkOption('#assocViewTable_length .assocViewTableTypeFilters .avTypeFilter input[value="Precedes"]');
        $I->checkOption('#assocViewTable_length .assocViewTableTypeFilters .avTypeFilter input[value="Is Peer Of"]');
        $I->checkOption('#assocViewTable_length .assocViewTableTypeFilters .avTypeFilter input[value="Is Part Of"]');
        $I->checkOption('#assocViewTable_length .assocViewTableTypeFilters .avTypeFilter input[value="Has Skill Level"]');
        $I->checkOption('#assocViewTable_length .assocViewTableTypeFilters .avTypeFilter input[value="Replaced By"]');

        $I->see('Is Child Of', '//table[@id="assocViewTable"]');
        $I->see('Exact Match Of', '//table[@id="assocViewTable"]');
        $I->see('Precedes', '//table[@id="assocViewTable"]');
        $I->see('Is Peer Of', '//table[@id="assocViewTable"]');
        $I->see('Is Part Of', '//table[@id="assocViewTable"]');
        $I->see('Has Skill Level', '//table[@id="assocViewTable"]');
        $I->see('Replaced By', '//table[@id="assocViewTable"]');
    }

    /**
     * @Given /^I visit the uploaded framework$/
     */
    public function iVisitTheUploadedFramework(): Framework
    {
        $I = $this->I;
        $creatorName = $this->creatorName;

        $I->iAmOnTheHomepage();
        $I->click("//span[text()='{$creatorName}']/../..");

        $frameworkName = $this->rememberedFramework;
        $I->waitForElementVisible("//span[text()='{$frameworkName}']");
        $I->click("//span[text()='{$frameworkName}']/../..");

        $I->waitForElementNotVisible('#modalSpinner', 120);
        $I->waitForElementVisible('#itemSection h4.itemTitle', 120);

        $I->rememberDocIdFromUrl();

        return $this;
    }
}
