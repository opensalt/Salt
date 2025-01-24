<?php

namespace Page;

use Behat\Behat\Context\Context;

class FrameworkLogs implements Context
{
    protected string $filename;

    protected \AcceptanceTester $I;

    public function __construct(\AcceptanceTester $I)
    {
        $this->I = $I;
    }

    /**
     * @Then /^I see the log table$/
     */
    public function iSeeTheLogTable()
    {
        $this->I->seeElement('table#logTable');
        $this->I->dontSeeElement('#treeView');
    }

    /**
     * @Given /^I see all of the data table elements$/
     */
    public function iSeeAllOfTheDataTableElements()
    {
        $I = $this->I;

        $I->see('Date', 'th');
        $I->see('Change', 'th');
        $I->see('Username', 'th');
    }

    /**
     * @Given /^I select Log View$/
     */
    public function iSelectLogView()
    {
        $this->I->waitForElementNotVisible('#modalSpinner');
        $this->I->click('#displayLogBtn');
        $this->I->waitForJS('return $.active == 0', 30);
        $this->I->waitForElementVisible(['xpath' => '//*[@id="logTable"]/tbody/tr[1]/td[2]'], 30);
    }

    /**
     * @Then /^I should see the add of "([^"]*)" in the log$/
     */
    public function iShouldSeeTheAddOfInTheLog($item)
    {
        $lookFor = $this->I->getRememberedString($item, $item);
        $this->I->see("\"{$lookFor}\" added", '#logTable td');
    }

    /**
     * @Then /^I should see the update of "([^"]*)" in the log$/
     */
    public function iShouldSeeItemUpdateInTheLog($item)
    {
        $this->I->see("\"{$item}\" modified", '#logTable td');
    }

    /**
     * @Given /^I should see the move of "([^"]*)" in the log$/
     */
    public function iShouldSeeTheMoveOfInTheLog($item)
    {
        $this->I->see("Framework tree updated", '#logTable td');
    }

    /**
     * @Given /^I enter "([^"]*)" in the log search field$/
     */
    public function iEnterInTheLogSearchField($term)
    {
        $this->I->fillField('#logTable_filter input', $term);
    }

    /**
     * @Then /^I should only see rows with "([^"]*)" in the log data table$/
     */
    public function iShouldOnlySeeRowsWithInTheLogDataTable($term)
    {
        $rows = $this->I->grabMultiple("//*[@id='logTable']/tbody/tr");

        foreach ($rows as $row) {
            $this->I->assertStringContainsString($term, $row);
        }
    }

    /**
     * @Given /^I select (\d+) from the "([^"]*)" dropdown$/
     */
    public function iSelectFromTheDropdown($count, $selectBox)
    {
        $this->I->selectOption('.dataTables_wrapper select', $count);
        $this->I->wait(1);
    }

    /**
     * @Then /^I should see (\d+) rows in the data table$/
     */
    public function iShouldSeeRowsInTheDataTable($count)
    {
        $rows = $this->I->grabMultiple("//*[@id='logTable']/tbody/tr");
        $this->I->assertEquals((int)$count, count($rows));
    }

    /**
     * @Given /^I download the framework revision export CSV$/
     */
    public function iDownloadTheCSV()
    {
        $I = $this->I;

        $url = $I->grabAttributeFrom('#logView a.btn-export-csv', 'href');

        $this->filename = $I->download($url);
    }

    /**
     * @Then /^I can see the data in the CSV matches the data in the framework log$/
     */
    public function iCanSeeTheDataInTheCSVMatchesTheDataInTheFrameworkLog()
    {
        $export = file_get_contents($this->filename);

        $rows = $this->I->grabMultiple("//*[@id='logTable']/tbody/tr/td[1]");
        foreach ($rows as $row) {
            $this->I->assertStringContainsString($row, $export);
        }
    }
}
