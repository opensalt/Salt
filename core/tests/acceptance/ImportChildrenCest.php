<?php

use Codeception\Scenario;
use Context\Login;
use Ramsey\Uuid\Uuid;

class ImportChildrenCest
{
    public static $docPath = '/cftree/doc/';

    public function importCSVSequenceNumber(AcceptanceTester $I, Scenario $scenario): void
    {
        $loginPage = new Login($I, $scenario);
        $loginPage->loginAsRole('super_user');
        $I->amOnPage(self::$docPath.$I->getDocId());
        $I->waitForElementNotVisible('#modalSpinner', 120);
        $I->waitForElementVisible('[data-bs-target="#addChildrenModal"]', 120);
        $I->see('Import Children');
        $I->click('Import Children');
        $I->waitForElementVisible('#addChildrenModal', 120);
        $I->see('Import Items');

        $data = file_get_contents(codecept_data_dir().'sequenceNumber.csv');

        $origValues = [];
        $replacements = [];

        $lines = explode(PHP_EOL, $data);
        foreach ($lines as $i => $line) {
            // skip header line
            if (0 === $i) {
                continue;
            }

            $decoded = str_getcsv($line);
            $origValues[] = $decoded[0];
            $replacements[] = Uuid::uuid4()->toString();
        }

        $data = str_replace($origValues, $replacements, $data);

        $filename = tempnam(codecept_data_dir(), 'tmp_eef_');
        unlink($filename);
        file_put_contents($filename.'.csv', $data);

        $I->attachFile('input#file-url', str_replace(codecept_data_dir(), '', $filename.'.csv'));
        $I->selectOption('#js-framework-to-association', ['value' => $I->getDocId()]);
        $I->click('.btn-import-csv');
        $I->waitForJS('return (("undefined" === typeof $) ? 1 : $.active) === 0;', 10);
        unlink($filename.'.csv');

        $I->amOnPage(self::$docPath.$I->getDocId());
        $I->waitForElementNotVisible('#modalSpinner', 120);
        $I->waitForElementVisible('#itemSection h4.itemTitle', 120);
        $I->executeJS("$('#tree1Section div.treeDiv').fancytree('getTree').visit(function(n){n.setExpanded(true);});");
        $I->waitForJS('return $.active == 0;', 1);
        $css = $I->grabTextFrom('.item-humanCodingScheme');
        $I->assertEquals($css, 'B');
    }

    public function abbreviatedStatementLongerThan60Chars(AcceptanceTester $I, Scenario $scenario): void
    {
        $loginPage = new Login($I, $scenario);
        $loginPage->loginAsRole('super_user');
        $I->amOnPage('/cfdoc/new');
        $framework = new \Page\Framework($I);
        $framework->iCreateAFramework('Import CSV Framework');

        $I->amOnPage(self::$docPath.$I->getDocId());
        $I->waitForElementNotVisible('#modalSpinner', 120);
        $I->waitForElementVisible('[data-bs-target="#addChildrenModal"]', 120);
        $I->see('Import Children');
        $I->click('Import Children');
        $I->waitForElementVisible('#addChildrenModal', 120);
        $I->see('Import Items');

        $I->attachFile('input#file-url', 'abbreviatedStatementLimit.csv');
        $I->selectOption('#js-framework-to-association', ['value' => $I->getDocId()]);
        $I->click('.btn-import-csv');
        $I->waitForJS('return (("undefined" === typeof $) ? 1 : $.active) === 0;', 10);
        $I->see('Abbreviated statement can not be longer than 60 characters.');
    }

    public function abbreviatedStatementShorterThan60Chars(AcceptanceTester $I, Scenario $scenario): void
    {
        $loginPage = new Login($I, $scenario);
        $loginPage->loginAsRole('super_user');
        $I->amOnPage('/cfdoc/new');
        $framework = new \Page\Framework($I);
        $framework->iCreateAFramework('Import CSV Framework');

        $I->amOnPage(self::$docPath.$I->getDocId());
        $I->waitForElementNotVisible('#modalSpinner', 120);
        $I->waitForElementVisible('[data-bs-target="#addChildrenModal"]', 120);
        $I->see('Import Children');
        $I->click('Import Children');
        $I->waitForElementVisible('#addChildrenModal', 120);
        $I->see('Import Items');

        $I->attachFile('input#file-url', 'abbreviatedStatement.csv');
        $I->selectOption('#js-framework-to-association', ['value' => $I->getDocId()]);
        $I->click('.btn-import-csv');
        $I->waitForJS('return (("undefined" === typeof $) ? 1 : $.active) === 0;', 10);

        $I->amOnPage(self::$docPath.$I->getDocId());
        $I->waitForElementNotVisible('#modalSpinner', 120);
        $I->waitForElementVisible('#itemSection h4.itemTitle', 120);
        $I->executeJS("$('#tree1Section div.treeDiv').fancytree('getTree').visit(function(n){n.setExpanded(true);});");
        $I->waitForJS('return $.active == 0;', 1);

        $css = $I->grabTextFrom('.item-humanCodingScheme');
        $I->assertEquals($css, 'M');
    }
}
