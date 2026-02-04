## Summary

<!-- Brief description of what this PR does -->

## Type of Change

- [ ] Bug fix (non-breaking change that fixes an issue)
- [ ] New feature (non-breaking change that adds functionality)
- [ ] Breaking change (fix or feature that would cause existing functionality to change)
- [ ] Refactoring (no functional changes)
- [ ] Documentation update
- [ ] Test update

## Checklist

### Before Submitting

- [ ] I have run `php fw validate:all` and all checks pass
- [ ] I have added tests that prove my fix/feature works
- [ ] I have updated documentation if needed
- [ ] My code follows the project's coding standards

### Security

- [ ] I have not introduced any `unserialize()` without `allowed_classes`
- [ ] I have not introduced any `eval()`, `create_function()`, or similar
- [ ] I have not hardcoded any credentials or secrets
- [ ] I have used parameterized queries for all database operations
- [ ] I have properly escaped all user output (XSS prevention)

### Architecture

- [ ] Controllers do not directly access the database (use Models/Repositories)
- [ ] No circular dependencies introduced
- [ ] Framework code (src/) does not depend on application code (app/)

## Testing

<!-- Describe how you tested this -->

```bash
# Commands to test this change
php fw test --filter=YourTest
```

## Screenshots (if applicable)

<!-- Add screenshots for UI changes -->

## Related Issues

<!-- Link any related issues: Fixes #123, Relates to #456 -->
