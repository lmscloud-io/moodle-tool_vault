@tool @tool_vault
Feature: Using tool vault
  In order to be able to backup and restore a site
  As an administrator
  I need to use tool vault

  @javascript
  Scenario: Vault UI
    When I log in as "admin"
    And I navigate to "Server > Vault - Site migration" in site administration
    And I click on "Site backup" "link" in the "ul.nav-tabs" "css_element"
    And I click on "Site restore" "link" in the "ul.nav-tabs" "css_element"
    And I click on "Settings" "link" in the "ul.nav-tabs" "css_element"
    And I follow "Edit backup settings"
    And I press "Cancel"
    And I follow "Edit restore settings"
    And I press "Cancel"
    And I click on "Overview" "link" in the "ul.nav-tabs" "css_element"
    And I trigger cron
    And I am on homepage
    And I navigate to "Server > Vault - Site migration" in site administration
    And I follow "See full report"
