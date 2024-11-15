<?php

namespace Page;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;

class User implements Context
{
    /**
     * @var \AcceptanceTester
     */
    protected $I;
    protected $userName;

    public function __construct(\AcceptanceTester $I)
    {
        $this->I = $I;
    }

    /**
     * @Then /^I add a new user with "([^"]*)" role$/
     */
    public function iAddANewUserWithRole(string $role): void
    {
        $I = $this->I;
        $roleMap = [
            'Super User' => '#salt_userbundle_user_roles_0',
            'Super Editor' => '#salt_userbundle_user_roles_1',
            'Organization Admin' => '#salt_userbundle_user_roles_2',
            'Editor' => '#salt_userbundle_user_roles_3',
        ];
        /** @var \Faker\Generator $faker */
        $faker = \Faker\Factory::create();
        $username = $faker->email();
        $password = $faker->password().'aB3';
        $this->userName = $username;

        $I->click('header .navbar .dropdown-toggle');
        $I->click('Manage user');
        $I->see('User list', 'h1');
        $I->click('Add a new user');
        $I->fillField('#salt_userbundle_user_username', $username);
        $I->fillField('#salt_userbundle_user_plainPassword', $password);
        $I->checkOption($roleMap[$role]);
        $I->selectOption('#salt_userbundle_user_org', ['value' => 1]);
        $I->click('Add');
        $I->waitForElementVisible('header .navbar .dropdown-toggle');
    }

    /**
     * @Then /^I add a new user$/
     */
    public function iAddANewUser(): void
    {
        $I = $this->I;

        /** @var \Faker\Generator $faker */
        $faker = \Faker\Factory::create();
        $username = $faker->email();
        $password = $faker->password().'aB3';
        $this->userName = $username;

        $I->click('header .navbar .dropdown-toggle');
        $I->click('Manage user');
        $I->see('User list', 'h1');
        $I->click('Add a new user');
        $I->fillField('#salt_userbundle_user_username', $username);
        $I->fillField('#salt_userbundle_user_plainPassword', $password);
        $I->checkOption('#salt_userbundle_user_roles_1');
        $I->click('Add');
        $I->remember('lastNewUsername', $username);
    }

    /**
     * @Then /^I create a new account$/
     */
    public function iCreateANewAccount(): void
    {
        $I = $this->I;

        /** @var \Faker\Generator $faker */
        $faker = \Faker\Factory::create();
        $username = $faker->email();
        $password = $faker->password().'aB3';
        $org = $faker->company();
        $this->userName = $username;

        $I->fillField('#signup_username', $username);
        $I->fillField('#signup_plainPassword_first', $password);
        $I->fillField('#signup_plainPassword_second', $password);
        $I->selectOption('#signup_org', 'other');
        $I->fillField('#signup_newOrg', $org);
        $I->click('Submit');
        $I->remember('lastNewUsername', $username);
    }

    /**
     * @Then /^I see last created account is pending$/
     */
    public function getLastCreatedAccount(): void
    {
        $I = $this->I;

        $username = $I->getRememberedString('lastNewUsername');
        $I->amOnPage('/admin/user');
        $I->click('th.sorting_asc');
        $I->seeElement("//td[text()='{$username}']/..//input[@value='Approve']");
        $I->seeElement("//td[text()='{$username}']/..//input[@value='Reject']");
    }

    /**
     * @Then /^I delete the User$/
     */
    public function iDeleteTheUser(): void
    {
        $I = $this->I;
        $username = $this->userName;
        if ('/admin/user/' !== $I->grabFromCurrentUrl()) {
            $I->amOnPage('/admin/user/');
        }
        try {
            $I->click('th.sorting_asc');
        } catch (\Exception $e) {
            // Ignore error if we are already sorting in descending order
        }
        $I->click("//td[text()='{$username}']/..//a[text()='show']");
        $I->see($username);
        $I->click('Delete');
        $I->waitForElementVisible('header .navbar .dropdown-toggle');
        $I->remember('lastDeletedUsername', $username);
    }

