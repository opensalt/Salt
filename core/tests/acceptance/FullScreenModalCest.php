<?php

use Codeception\Scenario;
use Context\Login;

class FullScreenModalCest
{
    public static $docPath = '/cftree/doc/';

    public function seeEditFullScreen(AcceptanceTester $I, Scenario $scenario): void
    {
        $loginPage = new Login($I);
        $loginPage->loginAsRole('super_user');
        $I->amOnPage(self::$docPath.$I->getDocId());
        $I->waitForElementNotVisible('#modalSpinner', 120);
        $I->see('Edit');
        $I->click('[data-bs-target="#editDocModal"]');
        $I->waitForElementVisible('#editDocModal');
        $contentSize = $I->executeJS('return $("#editDocModal .modal-dialog.modal-xl").width()');
        $fullSize = $I->executeJS('return $("body").width()');
        $I->assertGreaterThan($fullSize * 0.98, $contentSize, 'Full Screen');
    }
}
