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
    public function iAddANewUserWithRole($role)
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
        $username = $faker->email;
        $password = $faker->password;
        $this->userName = $username;

        $I->click('a.dropdown-toggle');
        $I->click('Manage user');
        $I->see('User list', 'h1');
        $I->click('Add a new user');
        $I->fillField('#salt_userbundle_user_username', $username);
        $I->fillField('#salt_userbundle_user_plainPassword', $password);
        $I->checkOption($roleMap[$role]);
        $I->selectOption('#salt_userbundle_user_org', array('value' => 1));
        $I->click('Add');
        $I->waitForElementVisible('a.dropdown-toggle');
    }

    /**
     * @Then /^I add a new user$/
     */
    public function iAddANewUser()
    {
        $I = $this->I;

        /** @var \Faker\Generator $faker */
        $faker = \Faker\Factory::create();
        $username = $faker->email;
        $password = $faker->password;
        $this->userName = $username;

        $I->click('a.dropdown-toggle');
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
    public function iCreateANewAccount()
    {
        $I = $this->I;

        /** @var \Faker\Generator $faker */
        $faker = \Faker\Factory::create();
        $username = $faker->email;
        $password = $faker->password;
        $org = $faker->company;
        $this->userName = $username;

        $I->fillField('#signup_username', $username);
        $I->fillField('#signup_plainPassword_first', $password);
        $I->fillField('#signup_plainPassword_second', $password);
        $I->selectOption('#signup_org', 'other');
        $I->fillField('#signup_new_org', $org);
        $I->click('Add');
        $I->remember('lastNewUsername', $username);
    }

    /**
     * @Then /^I delete the User$/
     */
    public function iDeleteTheUser()
    {
        $I = $this->I;
        $username = $this->userName;
        $I->amOnPage('/admin/user/');
        $I->click("//td[text()='{$username}']/..//a[text()='show']");
        $I->see($username);
        $I->click('Delete');
        $I->waitForElementVisible('a.dropdown-toggle');
        $I->remember('lastDeletedUsername', $username);
    }

    /**
     * @Then /^I edit a user profile$/
     */
    public function iEditAUserProfile(TableNode $table)
    {
        $I = $this->I;

        $username = $this->userName;
        $I->amOnPage('/admin/user/');
        $I->click("//td[text()='{$username}']/..//a[text()='edit']");
        $rows = $table->getRows();
        foreach ($rows as $row) {
            $I->fillField('#salt_userbundle_user_username', $row[0]);
            $I->click('Save');
            $I->waitForText($row[0], 10);
            $I->see($row[0]);
            $this->userName = $row[0];
        }
    }

    /**
     * @Then /^I change the user's email address$/
     */
    public function iChangeUserEmailAddress()
    {
        $I = $this->I;

        $faker = \Faker\Factory::create();
        $newUsername = $faker->email;

        $username = $this->userName;
        $I->amOnPage('/admin/user/');
        $I->click("//td[text()='{$username}']/..//a[text()='edit']");
        $I->fillField('#salt_userbundle_user_username', $newUsername);
        $I->click('Save');
        $I->waitForText($newUsername, 30);
        $I->see($newUsername);
        $this->userName = $newUsername;
        $I->remember('lastChangedUsername', $newUsername);
    }

    /**
     * @Then /^I suspend the user$/
     */
    public function iSuspendTheUser()
    {
        $I = $this->I;
        $username = $this->userName;

        $I->amOnPage('/admin/user/');
        $I->click("//td[text()='{$username}']/..//a[text()='Suspend']");
        $I->dontSee('Edit', "//td[text()='{$username}']/..//a[text()='edit']");
    }

    /**
     * @Then /^I reinstate the user$/
     */
    public function iReinstateTheUser()
    {
        $I = $this->I;
        $username = $this->userName;

        $I->amOnPage('/admin/user/');
        $I->click("//td[text()='{$username}']/..//a[text()='Unsuspend']");
        $I->See('Edit', "//td[text()='{$username}']/..//a[text()='edit']");
    }

    /**
     * @Then /^I view the user$/
     */
    public function iViewTheUser()
    {
        $I = $this->I;
        $username = $this->userName;

        $I->amOnPage('/admin/user/');
        $I->See($username);
    }

    /**
     * @Given /^I am on the User list page$/
     */
    public function iAmOnTheUserListPage()
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
    public function iChangeMyPassword()
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
    public function iEditTheNewUser()
    {
        $I = $this->I;

        $username = $this->userName;
        $I->amOnPage('/admin/user/');
        $I->click("//td[text()='{$username}']/..//a[text()='edit']");

    }

    /**
     * @Then /^I show the new user$/
     */
    public function iShowTheNewUser()
    {
        $I = $this->I;

        $username = $this->userName;
        $I->amOnPage('/admin/user/');
        $I->click("//td[text()='{$username}']/..//a[text()='show']");

    }

    /**
     * @Then /^I approve the new user$/
     */
    public function iApproveTheNewUser()
    {
        $I = $this->I;

        $username = $this->userName;
        $I->amOnPage('/admin/user');
        $I->click("//td[text()='{$username}']/..//a[text()='Approve']");
    }

}
