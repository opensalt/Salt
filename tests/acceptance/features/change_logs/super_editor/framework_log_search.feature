Feature: Change Log Interaction
  Search form on the Log view page

  @0117-0716 @logs @ui @super-editor @duplicate
  Scenario: 0117-0716 Searching for an item in the search form shows the table of data elements with those item rows - Logged in as Super-Editor
    Given I am logged in as an "Super-Editor"
    When I create a framework
    And I add the item "First trial Item"
    And I edit the fields in a item
      | Abbreviated statement | 1st trial Item |
    And I edit the fields in a item
      | Abbreviated statement | First trial Item |
    And I select Log View
    And I enter "trial" in the log search field
    Then I should only see rows with "trial" in the log data table
    And I delete the framework
