# Release Automation for `laminas/*` packages

This project is a [Github Action](https://github.com/features/actions) that allows
maintainers of open-source projects that follow [SemVer](https://semver.org/spec/v2.0.0.html)
to automate the automation of releases.

## Installation

To use this automation in your own repository, copy the [`example/.github`](./examples/.github)
workflows into your own project:

```sh
cd /tmp
git clone https://github.com/laminas/automatic-releases.git
cd /path/to/your/project
cp -r /tmp/automatic-releases/examples/.github ./.github
git add .github
git commit -m "Added release automation"
```

Then add following [secrets](https://docs.github.com/en/actions/configuring-and-managing-workflows/creating-and-storing-encrypted-secrets)
to your project or organisation:

 * `GIT_AUTHOR_NAME` - full name of the author of your releases: can be the name of a bot account.
 * `GIT_AUTHOR_EMAIL` - email address of the author of your releases: can be an email address of a bot account.
 * `SIGNING_SECRET_KEY` - a **password-less** private GPG key in ASCII format, to be used for signing your releases:
   please use a dedicated GPG subkey for this purpose. Unsigned releases are not supported, and won't be supported.

## Usage

Assuming your project has Github Actions enabled, each time you [**close**](https://developer.github.com/webhooks/event-payloads/#milestone)
a [**milestone**](https://docs.github.com/en/github/managing-your-work-on-github/creating-and-editing-milestones-for-issues-and-pull-requests),
this action will perform all following steps (or stop with an error):

 1. determine if all issues and pull requests associated with this milestone are closed
 2. determine if the milestone is named with the SemVer `x.y.z` format
 3. create a changelog by looking at the milestone description and associated issues and pull requests
 4. select a branch for the release:
     * if a branch matching `x.y.z` exists, it will be selected
     * otherwise, `master` will be used
 5. create a tag named `x.y.z` on the selected branch, with the generated changelog
 6. publish a release named `x.y.z`, with the generated tag and changelog
 7. create (if applicable), a pull request from the selected branch to the next release branch
 8. create (if necessary) a "next minor" release branch `x.y+1.z`
 9. switch default repository branch to newest release branch

Please read the [`feature/`](./feature) specification for more detailed scenarios on how the tool is supposed
to operate.
