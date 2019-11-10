@manually-tested
Feature: Automated releases

  Scenario: If no major release branch exists, the tool should not create a new major release
    Given following existing branches:
      | name  |
      | 1.0.x |
    And following open milestones:
      | name  |
      | 2.0.0 |
    When I close milestone "2.0.0"
    Then the tool should have halted with an error

  Scenario: If no major release branch exists, the tool should not create a new minor release
    Given following existing branches:
      | name  |
      | 1.0.x |
    And following open milestones:
      | name  |
      | 1.1.0 |
    When I close milestone "1.1.0"
    Then the tool should have halted with an error

  Scenario: If a major release branch exists, the tool creates a major release from there
    Given following existing branches:
      | name   |
      | 1.0.x  |
      | master |
    And following open milestones:
      | name  |
      | 2.0.0 |
    When I close milestone "2.0.0"
    Then tag "2.0.0" should have been created on branch "master"
    And branch "2.0.x" should have been created from "master"

  Scenario: If a major release branch exists, the tool creates a new minor release from there
    Given following existing branches:
      | name   |
      | 1.0.x  |
      | master |
    And following open milestones:
      | name  |
      | 1.1.0 |
    When I close milestone "1.1.0"
    Then tag "1.1.0" should have been created on branch "master"
    And branch "1.1.x" should have been created from "master"

  Scenario: If a minor release branch exists, the tool creates a new minor release from there
    Given following existing branches:
      | name   |
      | 1.1.x  |
      | master |
    And following open milestones:
      | name  |
      | 1.1.0 |
    When I close milestone "1.1.0"
    Then tag "1.1.0" should have been created on branch "1.1.x"
    And a new pull request from branch "1.1.x" to "master" should have been created

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
