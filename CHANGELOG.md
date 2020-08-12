# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 1.2.0 - TBD

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
