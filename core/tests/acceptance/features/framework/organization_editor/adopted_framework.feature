Feature: A framework marked as "Adopted" should not allow edits
  In order to enforce the CASE spec
  As an editor
  I should not be able to edit items in an "Adopted" framework

  @editor @case-file @adopted @1106-1103
  Scenario: 1106-1103 "Adopted" frameworks should not allow editing
    Given I log in as a user with role "Admin"
    And I am on the homepage

    When I click "Import framework"
    Then I should see the import dialogue

    When I click "Import CASE® file"
    And I upload the adopted CASE file
    And I go to the uploaded framework
    Then I should see the button "Manage Association Groups"
    And I should not see the button "Add New Child Item"
    And I should not see the button "Import Children"
    And I should not see the button "Update Framework"
    And I should see the button "Export"
    And I should see the button "Edit"

    When I click the first item in the framework
    And I should not see the button "Edit"
    And I should not see the button "Delete"
    And I should not see the button "Add a New Child Item"
    And I should see the button "Add an Exemplar"

    Then I edit the fields in a framework
      | Adoption Status | Deprecated |

    And I delete the framework
