@tool @tool_vault @javascript
Feature: Using tool vault
  In order to be able to backup and restore a site
  As an administrator
  I need to use tool vault

  Scenario: Vault UI
    When I log in as "admin"
    And I navigate to "Server > Vault - Site migration" in site administration
    And I click on "Site backup" "link" in the "region-main" "region"
    And I follow "Vault - Site migration"
    And I click on "Site restore" "link" in the "region-main" "region"
    And I follow "Vault - Site migration"
    And I click on "Settings" "link" in the "region-main" "region"
    And I navigate to "Server > Vault - Site migration" in site administration
    And I click on "History" "link" in the "region-main" "region"
    And I run the scheduled task "\tool_vault\task\cron_task"
    And I am on homepage
    And I navigate to "Server > Vault - Site migration" in site administration
    And I click on "Site backup" "link" in the "region-main" "region"
    And I should see "Database modifications"
    And I follow "See full report"
    And I should see "Config overrides"

  Scenario: Entering API key for Vault
    When I log in as "admin"
    And I navigate to "Server > Vault - Site migration" in site administration
    And the "src" attribute of "#getapikey_iframe" "css_element" should contain "about:blank"
    And I press "Sign in"
    And I wait "2" seconds
    And the "src" attribute of "#getapikey_iframe" "css_element" should not contain "about:blank"
    And I switch to "getapikey_iframe" class iframe
    And I should see "Forgot your password?"
    And I switch to the main frame
    And I press "Create account"
    And I wait "2" seconds
    And the "src" attribute of "#getapikey_iframe" "css_element" should not contain "about:blank"
    And I switch to "getapikey_iframe" class iframe
    And I should not see "Forgot your password?"
    And I should see "Confirm Password"
    And I switch to the main frame
    And I press "I have an API key"
    And the "src" attribute of "#getapikey_iframe" "css_element" should contain "about:blank"
    And I set the field "API key" to "hellothereThisisaninvalidAPIkeythatlooksvalid"
    And I press "Save changes"
    And I should see "API key not valid"

  Scenario: Vault forget API key
    Given test API key is specified for tool_vault
    When I log in as "admin"
    And I navigate to "Server > Vault - Site migration" in site administration
    And "I have an API key" "button" should not exist
    And I should see "You are using API key"
    And I follow "Forget API key"
    And "I have an API key" "button" should exist

  Scenario: Vault backup and restore
    Given test API key is specified for tool_vault
    And the following config values are set as admin:
      | allowrestore | 1  | tool_vault |
    When I log in as "admin"
    And I navigate to "Server > Vault - Site migration" in site administration
    And I click on "Site backup" "link" in the "region-main" "region"
    And I press "Start backup"
    And I wait until "Automatically expire backup after" "field" exists
    And I set vault backup description field
    And I set the following fields in the "Start backup" "dialogue" to these values:
      | Store data in                     | Europe |
      | Passphrase                        |        |
      | Automatically expire backup after | 1      |
    And I click on "Start backup" "button" in the "Start backup" "dialogue"
    And I wait "2" seconds
    And I should see "[info] Backup scheduled"
    And I run the scheduled task "tool_vault\task\cron_task"
    And I reload the page
    And I should see "Backup finished"
    And I log in as "admin"
    And I navigate to "Server > Vault - Site migration" in site administration
    And I click on "Site restore" "link" in the "region-main" "region"
    And I click on "Refresh" "link" in the "region-main" "region"
    And I click on "Restore this backup" "button" in the row of my vault backup
    And I click on "Restore this backup" "button" in the "Restore this backup" "dialogue"
    And I should see "[info] Restore scheduled"
    And I wait "2" seconds
    And I run the scheduled task "tool_vault\task\cron_task"
    And I log in as "admin"
    And I navigate to "Server > Vault - Site migration" in site administration
    And I click on "Site restore" "link" in the "region-main" "region"
    And I should see "Restore completed"
    And I follow "Vault - Site migration"
    And I click on "History" "link" in the "region-main" "region"
    And I click on "View details" "link" in the "Past backups on this site" "table"
    And the following should exist in the "Backup details" table:
      | -1-        | -2-       |
      | Status     | Completed |
      | Passphrase | No        |
      | Logs       | ...       |
    And I follow "Expand logs"
    And I should not see "Expand logs"
    And I should see "Collapse logs"
    And I navigate to "Server > Vault - Site migration" in site administration
    And I click on "History" "link" in the "region-main" "region"
    And I click on "View details" "link" in the "Past restores on this site" "table"
    And the following should exist in the "Restore details" table:
      | -1-        | -2-       |
      | Status     | Completed |
      | Logs       | ...       |
    And I follow "Expand logs"
    And I should not see "Expand logs"
    And I should see "Collapse logs"

  Scenario: Vault backup and restore pre-check
    Given test API key is specified for tool_vault
    And the following config values are set as admin:
      | allowrestore | 1  | tool_vault |
    When I log in as "admin"
    And I navigate to "Server > Vault - Site migration" in site administration
    And I click on "Site backup" "link" in the "region-main" "region"
    And I press "Start backup"
    And I wait until "Automatically expire backup after" "field" exists
    And I set vault backup description field
    And I set the following fields in the "Start backup" "dialogue" to these values:
      | Store data in                     | Europe |
      | Passphrase                        | hello  |
      | Automatically expire backup after | 1      |
    And I click on "Start backup" "button" in the "Start backup" "dialogue"
    And I wait "2" seconds
    And I should see "[info] Backup scheduled"
    And I run the scheduled task "tool_vault\task\cron_task"
    And I reload the page
    And I should see "Backup finished"
    And I log in as "admin"
    And I navigate to "Server > Vault - Site migration" in site administration
    And I click on "Site restore" "link" in the "region-main" "region"
    And I click on "Refresh" "link" in the "region-main" "region"
    And I click on "Run pre-check" "button" in the row of my vault backup
    And I set the following fields in the "Run pre-check" "dialogue" to these values:
      | Passphrase | hello  |
    And I click on "Run pre-check" "button" in the "Run pre-check" "dialogue"
    And I run the scheduled task "tool_vault\task\cron_task"
    And I reload the page
    And I should see "Moodle version matches"
    And I should see "All plugins in the backup have the same version as the plugins on this site"
    And I should see "There is enough disk space in the temporary directory to perform site restore"
