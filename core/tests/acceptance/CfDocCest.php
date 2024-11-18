<?php

use Codeception\Scenario;
use Context\Login;

class CfDocCest
{
    public function editorCanSeeCreateImportButtons(AcceptanceTester $I, Scenario $scenario)
    {
        $loginPage = new Login($I, $scenario);
        $loginPage->loginAsRole('Editor');
        $I->amOnPage('/cfdoc');
        $I->clickWithLeftButton(['css' => 'header a.dropdown-toggle svg[aria-label="Main Menu"]']);
        $I->see('Add framework');
        $I->see('Import framework');
    }

    public function userCantSeeCreateImportButtons(AcceptanceTester $I, Scenario $scenario)
    {
        $loginPage = new Login($I, $scenario);
        $loginPage->loginAsRole('User');
        $I->amOnPage('/cfdoc');
        $I->dontSee('Add framework');
        $I->dontSee('Import framework');
    }
}
