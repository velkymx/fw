# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-01-28

### Added

- **Core Framework**
  - Application container with dependency injection
  - Router with named routes, groups, and middleware
  - Request/Response handling with streaming support
  - View engine with layouts, sections, and caching
  - Configuration management with caching

- **Database**
  - PDO-based Connection with transaction support
  - Fluent QueryBuilder with operator whitelisting
  - Active Record Model with relationships
  - Migration system with rollback support
  - N+1 query detection in development

- **Security**
  - CSRF protection with timing-safe validation
  - Input sanitization (HTML, URL, filename, JSON)
  - Mass assignment protection with strict mode
  - Rate limiting middleware
  - Security headers helper (HSTS, CSP, etc.)
  - HMAC-signed queue job payloads

- **Authentication**
  - Session-based authentication
  - Remember me tokens with HMAC signing
  - API token management
  - Timing attack mitigations

- **Queue System**
  - File and database drivers
  - Delayed jobs
  - Retry with backoff
  - Failed job handling

- **CLI Framework**
  - Code generators (model, controller, middleware, migration)
  - Database commands (migrate, rollback, seed)
  - Development server
  - Route listing

- **Validation**
  - PHP 8 attribute-based validation
  - Form request classes
  - Comprehensive rule set

- **Async Support**
  - Fiber-based request handling
  - Event loop integration
  - Worker mode compatibility (FrankenPHP, RoadRunner)

### Security

- Parameterized queries prevent SQL injection
- Operator whitelisting in QueryBuilder
- CSRF tokens with timing-safe comparison
- Unicode control character filtering in URL sanitizer
- Signed serialization prevents RCE in queue jobs
- Constant-time authentication comparisons
- HMAC-signed remember cookies
- Secure session configuration by default

### Performance

- 15,500+ req/sec with FrankenPHP worker mode
- OPcache-based configuration caching
- Route caching
- View fragment caching
- Reflection metadata caching
- Lazy service loading

---

## Version History

### Versioning Policy

- **1.x**: Initial stable release, PHP 8.4+
- **Future**: PHP version requirements may increase in major versions

### Upgrade Guides

Upgrade guides will be provided for major version changes.

---

[Unreleased]: https://github.com/fw-php/framework/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/fw-php/framework/releases/tag/v1.0.0
