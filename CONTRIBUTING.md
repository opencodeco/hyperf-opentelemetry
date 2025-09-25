# Contributing

Thank you for considering contributing to `hyperf-opentelemetry`! 🎉

We welcome contributions of all kinds: bug fixes, new features, documentation improvements, tests, and discussions.

---

## Getting started

1. **Fork** the repository and create a new branch from `main`:
```bash
git checkout -b feat/your-feature-name
```

2. Install dependencies and run tests:
```bash
composer install
composer test
```

3. Follow code style guidelines:
- PHP: PSR-12
- Strict typing when possible
- Avoid creating unnecessary root spans; ensure proper context propagation

---

## Issues
- Use labels: bug, enhancement, question, performance, docs, good first issue, help wanted.
- When opening an issue, include:
  - Steps to reproduce 
  - Current vs. expected behavior 
  - Environment (PHP, Hyperf, OS, library version)
  - Relevant logs/stack traces

---

## Pull Requests

- Use Conventional Commits for PR titles (e.g., feat: add SQS aspect, fix: redis connection span leak).
- Include tests whenever possible.
- Update README/Docs if the change impacts usage or configuration.
- Checklist before submitting:
  - [ ] All tests passing (composer test)
  - [ ] Code style/lint OK 
  - [ ] Reasonable coverage for new code 
  - [ ] Documentation updated if necessary 
  - [ ] No breaking changes (or clearly documented)

---

## Branches & Releases

- main: stable branch
- `feat/*`, `fix/*`, `chore/*`: development branches
- Versioning follows Semantic Versioning (SemVer):
  - `MAJOR.MINOR.PATCH`
- Releases include changelogs (see GitHub Releases)

--- 

## Communication

- Slack channel: #hyperf-opentelemetry
- Technical discussions: GitHub Issues or Discussions