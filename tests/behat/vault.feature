@tool @tool_vault @javascript
Feature: Using tool vault
  In order to be able to backup and restore a site
  As an administrator
  I need to use tool vault

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

  Scenario: Vault backup and restore pre-check
    Given test API url is specified for tool_vault
    When I log in as "admin"
    And I navigate to "Server > Vault - Site migration" in site administration
    And I click on "Site backup" "link" in the "ul.nav-tabs" "css_element"
    And I follow "Register"
    And I press "Start backup"
    And I set the field "Passphrase" to "hello"
    And I click on "Start backup" "button" in the "Start backup" "dialogue"
    And I run the scheduled task "\tool_vault\task\cron_task"
    And I reload the page
    And I follow "More details"
    And I click on "Site restore" "link" in the "ul.nav-tabs" "css_element"
    And I press "Run pre-check"
    And I set the field "Passphrase" to "hello"
    And I click on "Run pre-check" "button" in the "Run pre-check" "dialogue"
    And I run the scheduled task "\tool_vault\task\cron_task"
    And I reload the page
