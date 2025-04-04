@tool @tool_vault @javascript
Feature: Performing full site backup and restore with a light account in Vault
  In order to be able to backup and restore a site
  As an administrator
  I need to use tool vault

  Scenario Outline: Vault backup and restore (light)
    Given test API key for light account is specified for tool_vault
    And storage "<storage>" should be tested in tool_vault
    And the following config values are set as admin:
      | allowrestore | 1 | tool_vault |
    When I log in as "admin"
    And I navigate to "Server > Vault - Site migration" in site administration
    And I click on "Site backup" "link" in the "region-main" "region"
    And I press "Start backup"
    And I wait until "Automatically expire backup after" "field" exists
    And I set vault backup description field
    And I set vault backup storage field to "<storage>"
    And I set the following fields in the "Start backup" "dialogue" to these values:
      | Automatically expire backup after | 1 |
    And I click on "Start backup" "button" in the "Start backup" "dialogue"
    And I wait "2" seconds
    And I should see "[info] Backup scheduled"
    And I run the scheduled task "tool_vault\task\cron_task"
    And I reload the page
    And I should see "Backup finished"
    # Restore pre-check
    And I log in as "admin"
    And I navigate to "Server > Vault - Site migration" in site administration
    And I click on "Site restore" "link" in the "region-main" "region"
    And I click on "Refresh" "link" in the "region-main" "region"
    And I click on "Run pre-check" "button" in the row of my vault backup
    And I click on "Run pre-check" "button" in the "Run pre-check" "dialogue"
    And I run the scheduled task "tool_vault\task\cron_task"
    And I reload the page
    And I should see "Moodle version matches"
    And I should see "All plugins in the backup have the same version as the plugins on this site"
    And I should see "There is enough disk space in the temporary directory to perform site restore"
    And I log out
    # Restore
    And I log in as "admin"
    And I navigate to "Server > Vault - Site migration" in site administration
    And I click on "Site restore" "link" in the "region-main" "region"
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

    Examples:
      | storage               |
      | Western North America |
      | Asia-Pacific          |
      | Europe                |

  Scenario Outline: Vault restore a specific backup (light)
    Given test API key for light account is specified for tool_vault
    And I can restore backup <backupkey> made in version <version> using <env> key
    And the following config values are set as admin:
      | allowrestore             | 1     | tool_vault |
      | allowrestorewithmissing  | 1     | tool_vault |
      | upgradeafterrestore      | 1     | tool_vault |
      | restoreremovemissing     | 1     | tool_vault |
      | restorepreservepasswords | admin | tool_vault |
    # Restore pre-check
    And I log in as "admin"
    And I navigate to "Server > Vault - Site migration" in site administration
    And I click on "Site restore" "link" in the "region-main" "region"
    And I click on "Refresh" "link" in the "region-main" "region"
    And I click on "Run pre-check" "button" in the row of my vault backup
    And I click on "Run pre-check" "button" in the "Run pre-check" "dialogue"
    And I run the scheduled task "tool_vault\task\cron_task"
    And I reload the page
    And I should not see "Failed"
    And I log out
    # Restore
    And I log in as "admin"
    And I navigate to "Server > Vault - Site migration" in site administration
    And I click on "Site restore" "link" in the "region-main" "region"
    And I click on "Restore this backup" "button" in the row of my vault backup
    And I click on "Restore this backup" "button" in the "Restore this backup" "dialogue"
    And I should see "[info] Restore scheduled"
    And I wait "2" seconds
    And I run the scheduled task "tool_vault\task\cron_task"
    And I reload the page
    And I should see "Completed" in the "Status" "table_row"
    And I log in as "admin"
    And I navigate to "Server > Vault - Site migration" in site administration
    And I click on "Site restore" "link" in the "region-main" "region"
    And I should see "Restore completed"
    And I follow "Vault - Site migration"
    And I click on "History" "link" in the "region-main" "region"
    And I click on "View details" "link" in the "Past restores on this site" "table"
    And the following should exist in the "Restore details" table:
      | -1-        | -2-       |
      | Status     | Completed |
      | Logs       | ...       |
    And I follow "Expand logs"
    And I should not see "Expand logs"
    And I should see "Collapse logs"
    # Make sure there are no errors in the adhoc tasks scheduled during upgrade
    And I run all adhoc tasks
    # Database schema check is available in 4.0 and up
    And the site is running Moodle version 4.0 or higher
    And I navigate to "Reports > Performance overview" in site administration
    And I should see "OK" in the "Database schema is correct" "table_row"

    Examples:
      | backupkey                        | version | env  | comment |
      | B1B9VIFRGXCHZX1N34T3HSFK681GFDKQ | 3.9     | prod |         |
      | AE5O4CDDOIVKC6E6SA773G0H3SIM3EO7 | 3.10    | prod |         |
      | 8LWO9N62PF7126K6HD39ZSDRUNRCEZXB | 3.11    | prod |         |
      | RNTBHH43JDQXJHXCXGJY357JR6LS6IPB | 4.0     | prod |         |
      | 79N139YRC5NZ6ISDMG28W9HNBMB7VH58 | 4.1     | prod |         |
      | V7P7QEH4JTRHXBCMZHZA86T35XIWDZ54 | 4.2     | prod |         |
      | IETJ6X5OZZVD8A90PI7B7OH4CM0Y4FGV | 4.3     | prod |         |
      | 1KPPNNA2C2IBTJEYFN918063A4DN6B00 | 4.4     | prod |         |
      | RV281ZXAK6C4UUY8E2ZOR99BHQSCMDS6 | 4.5     | prod |         |
