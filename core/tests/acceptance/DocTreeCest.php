<?php

use Codeception\Scenario;
use Context\Login;
use Ramsey\Uuid\Uuid;

class DocTreeCest
{
    public static $docPath = '/cftree/doc/';

    // tests
    public function verifyOrder(AcceptanceTester $I, Scenario $scenario)
    {
        $loginPage = new Login($I, $scenario);
        $loginPage->loginAsRole('Admin');
        $I->amOnPage('/cfdoc');
        $I->see('Import framework');
        $I->clickWithLeftButton(['css' => 'header a.dropdown-toggle svg[aria-label="Main Menu"]']);
        $I->click('Import framework');
        $I->waitForElementVisible('.modal');
        $I->see('Import CASE® file');
        $I->click('//*[@data-bs-target="#case"]');

        $data = file_get_contents(codecept_data_dir().'Ordering.json');

        $name = sq('OrderingTestFramework');
        $docUuid = Uuid::uuid4()->toString();

        $origValues = [
            'ACT Holistic Framework, Math',
            'a33fc64e-5c40-11e7-82c4-3d54268aa9ee',
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

        $filename = tempnam(codecept_data_dir(), 'tmp_eef_');
        unlink($filename);
        file_put_contents($filename.'.json', $data);

        $I->attachFile('input#file-url', str_replace(codecept_data_dir(), '', $filename.'.json'));

        $I->click('.btn-import-case');
        $I->waitForJS('return ("function" === typeof $ && $.active == 0);', 30);

        $docId = $I->getFrameworkIdForIdentifier($docUuid);
        $I->amOnPage(self::$docPath.$docId);
        $I->waitForElementNotVisible('#modalSpinner', 120);
        $I->waitForElementVisible('#itemSection h4.itemTitle', 120);
        $I->see($name);
        $I->executeJS("$('#tree1Section div.treeDiv').fancytree('getTree').visit(function(n){n.setExpanded(true);});");
        $I->waitForJS('return $.active == 0;', 1);
        $css = $I->grabMultiple('.item-humanCodingScheme');
        $array = [];
        $expectedArray = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24];

        foreach ($css as $cs) {
            $tmp = explode('.', $cs);
            $tmp = end($tmp);
            if (is_numeric($tmp)) {
                $array[] = (int) $tmp;
            }
        }

        $I->assertEquals($array, $expectedArray);
    }
}
