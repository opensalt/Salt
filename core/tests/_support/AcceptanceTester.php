<?php

use Behat\Behat\Context\Context;
use Facebook\WebDriver\WebDriverElement;

/**
 * Inherited Methods.
 *
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method \Codeception\Lib\Friend haveFriend($name, $actorClass = NULL)
 *
 * @SuppressWarnings(PHPMD)
 */
class AcceptanceTester extends \Codeception\Actor implements Context
{
    use _generated\AcceptanceTesterActions;

    protected static $documentsApi = '/ims/case/v1p0/CFDocuments?sort=updatedAt&orderBy=DESC&limit=1000';
    protected static $packagesApi = '/ims/case/v1p0/CFPackages/';

    private $lsDocId = null;
    private $lsItemId = null;

    /**
     * @Given I am on the homepage
     */
    public function iAmOnTheHomepage(): AcceptanceTester
    {
        $this->amOnPage('/');

        return $this;
    }

    /**
     * @Then I should see :arg1
     */
    public function iShouldSee(string $arg1): AcceptanceTester
    {
        $this->see($arg1);

        return $this;
    }

    /**
     * @Then I should not see the button :arg1
     */
    public function iShouldNotSeeTheButton(string $arg1): AcceptanceTester
    {
        $this->cantSee($arg1, 'button');

        return $this;
    }

    /**
     * @Then I should see the button :arg1
     */
    public function iShouldSeeTheButton(string $arg1): AcceptanceTester
    {
        $this->see($arg1, 'button');

        return $this;
    }

    /**
     * @Then I should see :arg1 in the header
     */
    public function iShouldSeeInTheHeader(string $arg1): AcceptanceTester
    {
        $this->see($arg1, 'header');

        return $this;
    }

    /**
     * @Then I should see :arg1 in the :arg2 element
     */
    public function iShouldSeeInTheElement(string $arg1, string $arg2): AcceptanceTester
    {
        $this->see($arg1, $arg2);

        return $this;
    }

    /**
     * @When I follow :arg1
     */
    public function iFollow(string $arg1): AcceptanceTester
    {
        $this->click($arg1);

        return $this;
    }

    /**
     * @When I press :arg1
     * @When I click :arg1
     */
    public function iPress(string $link): AcceptanceTester
    {
        switch ($link) {
            case 'Import framework':
                $this->clickWithLeftButton(['css' => 'header a.dropdown-toggle svg[aria-label="Main Menu"]']);
                $this->click('Import framework');
                $this->waitForElementVisible('.modal');
                break;

            default:
                $this->click($link);
        }

        return $this;
    }

    public function getLastFramework(): array
    {
        $documents = $this->fetchJson(self::$documentsApi);
        $documents = $documents['CFDocuments'] ?? [];

        if (0 === count($documents)) {
            /* @todo Create a framework if none found */

            throw new LogicException('No framework could be found');
        }

        $lastDoc = $documents[0];
        foreach ($documents as $document) {
            if (($document['adoptionStatus'] ?? 'Draft') !== 'Draft') {
                continue;
            }

            if ($lastDoc['updatedAt'] < $document['updatedAt']) {
                $lastDoc = $document;
            }
        }

        return $lastDoc;
    }

    public function getLastFrameworkTitle(): string
    {
        $lastDoc = $this->getLastFramework();

        return $lastDoc['title'];
    }

    public function getDocId()
    {
        if (null === $this->lsDocId) {
            return $this->getLastFrameworkId();
        }

        return $this->lsDocId;
    }

    public function setDocId($id)
    {
        $this->lsDocId = $id;
    }

    public function getFrameworkIdForIdentifier(string $identifier): string
    {
        try {
            $docPage = $this->fetch('/uri/'.$identifier, 'text/html');
        } catch (\Exception $e) {
            $docPage = null;
        }

        if (null === $docPage) {
            /* @todo Create a framework if none found */

            throw new LogicException('No framework could be found');
        }

        if (1 === preg_match('#/cftree/doc/(\d+)#', $docPage, $matches)) {
            $this->lsDocId = $matches[1];

            return $this->lsDocId;
        }

        throw new LogicException('Framework id could not be found');
    }

    public function getLastFrameworkId(): string
    {
        $lastDoc = $this->getLastFramework();

        return $this->getFrameworkIdForIdentifier($lastDoc['identifier']);
    }

    public function rememberDocIdFromUrl(): void
    {
        $this->lsDocId = $this->grabFromCurrentUrl('#/(\d+)$#');
    }

    public function getLastItemId()
    {
        $lastDoc = $this->getLastFramework();

        $framework = $this->fetchJson(self::$packagesApi.$lastDoc['identifier']);
        $items = $framework['CFItems'] ?? [];
        if (0 === count($items)) {
            /* @todo Create an item if none found */

            throw new LogicException('No item could be found in the last document');
        }

        $lastItem = $items[0];
        foreach ($items as $item) {
            if ($lastItem['lastChangeDateTime'] < $item['lastChangeDateTime']) {
                $lastItem = $item;
            }
        }

        try {
            $itemPage = $this->fetch('/uri/'.$lastItem['identifier'], 'text/html');
        } catch (\Exception $e) {
            $itemPage = null;
        }
        if (null === $itemPage) {
            /* @todo Create a item if none found */

            throw new LogicException('No item could be found');
        }

        if (1 === preg_match('#/cftree/item/(\d+)#', $itemPage, $matches)) {
            $this->lsItemId = $matches[1];
            return $this->lsItemId;
        }

        throw new LogicException('Item id could not be found');
    }

    public function getItemId()
    {
        if (null === $this->lsItemId) {
            return $this->getLastItemId();
        }

        return $this->lsItemId;
    }

    public function createAComment($content)
    {
        $this->click('.jquery-comments .commenting-field .textarea-wrapper .textarea');
        $this->fillField('.textarea', $content);
        $this->click('.jquery-comments .commenting-field .textarea-wrapper .control-row .send');
        $this->waitForElementChange('.comment-wrapper .wrapper .content', function (WebDriverElement $el) {
            return $el->isDisplayed();
        }, 2);
    }


    public function iAmOnAFrameworkPage()
    {
    }

    /**
     * @Given /^I am on the page "([^"]*)"$/
     */
    public function iAmOnThePage($page)
    {
        $this->amOnPage($page);
    }

    /**
     * @Given /^"([^"]*)" is enabled$/
     */
    public function featureIsEnabled($feature)
    {
        $this->assertFeatureEnabled($feature);
    }

    /**
     * @Then I fill in the :input with :data
     */
    public function iFillFieldWithData(string $input, string $data)
    {
        $this->fillField($input, $data);
    }
}
