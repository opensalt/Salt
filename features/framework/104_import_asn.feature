Feature: An ASN document can be imported
  In order to load an ASN framework as a starting point
  As an editor
  I need to import an ASN document

  @smoke @editor @asn-import @asn-import-104
  Scenario: A CASE file can be uploaded and downloaded
    Given I log in as a user with role "Editor"
    And I am on the homepage
    Then I count frameworks imported from ASN
    When I click "Import framework"
    Then I should see the import dialogue
    When I click "Import from ASN"
    And I fill in an ASN document identifier
    And I click "Import Framework"
    Then I should see the ASN framework loaded
