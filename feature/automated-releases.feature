@manually-tested
Feature: Automated releases

  Scenario: If no major release branch exists, the tool should not create a new major release
    Given following existing branches:
      | name    |
      | 1.0.x   |
      | master  |
      | develop |
    And following open milestones:
      | name  |
      | 2.0.0 |
    When I close milestone "2.0.0"
    Then the tool should have halted with an error

  Scenario: If no major release branch exists, the tool should not create a new minor release
    Given following existing branches:
      | name    |
      | 1.0.x   |
      | master  |
      | develop |
    And following open milestones:
      | name  |
      | 1.1.0 |
    When I close milestone "1.1.0"
    Then the tool should have halted with an error

  Scenario: If a major release branch exists, the tool creates a major release from there
    Given following existing branches:
      | name    |
      | 1.0.x   |
      | 2.0.x   |
      | master  |
      | develop |
    And following open milestones:
      | name  |
      | 2.0.0 |
    When I close milestone "2.0.0"
    Then tag "2.0.0" should have been created on branch "2.0.x"

  Scenario: If a new major release branch exists, the tool does not create a new minor release
    Given following existing branches:
      | name    |
      | 1.0.x   |
      | 2.0.x   |
      | master  |
      | develop |
    And following open milestones:
      | name  |
      | 1.1.0 |
    When I close milestone "1.1.0"
    Then the tool should have halted with an error

  Scenario: If a minor release branch exists, when closing the minor release milestone,
  the tool tags the minor release from the branch, and creates a pull request
  against the next newer minor release branch.
    Given following existing branches:
      | name   |
      | 1.1.x  |
      | 1.2.x  |
      | master |
    And following open milestones:
      | name  |
      | 1.1.0 |
    When I close milestone "1.1.0"
    Then tag "1.1.0" should have been created on branch "1.1.x"
    And a new pull request from branch "1.1.x" to "1.2.x" should have been created

  Scenario: If no newer release branch exists, the tool will not create any pull requests
    Given following existing branches:
      | name    |
      | 1.1.x   |
      | master  |
      | develop |
    And following open milestones:
      | name  |
      | 1.1.0 |
    When I close milestone "1.1.0"
    Then tag "1.1.0" should have been created on branch "1.1.x"
    And no new pull request should have been created

  Scenario: If a minor release branch exists, the tool creates a new patch release from there
    Given following existing branches:
      | name   |
      | 1.1.x  |
      | 1.2.x  |
      | master |
    And following open milestones:
      | name  |
      | 1.1.1 |
    When I close milestone "1.1.1"
    Then tag "1.1.1" should have been created on branch "1.1.x"
    And a new pull request from branch "1.1.x" to "1.2.x" should have been created

  Scenario: If a minor release branch doesn't exist, the tool refuses to create it if a newer one exists
    Given following existing branches:
      | name   |
      | 1.2.x  |
      | master |
    And following open milestones:
      | name  |
      | 1.1.0 |
    When I close milestone "1.1.0"
    Then the tool should have halted with an error

  Scenario: If a minor release branch doesn't exist, the tool refuses to create a patch release
    Given following existing branches:
      | name   |
      | master |
    And following open milestones:
      | name  |
      | 1.1.1 |
    When I close milestone "1.1.1"
    Then the tool should have halted with an error
