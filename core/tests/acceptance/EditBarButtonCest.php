<?php

use Codeception\Scenario;
use Context\Login;

class EditBarButtonCest
{
    public static $itemPath = '/cftree/item/';

    public function seeAlphabeticalListButton(AcceptanceTester $Acpt, Scenario $scenario): void
    {
        $loginPage = new Login($Acpt);
        $loginPage->loginAsRole('super_user');
        $Acpt->getLastItemId();
        $Acpt->amOnPage(self::$itemPath.$Acpt->getItemId());
        $Acpt->waitForElementNotVisible('#modalSpinner', 120);
        $Acpt->see('Edit');
        $Acpt->click('[data-bs-target="#editItemModal"]');
        $Acpt->waitForElementVisible('#editItemModal');
        $Acpt->waitForElementVisible('#ls_item', 120);
        $Acpt->seeElement('.fa.fa-sort-alpha-asc');
    }
}
