---
title: Audit Logging
description: The audit logging system and what gets logged
---

## Overview

The starter includes a comprehensive audit logging system that tracks all significant user actions and data changes. This provides a complete historical record for compliance, security, and debugging purposes.

## Importance of Audit Logging

### Compliance

Many Northwestern applications handle sensitive data subject to regulations requiring audit trails:

- **FERPA** - Student education records
- **HIPAA** - Healthcare information
- **University Policies** - Administrative data retention requirements

### Security

Audit logs help detect and investigate:

- Unauthorized access attempts
- Suspicious data modifications
- Account compromise
- Privilege escalation

### Debugging

Audit logs assist with:

- Understanding how data reached its current state
- Identifying when changes were made
- Determining who made specific changes
- Diagnosing reported issues

### Accountability

Complete audit trails provide:

- Attribution for all actions
- Transparency in administrative processes
- Evidence for dispute resolution
- Historical context for decisions

## What Gets Audited

### Automatic Model Auditing

All models extending `BaseModel` automatically log **Create**, **Update**, **Delete**, and **Restore** Eloquent events with detailed information.

### Custom Audit Events

Beyond automatic model events, the system logs custom events for:

**Role Assignment/Removal**

- User receiving the role change
- Role being assigned/removed
- Before state (previous roles)
- After state (new roles)
- User who made the change

**Permission Syncing**

- Role whose permissions changed
- Old permission set
- New permission set
- User who made the change

**Impersonation**

- When impersonation starts
- Who is being impersonated
- Who started the impersonation
- When impersonation ends

## Audit Exclusions

Certain fields are excluded from audit logs to prevent storing sensitive or irrelevant data:

```php
// Within an Eloquent model
protected array $auditExclude = [
    'password',
]

```

### Why Exclude Fields?

1. **Security** - Sensitive data (passwords, tokens)
2. **Volume** - Fields that change too frequently
3. **Relevance** - Technical fields not meaningful for audit purposes
4. **Storage** - Reduce audit table size

## Custom Audit Events

For actions that don't fit standard CRUD, you may wish to log custom audit events. The `AuditsPermissions` and `AuditsRoles` traits can be used as examples of how this can be done.

Review the [Laravel Auditing](https://laravel-auditing.com/guide/audit-custom.html#example-custom-log-event) documentation for further information.
