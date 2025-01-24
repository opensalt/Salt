<?php

namespace Page;

use Behat\Behat\Context\Context;

class SystemLogs implements Context
{
    public const SYSTEM_LOGS_PAGE = '/admin/system_log';
    public const SYSTEM_LOGS_COUNT = '/admin/system_log/revisions/count';

    /**
     * @var \AcceptanceTester
     */
    protected $I;
    protected $filename;

    public function __construct(\AcceptanceTester $I)
    {
        $this->I = $I;
    }

    /**
     * @Then /^I go to the system logs$/
     */
    public function iGoToSystemLogs()
    {
        $this->I->amOnPage(self::SYSTEM_LOGS_PAGE);

        $this->I->waitForJS('return $.active == 0', 30);
        $this->I->waitForElementVisible(['xpath' => '//*[@id="systemLogTable"]/tbody/tr[1]/td[2]'], 30);
    }

    /**
     * @When /^I select "([^"]*)" from the menu dropdown$/
     */
    public function iSelectFromTheMenuDropdown($menuOption)
    {
        $I = $this->I;

        $I->click('header .navbar .dropdown-toggle');
        $I->click($menuOption);
    }

    /**
     * @Then /^I see "([^"]*)" in the title section$/
     */
    public function iSeeInTheTitleSection($text)
    {
        $this->I->see($text, 'main > h1');
    }

    /**
     * @Given /^I see all of the data table elements for the system logs$/
     */
    public function iSeeAllOfTheDataTableElements()
    {
        $I = $this->I;

        $I->see('Date', 'th');
        $I->see('Change', 'th');
        $I->see('User', 'th');
    }

    /**
     * @Then /^I should see the add of the user in the log$/
     */
    public function iShouldSeeTheAddOfTheUserInTheLog()
    {
        $this->I->see($this->I->getRememberedString('lastNewUsername').'" added');
    }

    /**
     * @Given /^I should see the update of the user in the log$/
     */
    public function iShouldSeeTheUpdateOfTheUserInTheLog()
    {
        $this->I->see($this->I->getRememberedString('lastChangedUsername').'" modified');
    }

    /**
     * @Given /^I should see the delete of the user in the log$/
     */
    public function iShouldSeeTheDeleteOfTheUserInTheLog()
    {
        $this->I->see($this->I->getRememberedString('lastDeletedUsername').'" deleted');
    }

    /**
     * @Given /^I should see the add of the organization in the log$/
     */
    public function iShouldSeeTheAddOfTheOrganizationInTheLog()
    {
        $this->I->see($this->I->getRememberedString('lastNewOrg').'" added');
    }

    /**
     * @Given /^I should see the update of the organization in the log$/
     */
    public function iShouldSeeTheUpdateOfTheOrganizationInTheLog()
    {
        $this->I->see($this->I->getRememberedString('lastChangedOrg').'" modified');
    }

    /**
     * @Given /^I should see the delete of the organization in the log$/
     */
    public function iShouldSeeTheDeleteOfTheOrganizationInTheLog()
    {
        $this->I->see($this->I->getRememberedString('lastDeletedOrg').'" deleted');
    }

    /**
     * @Then /^I do not see "([^"]*)" in the menu dropdown$/
     */
    public function iDoNotSeeInTheMenuDropdown($menuOption)
    {
        $I = $this->I;

        $I->click('header .navbar .dropdown-toggle');
        $I->dontSee($menuOption);
    }

    /**
     * @Given /^I enter "([^"]*)" in the sytem log search field$/
     */
    public function iEnterInTheSytemLogSearchField($term)
    {
        $this->I->fillField('#systemLogTable_filter input', $term);
    }

    /**
     * @Then /^I should only see rows with "([^"]*)" in the system log data table$/
     */
    public function iShouldOnlySeeRowsWithInTheSystemLogDataTable($term)
    {
        $rows = $this->I->grabMultiple("//*[@id='systemLogTable']/tbody/tr");

        foreach ($rows as $row) {
            $this->I->assertStringContainsString($term, $row);
        }
    }

    /**
     * @Given /^I download the system log export CSV$/
     */
    public function iDownloadTheSystemLogExportCSV()
    {
        $I = $this->I;

        $url = $I->grabAttributeFrom('#systemLogView a.btn-export-csv', 'href');

        $this->filename = $I->download($url);
    }

    /**
     * @Then /^I can see the data in the CSV matches the data in the system log$/
     */
    public function iCanSeeTheDataInTheCSVMatchesTheDataInTheSystemLog()
    {
        $export = file_get_contents($this->filename);

        $rows = $this->I->grabMultiple("//*[@id='systemLogTable']/tbody/tr/td[1]");
        foreach ($rows as $row) {
            $this->I->assertStringContainsString($row, $export);
        }
    }

    /**
     * @Then /^I should see (\d+) rows in the system log data table$/
     */
    public function iShouldSeeRowsInTheSystemLogDataTable($count)
    {
        $rows = $this->I->grabMultiple("//*[@id='systemLogTable']/tbody/tr");
        $this->I->assertEquals((int)$count, count($rows));
    }

    /**
     * @Given /^the system log has at least (\d+) changes$/
     */
    public function theSystemLogHasAtLeastChanges($changeCount)
    {
        $I = $this->I;

        $hasCount = $I->fetch(static::SYSTEM_LOGS_COUNT);

        if ($hasCount < $changeCount) {
        }
    }
}
