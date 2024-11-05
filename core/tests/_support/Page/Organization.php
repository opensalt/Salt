<?php

namespace Page;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;

class Organization implements Context
{
    public const ORG_ADMIN_PAGE = '/admin/organization/';

    /**
     * @var \AcceptanceTester
     */
    protected $I;
    protected $orgName;

    public function __construct(\AcceptanceTester $I)
    {
        $this->I = $I;
    }

    /**
     * @Then /^I add a Organization$/
     */
    public function iAddAOrganization()
    {
        $I = $this->I;
        /** @var \Faker\Generator $faker */
        $faker = \Faker\Factory::create();
        $name = str_replace("'", '', $faker->company());
        $this->orgName = $name;

        $I->click('.dropdown-toggle');
        $I->click('Manage organizations');
        $I->see('Organizations list', 'h1');
        $I->click('Add a new organization');
        $I->fillField('#salt_userbundle_organization_name', $name);
        $I->click('Add');
        $I->remember('lastNewOrg', $this->orgName);
    }

    /**
     * @Then /^I delete the Organization$/
     */
    public function iDeleteTheOrganization()
    {
        $I = $this->I;
        $org = $this->orgName;

        $I->amOnPage(self::ORG_ADMIN_PAGE);
        $I->click("//td[text()='{$org}']/..//a[text()='show']");
        $I->see($this->orgName);
        $I->click('Delete');
        $I->remember('lastDeletedOrg', $this->orgName);
    }

    /**
     * @Given /^I am on the Organizations list page$/
     */
    public function iAmOnTheOrganizationsListPage()
    {
        $I = $this->I;

        $I->amOnPage(self::ORG_ADMIN_PAGE);
        $I->see('Organizations list', 'h1');
    }

    /**
     * @Given /^I should be on the Organizations list page$/
     */
    public function iShouldBeOnTheOrganizationsListPage()
    {
        $I = $this->I;

        $I->seeInCurrentUrl(self::ORG_ADMIN_PAGE);
        $I->see('Organizations list', 'h1');
    }

    /**
     * @Then /^I should see the following:$/
     */
    public function iShouldSeeTheFollowing(TableNode $table)
    {
        $I = $this->I;

        $rows = $table->getRows();
        foreach ($rows as $row) {
            $I->see($row[0]);
        }
    }

    /**
     * @Then /^I should see the Organization$/
     */
    public function iShouldSeeTheOrganization()
    {
        $I = $this->I;

        $I->amOnPage(self::ORG_ADMIN_PAGE);
        $I->see($this->orgName);
    }

    /**
     * @Then /^I edit the name in Organization$/
     */
    public function iEditTheNameInOrganization(TableNode $table)
    {
        $I = $this->I;
        $org = $this->orgName;

        $I->amOnPage(self::ORG_ADMIN_PAGE);
        $I->click("//td[text()='{$org}']/..//a[text()='edit']");
        $I->seeInField('#salt_userbundle_organization_name', $this->orgName);
        $rows = $table->getRows();
        foreach ($rows as $row) {
            $I->fillField('#salt_userbundle_organization_name', $row[0]);
            $I->click('Save');
            $I->waitForText($row[0], 10);
            $I->see($row[0]);
            $this->orgName = $row[0];
        }
    }

    /**
     * @Then /^I change the name of the Organization$/
     */
    public function iChangeOrganizationName()
    {
        /** @var \Faker\Generator $faker */
        $faker = \Faker\Factory::create();
        $name = str_replace("'", '', $faker->company());
        $newOrgName = $name;

        $I = $this->I;
        $org = $this->orgName;

        $I->amOnPage(self::ORG_ADMIN_PAGE);
        $I->click("//td[text()='{$org}']/..//a[text()='edit']");
        $I->seeInField('#salt_userbundle_organization_name', $this->orgName);
        $I->fillField('#salt_userbundle_organization_name', $newOrgName);
        $I->click('Save');
        $I->waitForText($newOrgName, 10);
        $I->see($newOrgName);
        $this->orgName = $newOrgName;
        $I->remember('lastChangedOrg', $this->orgName);
    }

    /**
     * @Then /^I edit the new Organization$/
     */
    public function iEditTheNewOrganization()
    {
        $I = $this->I;

        $org = $this->orgName;
        $I->amOnPage(self::ORG_ADMIN_PAGE);
        $I->click("//td[text()='{$org}']/..//a[text()='edit']");
    }

    /**
     * @Then /^I show the new Organization$/
     */
    public function iShowTheNewOrganization()
    {
        $I = $this->I;

        $org = $this->orgName;
        $I->amOnPage(self::ORG_ADMIN_PAGE);
        $I->click("//td[text()='{$org}']/..//a[text()='show']");
    }
}
