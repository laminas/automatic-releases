# Release automation for `laminas/*` packages

Please read the [`feature/`](./feature) specification to understand how
this tool behaves.

TL;DR: when you close a milestone:
 * a `git tag` will be created
 * a github release will be created
 * if not already existing, a release branch will be created
 * a new pull request will be created, porting changes to the next release branch from an intermediary branch (e.g. 1.2.3-merge-up-to-1.3.x)
