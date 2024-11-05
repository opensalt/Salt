Feature: A CASE framework with sequence numbers can be loaded
  In order to have the correct order of items
  As an editor
  I need to be able to load sequence numbers from CASE files

  @editor @case-file @1103-1626
  Scenario: 1103-1626 A CASE file can contain sequence numbers
    Given I log in as a user with role "Editor"
    And I am on the homepage
    When I click "Import framework"
    Then I should see the import dialogue
    When I click "Import CASE® file"
    And I upload the sequence number CASE file
    And I go to the uploaded framework
    Then I should see "SNT.2" as the first item in the tree's HCS value

    And I delete the framework
