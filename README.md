# Release automation for `doctrine/*` packages

This repository is a WIP of the current release automation tool
that is being developed while at the 

## TODOs

This is the proposed workflow:

1. contributor creates PR #123 - RED because not assigned to a milestone
2. maintainer assigns milestone 1.2.3 to PR #123 - (patch should only be green in this state)
3. maintainer merges PR #123
4. maintainer closes milestone 1.2.3
5. bot picks up hook event: milestone 1.2.3 closed -> create release notes -> tag -> publish
6. bot creates new branch (from the tag) 1.2.3-merge-up-to-1.3.x
7. bot opens PR for 1.2.3-merge-up-to-1.3.x