    /**
     * @Then /^I edit a user profile$/
     */
    public function iEditAUserProfile(TableNode $table): void
    {
        $I = $this->I;

        $username = $this->userName;
        $I->amOnPage('/admin/user/');
        $I->click('th.sorting_asc');
        $I->click("//td[text()='{$username}']/..//a[text()='edit']");
        $rows = $table->getRows();
        $row = current($rows); // Get first row, first element is new username
        $I->fillField('#salt_userbundle_user_username', $row[0]);
        $I->click('Save');
        try {
            $I->click('th.sorting_asc'); // reset sort to desc
        } catch (\Exception $e) {
            // Ignore error
        }
        $I->waitForText($row[0], 10);
        $I->see($row[0]);
        $this->userName = $row[0];
    }

    /**
     * @Then /^I change the user's email address$/
     */
    public function iChangeUserEmailAddress(): void
    {
        $I = $this->I;

        $faker = \Faker\Factory::create();
        $newUsername = $faker->email();

        $username = $this->userName;
        $I->amOnPage('/admin/user/');
        $I->click('th.sorting_asc');
        $I->click("//td[text()='{$username}']/..//a[text()='edit']");
        $I->fillField('#salt_userbundle_user_username', $newUsername);
        $I->click('Save');
        $I->click('th.sorting_asc');
        $I->waitForText($newUsername, 10);
        $I->see($newUsername);
        $this->userName = $newUsername;
        $I->remember('lastChangedUsername', $newUsername);
    }

    /**
     * @Then /^I suspend the user$/
     */
    public function iSuspendTheUser(): void
    {
        $I = $this->I;
        $username = $this->userName;

        $I->amOnPage('/admin/user/');
        $I->click('th.sorting_asc');
        $I->click("//td[text()='{$username}']/..//input[@value='Suspend']");
        $I->dontSee('Edit', "//td[text()='{$username}']/..//a[text()='edit']");
    }

    /**
     * @Then /^I reinstate the user$/
     */
    public function iReinstateTheUser(): void
    {
        $I = $this->I;
        $username = $this->userName;

        $I->amOnPage('/admin/user/');
        $I->click('th.sorting_asc');
        $I->click("//td[text()='{$username}']/..//input[@value='Unsuspend']");
        $I->click('th.sorting_asc');
        $I->see('Edit', "//td[text()='{$username}']/..//a[text()='edit']");
    }

    /**
     * @Then /^I view the user$/
     */
    public function iViewTheUser(): void
    {
        $I = $this->I;
        $username = $this->userName;

        $I->amOnPage('/admin/user/');
        $I->click('th.sorting_asc');
        $I->see($username);
    }

    /**
     * @Given /^I am on the User list page$/
     */
    public function iAmOnTheUserListPage(): void
    {
        $I = $this->I;

        $I->amOnPage('/admin/user/');
        $I->see('User list');
        $I->see('Id');
        $I->see('Organization');
        $I->see('Username');
        $I->see('Roles');
        $I->see('Actions');
    }

    /**
     * @Then /^I change my password$/
     */
    public function iChangeMyPassword(): void
    {
        $I = $this->I;
        $password = $this->I->getLastPassword();

        $I->amOnPage('/user/change-password');
        $I->see('Change Password');
        $I->fillField('#change_password_oldPassword', $password);
        $I->fillField('#change_password_newPassword_first', '123456');
        $I->fillField('#change_password_newPassword_second', '123456');
        $I->click('/html/body/div[1]/main/div[2]/div/div[2]/form/ul/li[1]/input');
        $I->see('Your password has been changed.');

        $I->amOnPage('/user/change-password');
        $I->see('Change Password');
        $I->fillField('#change_password_oldPassword', '123456');
        $I->fillField('#change_password_newPassword_first', $password);
        $I->fillField('#change_password_newPassword_second', $password);
        $I->click('/html/body/div[1]/main/div[2]/div/div[2]/form/ul/li[1]/input');
        $I->see('Your password has been changed.');
    }

