<?php

namespace Page;

use Behat\Behat\Context\Context;
use Facebook\WebDriver\WebDriverElement;

class Notification implements Context
{
    /**
     * @var \AcceptanceTester
     */
    protected $I;

    public function __construct(\AcceptanceTester $I)
    {
        $this->I = $I;
    }

    /**
     * @Given /^I select the document$/
     */
    public function iSelectTheDocument(): void
    {
        $I = $this->I;

        $admin = $I->haveFriend('new user');
        $admin->does(
            function (\AcceptanceTester $I) {
                $framework = new Framework($I);
                $framework->iGoToTheFrameworkDocument();
                $I->see('Item Details');
            }
        );
    }

    /**
     * @Given /^I select the item$/
     */
    public function iSelectTheItem(): void
    {
        $I = $this->I;

        $admin = $I->haveFriend('new user');
        $admin->does(
            function (\AcceptanceTester $I) {
                $item = new Item($I);
                $item->iAmOnAnItemPage();
                $I->see('Item Details');
            }
        );
    }

    /**
     * @Then /^I see the Document buttons disabled$/
     */
    public function iSeeTheDocumentButtonsDisabled(): void
    {
        $I = $this->I;

        $admin = $I->haveFriend('new user');
        $admin->does(
            function (\AcceptanceTester $I) {
                $I->waitForElementChange('button[data-bs-target="#editDocModal"]', function (WebDriverElement $el) {
                    return false !== strpos($el->getAttribute('class'), 'disabled');
                }, 30);
                $I->see('Edit', '.disabled');
                $I->see('Manage Association Groups', '.disabled');
                $I->see('Add New Child Item', '.disabled');
                $I->see('Import Children', '.disabled');
                $I->see('Update Framework', '.disabled');
            }
        );
    }

    /**
     * @Then /^I see a notification of editing "([^"]*)"$/
     */
    public function iSeeANotificationOfEditing(string $type): void
    {
        $I = $this->I;

        $admin = $I->haveFriend('new user');
        $admin->does(
            function (\AcceptanceTester $I) use ($type) {
                $I->see($type, '.alert-info'); //TODO: need to get the name of the Document/Item to check in the message
            }
        );
    }

    /**
     * @Then /^I see a notification New "([^"]*)"$/
     */
    public function iSeeANotificationOfNew(string $type): void
    {
        $I = $this->I;

        $admin = $I->haveFriend('new user');
        $admin->does(
            function (\AcceptanceTester $I) use ($type) {
                $I->see($type, '.alert-info'); //TODO: need to get the name of the Document/Item to check in the message
            }
        );
    }

    /**
     * @Then /^I see a notification modified "([^"]*)"$/
     */
    public function iSeeANotificationOfModified(string $type): void
    {
        $I = $this->I;

        $admin = $I->haveFriend('new user');
        $admin->does(
            function (\AcceptanceTester $I) use ($type) {
                // Look for notification, might take a few seconds to show up
                for ($i = 0; $i < 32; ++$i) {
                    try {
                        $I->see($type, '.alert-info');

                        return;
                    } catch (\Exception $e) {
                        $I->wait(1);
                    }
                }

                $I->see($type, '.alert-info');
            }
        );
    }

    /**
     * @Given /^I see the Document buttons enabled$/
     */
    public function iSeeTheDocumentButtonsEnabled(): void
    {
        $I = $this->I;

        $admin = $I->haveFriend('new user');
        $admin->does(
            function (\AcceptanceTester $I) {
                $I->waitForElementChange('button[data-bs-target="#editDocModal"]', function (WebDriverElement $el) {
                    return false === strpos($el->getAttribute('class'), 'disabled');
                }, 30);
                $I->dontSee('Edit', '.disabled');
                $I->dontSee('Manage Association Groups', '.disabled');
                $I->dontSee('Add New Child Item', '.disabled');
                $I->dontSee('Import Children', '.disabled');
                $I->dontSee('Update Framework', '.disabled');
            }
        );
    }

    /**
     * @Given /^I see the Item buttons disabled$/
     */
    public function iSeeTheItemButtonsDisabled(): void
    {
        $I = $this->I;

        $admin = $I->haveFriend('new user');
        $admin->does(
            function (\AcceptanceTester $I) {
                $I->waitForElementChange('button[data-bs-target="#editItemModal"]', function (WebDriverElement $el) {
                    return false !== strpos($el->getAttribute('class'), 'disabled');
                }, 30);
                $I->see('Edit', '.disabled');
                $I->see('Delete', '.disabled');
                $I->see('Make This Item a Parent', '.disabled');
                $I->see('Add an Exemplar', '.disabled');
            }
        );
    }

    /**
     * @Given /^I see the Item buttons enabled$/
     */
    public function iSeeTheItemButtonsEnabled(): void
    {
        $I = $this->I;

        $admin = $I->haveFriend('new user');
        $admin->does(
            function (\AcceptanceTester $I) {
                $I->waitForElementChange('button[data-bs-target="#editItemModal"]', function (WebDriverElement $el) {
                    return false === strpos($el->getAttribute('class'), 'disabled');
                }, 30);
                $I->dontSee('Edit', '.disabled');
                $I->dontSee('Delete', '.disabled');
                $I->dontSee('Make This Item a Parent', '.disabled');
                $I->dontSee('Add an Exemplar', '.disabled');
            }
        );
    }
}
