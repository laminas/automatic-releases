# Release Automation for `laminas/*` packages

This project is a [Github Action](https://github.com/features/actions) that allows
maintainers of open-source projects that follow [SemVer](https://semver.org/spec/v2.0.0.html)
to automate the automation of releases.

## Installation

To use this automation in your own repository, copy the [`examples/.github`](./examples/.github)
workflows into your own project:

```sh
cd /tmp
git clone https://github.com/laminas/automatic-releases.git
cd /path/to/your/project
cp -r /tmp/automatic-releases/examples/.github ./.github
git add .github
git commit -m "Added release automation"
```

To get started you need to create a branch for the next release. e.g. if your next milestone will be
`3.2.0` a `3.2.x` branch is required.

Then add following [secrets](https://docs.github.com/en/actions/configuring-and-managing-workflows/creating-and-storing-encrypted-secrets)
to your project or organization:

- `GIT_AUTHOR_NAME` - full name of the author of your releases: can be the name of a bot account.
- `GIT_AUTHOR_EMAIL` - email address of the author of your releases: can be an email address of a bot account.
- `SIGNING_SECRET_KEY` - a **password-less** private GPG key in ASCII format, to be used for signing your releases:
  please use a dedicated GPG subkey for this purpose. Unsigned releases are not supported, and won't be supported.
- `ORGANIZATION_ADMIN_TOKEN` - if you use the file from [`examples/.github/workflows/release-on-milestone-closed.yml`](examples/.github/workflows/release-on-milestone-closed.yml),
  then you have to provide a `ORGANIZATION_ADMIN_TOKEN` (with a full repo scope), which is a github token with
  administrative rights over your organization (issued by a user that has administrative rights over your project).
  This is required for the `laminas:automatic-releases:switch-default-branch-to-next-minor`
  command, because [changing default branch of a repository currently requires administrative token rights](https://developer.github.com/v3/repos/#update-a-repository).
  You can generate a token from your [personal access tokens page](https://github.com/settings/tokens/new).

## Usage

Assuming your project has Github Actions enabled, each time you [**close**](https://developer.github.com/webhooks/event-payloads/#milestone)
a [**milestone**](https://docs.github.com/en/github/managing-your-work-on-github/creating-and-editing-milestones-for-issues-and-pull-requests),
this action will perform all following steps (or stop with an error):

1.  determine if all issues and pull requests associated with this milestone are closed
2.  determine if the milestone is named with the SemVer `x.y.z` format
3.  create a changelog by looking at the milestone description and associated issues and pull requests
4.  select branch `x.y.z` for the release (e.g. `1.1.x` for a `1.1.0` release)
5.  create a tag named `x.y.z` on the selected branch, with the generated changelog
6.  publish a release named `x.y.z`, with the generated tag and changelog
7.  create (if applicable), a pull request from the selected branch to the next release branch
8.  create (if necessary) a "next minor" release branch `x.y+1.z`
9.  switch default repository branch to newest release branch

Please read the [`feature/`](./feature) specification for more detailed scenarios on how the tool is supposed
to operate.

## Branching model

In this model we operate with release branches (e.g. `1.0.x`, `1.1.x`, `1.2.x`).
This provides a lot of flexibility whilst keeping a single workflow.

![](./docs/branching-model.png)

### Working on new features

The current default release branch should be used. The default branch is always automatically changed
after a new release is created.

An example is Mezzio that has `3.2.x` as the current default release branch for simple features and
deprecation notices and `4.0.x` for the next major release.

### Working on bug fixes

Bug fixes should be applied on the version which introduced the issue and then synchronized all way to
the current default release branch.

### Releasing

When releasing a new version `x.y.z`, a new branch will be created `x.y+1.z` and will be set as the next
default release branch.

### Synchronizing branches

To keep branches synchronized merge-ups are used.

That consists in getting the changes of a specific released branch merged all the way up to `master`.
This ensures that all release branches and the `master` branch are up-to-date and will never present a bug which has already been fixed.

**Example**

Let's say we've released the versions `1.0.0` and `1.1.0`.
New features are being developed on `1.2.x`.
After a couple weeks, a bug was found on version `1.0.0`.

The fix for that bug should be done based on the branch `1.0.x` and, once merged, the branches should be updated in this way:

1. Create a branch from the fixed `1.0.x` (`git checkout 1.0.x && git checkout -b merge-up/1.0.x-into-1.1.x`)
1. Create a PR using `1.1.x` as destination
1. Create a branch from the fixed `1.1.x` (`git checkout 1.1.x && git checkout -b merge-up/1.1.x-into-1.2.x`)
1. Create a PR using `1.2.x` as destination

:warning: when the merge-up can't be merged due to conflicts, we'd need to sync it with the destination branch.
That's done by merging the destination into the merge-up branch and resolving the conflicts locally:

1. Checkout to merge-up branch (`git checkout -b merge-up/1.1.x-into-master`)
1. Sync merge-up branch (`git merge --no-ff origin/master`)
1. Solve conflicts (using `git mergetool` or through an IDE)
1. Resume merge (`git merge --continue`)
1. Push (`git push`)
