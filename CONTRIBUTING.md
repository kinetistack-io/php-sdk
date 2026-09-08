# Contributing to KinetiStack PHP SDK

Thank you for contributing to the KinetiStack PHP SDK! This document provides guidelines and workflows for local development, code quality standards, pull requests, and the release lifecycle.

---

## 1. Development Environment

The SDK is framework-agnostic, supporting PHP 8.1 through 8.4. Local development is fully containerized using Docker Compose and managed via `make`.

### Prerequisites
- [Docker](https://docs.docker.com/get-docker/) & Docker Compose
- `make`

### Setup & Lifecycle Commands
Run all commands from the `php-sdk/` directory:

```bash
# Build the primary PHP 8.3 container
make build

# Build all PHP version matrix containers (8.1, 8.2, 8.3, 8.4)
make build-all

# Install dependencies in the container
make install

# Update dependencies
make update

# Run arbitrary Composer commands
make composer cmd="require <package-name>"

# Open an interactive shell inside the container
make shell
```

---

## 2. Quality & Verification Standards

Before opening a pull request, all code must satisfy strict quality checks:

### Code Style (PSR-12)
Code formatting is enforced via PHP CS Fixer.

```bash
# Check code style (dry-run with diff)
make cs-check

# Automatically fix code style violations
make cs-fix
```

### Static Analysis (PHPStan)
Static analysis is enforced at **Level 8** with strict parameter rules and explicit typing:

```bash
make phpstan
```

### Automated Tests (PHPUnit)
All unit and functional tests must pass:

```bash
# Run PHPUnit tests against PHP 8.3
make test

# Run PHPUnit tests across all supported PHP versions (8.1, 8.2, 8.3, 8.4)
make test-all
```

### Full Verification Check
Run the entire verification suite (`cs-check`, `phpstan`, and `test`) in one command:

```bash
make check
```

---

## 3. Git & Branching Workflow

1. **Sub-Repository Independence**:
   `php-sdk/` is an independent Git repository pushed to Forgejo (`KinetiStack/php-sdk`). Never commit changes that span across other sub-repository boundaries.
2. **Branch Naming**:
   Create branches branching from `main`:
   - Features: `feature/<short-description>` (e.g. `feature/PHPSDK-17-local-release-automation`)
   - Bug fixes: `fix/<short-description>`
3. **Pull Requests**:
   - Never push directly to `main` without a pull request.
   - Forgejo Actions CI automatically runs `.github/workflows/ci.yml` across PHP 8.1, 8.2, 8.3, and 8.4, validating tests, code style, and static analysis.
   - Ensure all CI checks pass before requesting review and merging.

---

## 4. Release Process (`make release`)

The release lifecycle consists of two halves:
1. **Local Release Automation (`make release`)**: Safely validates code, bumps `composer.json`, tags the release, and pushes to origin.
2. **Server-side CI Pipeline (`.github/workflows/release.yml`)**: Triggered by the pushed tag (`v*.*.*`), running matrix validation, generating GitHub/Forgejo release notes, and notifying Packagist.

### Cutting a New Release

Releases must follow [Semantic Versioning](https://semver.org/) (`X.Y.Z`).

1. Ensure all desired changes are reviewed and merged into the `main` branch.
2. Check out `main` and pull the latest changes:
   ```bash
   git checkout main
   git pull origin main
   ```
3. Ensure your local working tree is clean (`git status` shows no uncommitted changes).
4. Run the release command specifying the target `VERSION`:
   ```bash
   make release VERSION=1.2.4
   ```

### What `make release` Does Under the Hood

The automated target performs the following safeguards and actions sequentially:

1. **Input Validation**:
   - Verifies that `VERSION` was provided.
   - Strips optional `v` prefix and validates that the version strictly adheres to SemVer (e.g. `1.2.4` or `1.2.4-rc.1`).
2. **Pre-flight Git Safety Checks**:
   - Confirms that the Git working directory is clean (`git status --porcelain`).
   - Ensures execution is on the `main` branch (override with `ALLOW_BRANCH=1` if necessary).
   - Verifies the Git tag (`v<VERSION>`) does not already exist locally.
3. **Pre-Release Verification (`make check`)**:
   - Executes `make check` (`cs-check`, `phpstan`, `test`).
   - **Critical Safety Guard**: If any check fails, execution immediately halts before any files are touched or Git operations occur.
4. **Composer Version Bump**:
   - Updates `"version"` in `composer.json` using `composer config version <VERSION>`.
   - Synchronizes `composer.lock` hash using `composer update --lock`.
   - Validates `composer.json` integrity using `composer validate --no-check-version`.
5. **Git Commit & Tag**:
   - Stages `composer.json` and `composer.lock` (`git add composer.json composer.lock`).
   - Commits with message `Release v<VERSION>`.
   - Creates an annotated tag `v<VERSION>` (`git tag -a v<VERSION> -m "Release v<VERSION>"`).
6. **Push to Origin**:
   - Pushes the release commit on `main` to `origin`.
   - Pushes the new tag `v<VERSION>` to `origin`.

### Optional Flags

- **Dry Run**:
  To test the validation, `make check`, version bump, local commit, and tag without pushing to remote:
  ```bash
  make release VERSION=1.2.4 DRY_RUN=1
  ```
- **Allow Non-Main Branch**:
  To allow releasing from a branch other than `main` (for hotfixes or dry-run testing):
  ```bash
  make release VERSION=1.2.4 ALLOW_BRANCH=1
  ```

---

## 5. Server-Side CI Release Pipeline

Once `v<VERSION>` is pushed to `origin`:

1. **`.github/workflows/release.yml`** is triggered automatically.
2. It validates the tag across PHP 8.1, 8.2, 8.3, and 8.4 test matrices, code style, and PHPStan.
3. Upon successful validation, it creates the official Release with auto-generated release notes.
4. If configured, it triggers the Packagist API webhook to update the package index on [packagist.org](https://packagist.org/packages/kinetistack-io/php-sdk).
