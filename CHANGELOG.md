# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 1.7.0 - TBD

### Added

- Nothing.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 1.6.0 - 2020-09-03


-----

### Release Notes for [1.6.0](https://github.com/laminas/automatic-releases/milestone/16)

Feature release (minor)

### 1.6.0

- Total issues resolved: **3**
- Total pull requests resolved: **3**
- Total contributors: **1**

#### Enhancement

 - [68: Strip empty sections from keep-a-changelog release notes](https://github.com/laminas/automatic-releases/pull/68) thanks to @weierophinney
 - [67: Normalize generated text to strip extra lines and redundant version information](https://github.com/laminas/automatic-releases/pull/67) thanks to @weierophinney
 - [66: End changelog version entry with empty line](https://github.com/laminas/automatic-releases/pull/66) thanks to @weierophinney

## 1.5.0 - 2020-08-31



-----

### Release Notes for [1.5.0](https://github.com/laminas/automatic-releases/milestone/13)



### 1.5.0

- Total issues resolved: **1**
- Total pull requests resolved: **1**
- Total contributors: **1**

#### Enhancement

 - [62: Add milestone descriptions when creating new milestones](https://github.com/laminas/automatic-releases/pull/62) thanks to @geerteltink
## 1.4.0 - 2020-08-31



-----

### Release Notes for [1.4.0](https://github.com/laminas/automatic-releases/milestone/11)



### 1.4.0

- Total issues resolved: **1**
- Total pull requests resolved: **1**
- Total contributors: **2**

#### Enhancement

 - [37: Create new milestones automatically](https://github.com/laminas/automatic-releases/pull/37) thanks to @geerteltink and @Ocramius
## 1.3.0 - 2020-08-26

### Added

- Nothing.

### Changed

- [#58](https://github.com/laminas/automatic-releases/pull/58) updates the "Release" step such that:
  - It now **always** uses jwage/changelog-generator to generate release notes.
  - **IF** a `CHANGELOG.md` file is present:
    - **IF** it contains changes for the target version, it appends the generated release notes to the changes for that version.
    - **OTHERWISE** it replaces the contents for the target version with the generated release notes.
    - And then it commits and pushes the file to the originating branch before it tags and releases.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.


-----

### Release Notes for [1.3.0](https://github.com/laminas/automatic-releases/milestone/5)



### 1.3.0

- Total issues resolved: **0**
- Total pull requests resolved: **9**
- Total contributors: **3**

#### Bug,Documentation

 - [60: Updates README.md  - automation is not laminas-specific](https://github.com/laminas/automatic-releases/pull/60) thanks to @michalbundyra

#### Bug

 - [59: Remove redundant infection configuration](https://github.com/laminas/automatic-releases/pull/59) thanks to @michalbundyra
 - [53: Use ORGANIZATION&#95;ADMIN&#95;TOKEN for our own release action](https://github.com/laminas/automatic-releases/pull/53) thanks to @weierophinney
 - [52: Merge release 1.2.3 into 1.3.x](https://github.com/laminas/automatic-releases/pull/52) thanks to @github-actions[bot]
 - [48: Merge release 1.2.2 into 1.3.x](https://github.com/laminas/automatic-releases/pull/48) thanks to @github-actions[bot]
 - [45: Fix YAML config for actions-tagger workflow](https://github.com/laminas/automatic-releases/pull/45) thanks to @weierophinney
 - [44: Merge release 1.2.1 into 1.3.x](https://github.com/laminas/automatic-releases/pull/44) thanks to @github-actions[bot]

#### Enhancement,Review Needed

 - [58: Automatically generate changelog revision text if the changelog was not hand-crafted - do not commit if unchanged](https://github.com/laminas/automatic-releases/pull/58) thanks to @weierophinney

#### Documentation,Enhancement

 - [54: Update workflow documentation and examples](https://github.com/laminas/automatic-releases/pull/54) thanks to @weierophinney
## 1.2.4 - TBD

### Added

- Nothing.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#55](https://github.com/laminas/automatic-releases/pull/55) fixes issues with identifying and retrieving `CHANGELOG.md` contents from non-default branches.

## 1.2.3 - 2020-08-13

### Added

- Nothing.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#50](https://github.com/laminas/automatic-releases/pull/50) updates the various classes performing API calls to issue authorization as a Personal Access Token instead of an OAuth token.

## 1.2.2 - 2020-08-12

### Added

- Nothing.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#47](https://github.com/laminas/automatic-releases/pull/47) fixes `CHANGELOG.md` update operations to avoid preventable failures during the release process.

## 1.2.1 - 2020-08-12

### Added

- Nothing.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#43](https://github.com/laminas/automatic-releases/pull/43) fixes which branch the minor changelog bump is targetted to to correctly be the next default branch.

## 1.2.0 - 2020-08-12

### Added

- [#40](https://github.com/laminas/automatic-releases/pull/40) adds a new command, laminas:automatic-releases:bump-changelog. When a `CHANGELOG.md` file is present in the repository, it will add an entry in the file for the next patch-level release to the target branch of the closed milestone. The patch also adds the command to the end of the suggested workflow configuration.

### Changed

- [#40](https://github.com/laminas/automatic-releases/pull/40) updates the laminas:automatic-releases:switch-default-branch-to-next-minor command such that if a `CHANGELOG.md` file is present in the repository, and a new minor release branch is created, it adds an entry to the file for the next minor release.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#41](https://github.com/laminas/automatic-releases/pull/41) fixes an issue that occurred when attempting to commit changes to the `CHANGELOG.md`.

## 1.1.0 - 2020-08-06

### Added

- [#18](https://github.com/laminas/automatic-releases/pull/18) adds support for using `CHANGELOG.md` files in [Keep-A-Changelog](https://keepachangelog.com) format for the release notes. When such a file is found, the tooling will set the release date in the file, commit and push it, and then extract that changelog version to use in the release notes. It still pulls release notes from patches associated with the milestone as well, in order to provide attribution and provide additional insight into the changes associated with the release.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.