    /**
     * @Then /^I edit the new user$/
     */
    public function iEditTheNewUser(): void
    {
        $I = $this->I;

        $username = $this->userName;
        $I->amOnPage('/admin/user/');
        try {
            $I->click('th.sorting_asc');
        } catch (\Exception $e) {
            // Ignore error if we are already sorting in descending order
        }
        $I->click("//td[text()='{$username}']/..//a[text()='edit']");
    }

    /**
     * @Then /^I show the new user$/
     */
    public function iShowTheNewUser(): void
    {
        $I = $this->I;

        $username = $this->userName;
        $I->amOnPage('/admin/user/');
        try {
            $I->click('th.sorting_asc');
        } catch (\Exception $e) {
            // Ignore error if we are already sorting in descending order
        }
        $I->click("//td[text()='{$username}']/..//a[text()='show']");
    }

    /**
     * @Then /^I approve the new user$/
     */
    public function iApproveTheNewUser(): void
    {
        $I = $this->I;

        $username = $this->userName;
        $I->amOnPage('/admin/user');
        $I->click('th.sorting_asc');
        $I->click("//td[text()='{$username}']/..//input[@value='Approve']");
    }

    /**
     * @Then /^I verify an email was sent$/
     */
    public function iVerifyEmailWasSent(): void
    {
        // check to see if the email feature is active
        if ('always-active' === getenv('USE_MAIL_FEATURE')) {
            $I = $this->I;

            $fromEmail = getenv('MAIL_FEATURE_FROM_EMAIL');
            if (null !== $fromEmail) {
                $I->fetchEmails();
                $I->haveEmails();
                $I->haveUnreadEmails();
                $I->openNextUnreadEmail();
                $I->seeInOpenedEmailSubject('Your account has been created');
                $I->seeInOpenedEmailBody('Thank you! Your account has been created and you will be contacted in 2 business days when it is active.');
            } else {
                $I->comment('MAIL_FEATURE disabled, email would not be sent.');
            }
        }
    }

    /**
     * @Then /^I search organization and role type$/
     */
    public function iSearchOrgAndRole(): void
    {
        $I = $this->I;
        $I->amOnPage('/admin/user/');
        $I->see('Organization');
        $organization = $I->grabTextFrom('//*[@id="datatable"]/tbody/tr[1]/td[2]');
        $I->fillField('#search_form_organization', $organization);
        $I->see($organization, '//*[@id="datatable"]/tbody/tr[1]/td[2]');
    }

    /**
     * @Then /^I reject the new user$/
     */
    public function isRejectTheNewUser(): void
    {
        $I = $this->I;

        $username = $I->getRememberedString('lastNewUsername');
        $I->amOnPage('/admin/user');
        $I->click('th.sorting_asc');
        $I->click("//td[text()='{$username}']/..//input[@value='Reject']");
    }

    /**
     * @Then /^I see status column on user list page$/
     */
    public function seeStatusColumn(): void
    {
        $I = $this->I;
        $I->amOnPage('/admin/user');
        $I->click('th.sorting_asc');
        $I->assertEquals($I->grabTextFrom('//*[@id="datatable"]/tbody/tr[1]/td[5]'), 'Pending', 'Can not see status column');
    }

    /**
     * @Then /^I should see the main menu in the header$/
     */
    public function iShouldSeeTheMainMenuInTheHeader()
    {
        $I = $this->I;

        $I->waitForElement('header a.dropdown-toggle svg[aria-label="Main Menu"]', 60);
        $I->seeElement('header a.dropdown-toggle svg[aria-label="Main Menu"]');
    }

    /**
     * @Then /^I should be logged in$/
     */
    public function iShouldBeLoggedIn()
    {
        $I = $this->I;

        $this->iShouldSeeTheMainMenuInTheHeader();
        $I->clickWithLeftButton(['css' => 'header a.dropdown-toggle svg[aria-label="Main Menu"]']);
        $I->waitForElement('a.logout', 60);
        $I->seeElement('a.logout');
    }
}
