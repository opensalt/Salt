<?php

namespace Page;

use Behat\Behat\Context\Context;

class LoginLocal implements Context, Login
{
    // include url of current page
    public static $loginUrl = '/login';
    public static $logoutUrl = '/logout';

    public static $loginLink = 'a.login';
    public static $usernameField = '#username';
    public static $passwordField = '#password';
    public static $loginButton = 'button.btn-login';

    /**
     * @var \AcceptanceTester
     */
    protected $I;

    public function __construct(\AcceptanceTester $I)
    {
        $this->I = $I;
    }

    public function logout(): Login
    {
        $I = $this->I;

        $I->amOnPage(self::$logoutUrl);

        return $this;
    }

    public function loginAsRole(string $role): Login
    {
        $this
            ->logout()
            ->aUserExistsWithRole($role)
            ->loginWithPassword($this->I->getLastUsername(), $this->I->getLastPassword());

        return $this;
    }

    public function loginWithPassword(string $username, string $password): Login
    {
        $I = $this->I;

        $I->amOnPage(self::$loginUrl);
        $I->fillField(self::$usernameField, $username);
        $I->fillField(self::$passwordField, $password);
        $I->click(self::$loginButton);

        $I->waitForElement('header a.dropdown-toggle svg[aria-label="Main Menu"]', 60);
        $I->seeElement('header a.dropdown-toggle svg[aria-label="Main Menu"]');
        $I->waitForElement('a.logout', 60);
        $I->seeElement('a.logout');
        $I->dontSee('Unrecognized username or password');

        $I->iAmOnTheHomepage();

        return $this;
    }

    public function iFillInTheUsername(): Login
    {
        $this->I->fillField(self::$usernameField, $this->I->getLastUsername());

        return $this;
    }

    public function iFillInThePassword(): Login
    {
        $this->I->fillField(self::$passwordField, $this->I->getLastPassword());

        return $this;
    }

    public function aUserExistsWithRole(string $role): Login
    {
        $this->I->ensureUserExistsWithRole($role);

        return $this;
    }

    public function aPendingUserExistsWithRole(string $role): Login
    {
        $this->I->ensurePendingUserExistsWithRole($role);

        return $this;
    }
}
