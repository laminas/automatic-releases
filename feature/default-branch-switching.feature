@manually-tested
Feature: Default branch switching

  Scenario: A new minor branch is created and set as default branch on release
    Given following existing branches:
      | branch |
      | 1.0.x  |
      | 1.1.x  |
      | 1.2.x  |
    And following open milestones:
      | name  |
      | 1.2.0 |
    And the default branch is "1.0.x"
    When I close milestone "1.2.0"
    Then these should be the existing branches:
      | branch |
      | 1.0.x  |
      | 1.1.x  |
      | 1.2.x  |
      | 1.3.x  |
    And the default branch should be "1.3.x"

  Scenario: The latest pre-existing minor release branch is set as default branch on a successful release
    Given following existing branches:
      | branch |
      | 1.0.x  |
      | 1.1.x  |
      | 1.2.x  |
      | 1.3.x  |
    And following open milestones:
      | name  |
      | 1.2.0 |
    And the default branch is "1.0.x"
    When I close milestone "1.2.0"
    Then these should be the existing branches:
      | branch |
      | 1.0.x  |
      | 1.1.x  |
      | 1.2.x  |
      | 1.3.x  |
    And the default branch should be "1.3.x"

  Scenario: A pre-existing branch of a greater major release is set as default branch on release
    Given following existing branches:
      | branch |
      | 1.0.x  |
      | 1.1.x  |
      | 1.2.x  |
      | 2.0.x  |
      | 2.1.x  |
    And following open milestones:
      | name  |
      | 1.2.0 |
    And the default branch is "1.0.x"
    When I close milestone "1.2.0"
    Then these should be the existing branches:
      | branch |
      | 1.0.x  |
      | 1.1.x  |
      | 1.2.x  |
      | 2.0.x  |
      | 2.1.x  |
    And the default branch should be "2.1.x"

  Scenario: Branch is not switched when no minor release branch exists
    Given following existing branches:
      | branch  |
      | foo     |
      | bar     |
      | master  |
      | develop |
    And following open milestones:
      | name  |
      | 1.2.0 |
    And the default branch is "develop"
    When I close milestone "1.2.0"
    Then these should be the existing branches:
      | branch  |
      | foo     |
      | bar     |
      | master  |
      | develop |
    And the default branch should be "develop"

