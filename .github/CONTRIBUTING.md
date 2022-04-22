# Contributing to StellarWP

Thank you for your interest in making StellarWP even better!

This document outlines everything you need to know to get started contributing.

## Versioning scheme

This project adheres to [semantic versioning](https://semver.org/spec/v2.0.0.html), in the form of `<major>.<minor>.<patch>`.

* Bugfixes or updates that do not introduce new functionality nor break backwards compatibility should increment the **patch** version.
* New/improved functionality without backwards compatibility breaks should increment the **minor** version.
* Any breaks to backwards compatibility cause the **major** version to be incremented.

## Branching strategy

This repository follows [the "Git flow" branching model](https://www.atlassian.com/git/tutorials/comparing-workflows/gitflow-workflow):

-   The `develop` branch represents the latest work, and may not necessarily be stable
-   The `main` branch corresponds to the currently-released code

All work should be done in branches, that will generally use `develop` as their base (except in the case of hotfixes, which will be based on `main`).

Once ready for review, pull requests should be opened, comparing the feature branches against `develop`.

### Example pull request

Let's imagine you're submitting a new feature that lets the project order tacos for the team:

First, create a new feature branch off of `develop`:

```sh
$ git checkout -b feature/order-tacos develop
```

Next, add your feature (and tests!); once you're satisfied with the branch (and all [automated tests](#automated-testing) are passing), push the branch and open a new PR against `develop`:

```sh
$ git push -u origin feature/order-tacos
Enumerating objects: 6, done.
Counting objects: 100% (6/6), done.
Delta compression using up to 4 threads
Compressing objects: 100% (4/4), done.
Writing objects: 100% (5/5), 1.46 KiB | 745.00 KiB/s, done.
Total 5 (delta 1), reused 0 (delta 0), pack-reused 0
remote: Resolving deltas: 100% (1/1), completed with 1 local object.
remote:
remote: Create a pull request for 'feature/order-tacos' on GitHub by visiting:
remote:      https://github.com/stellarwp/admin-notice/pull/new/feature/order-tacos
remote:
To github.com:stellarwp/admin-notice.git
 * [new branch]      feature/order-tacos -> feature/order-tacos
Branch 'feature/order-tacos' set up to track remote branch 'feature/order-tacos' from 'origin'.
```

Once the PR is submitted, the Continuous Integration (CI) pipeline will kick in, automatically running all of our automated tests. Once those are passing, someone from the StellarWP team will review the PR and, if everything looks good (and the project really needs to be able to order tacos), will merge it into `develop` so that it can be included in the next release!

### Preparing a release

When it comes time to prepare a release, a new `release/*` branch (e.g. `release/v2.3.4`) should be created off of `develop`, [the changelog updated](../CHANGELOG.md), and a new pull request opened for the branch compared against `main`.

Once the release branch is merged into a `main`, a new tag should be created (e.g. `v2.3.4`), then `main` should be merged into `develop` to ensure parity.

## PHP compatibility

While StellarWP would love to see everyone running the latest and greatest versions of PHP, we also recognize that it's important to create tools that can service the larger WordPress community. As such, we commit to supporting [all versions officially supported by the latest release of WordPress](https://wordpress.org/about/requirements/).

The Continuous Integration (CI) pipeline is configured to run tests against all supported versions, so feel free to code in whatever version of PHP you have installed locally and let the pipeline sort it out! 😄

## Automated testing

This project utilizes a Continuous Integration (CI) pipeline, powered by [GitHub Actions](https://github.com/features/actions). Every pull request will trigger a series of automated checks to ensure the highest quality of code.

## Unit tests

One of the most important parts of our CI pipeline is our unit test suite, powered by [PHPUnit](https://phpunit.de).

You may execute the test suite at any time by running the following:

```sh
$ composer test:unit
```

When submitting a pull request, please include tests that cover your changes!

### Code coverage reporting

While 100% code coverage isn't absolutely necessary, code coverage reporting can help uncover areas of the codebase that aren't sufficiently tested.

You may generate code coverage reports at any time by running the following:

```sh
$ composer test:coverage
```

Reports will be generated in HTML form within [`tests/coverage/`](../tests/coverage).

## Coding standards

Despite this being WordPress-oriented code, this project uses [the PSR-12 coding standard](https://www.php-fig.org/psr/psr-12/), which has been adopted by the larger PHP community. We then supplement this with select WordPress best practices (verifying nonce usage, late-escaping, sanitization of user input, etc.).

One notable exception to PSR-12 is in our test classes, where test methods should use snake_case and the `@test` annotation:

```php
# Discouraged: camelCase with "test" prefix:
public function testItDoesTheThing() { /* ... */ }

# Preferred: snake_case with @test annotation:
/**
 * @test
 */
public function it_does_the_thing() { /* ... */ }
```

Our coding standards are enforced automatically as part of the Continuous Integration (CI) pipeline via [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) and [PHP-CS-Fixer](https://cs.symfony.com/). You may also run these coding standards checks at any time with the following command:

```sh
$ composer test:standards
```
