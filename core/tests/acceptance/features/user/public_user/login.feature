Feature: A user can sign into the application
  In order to manage frameworks
  As an anonymous user
  I need to sign in and become an authenticated user

  @login
  Scenario Outline: An anonymous user lands on the front page
    Given I am logged out
    And a user exists with role "<role>"

    When I am on the homepage
    And I follow "Sign in"
    Then I should see "Username"
    And I should see "Password"
    And I should see "Login"

    When I fill in the username
    And I fill in the password
    And I press "Login"
    Then I should be logged in

    Examples:
      | role |
      | Super-User |
      | Super-Editor |
      | Admin |
      | Editor |
