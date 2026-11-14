# API Conventions & Response Contracts

## 1. Routing & Versioning

All API endpoints are explicitly versioned under the `/api/v1/` prefix:
- `/api/v1/auth/*`
- `/api/v1/organizations/*`
- `/api/v1/rws/*`
- `/api/v1/rts/*`
- `/api/v1/memberships/*`
- `/api/v1/files/*`
- `/api/v1/audit-logs`

## 2. Response Formats

### Success Response
```json
{
  "data": {
    "id": "01924b12-9c9e-716b-9c3f-8461abde5001",
    "name": "RW 05",
    "number": 5,
    "status": "active"
  },
  "meta": {
    "request_id": "01924b12-9c9e-716b-9c3f-8461abde5002"
  }
}
```

### Paginated List Response
```json
{
  "data": [
    {
      "id": "01924b12-9c9e-716b-9c3f-8461abde5001",
      "number": 1,
      "name": "RT 01"
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 42,
      "last_page": 3,
      "has_more": true
    },
    "request_id": "01924b12-9c9e-716b-9c3f-8461abde5002"
  }
}
```

### Error Response
```json
{
  "error": {
    "code": "validation_failed",
    "message": "The given data was invalid.",
    "request_id": "01924b12-9c9e-716b-9c3f-8461abde5002",
    "errors": {
      "number": ["The number field is required."]
    }
  }
}
```

## 3. Standard HTTP Status Codes

| Code | Meaning | Typical Usage |
| :--- | :--- | :--- |
| `200` | OK | Successful GET, PUT, or DELETE with content |
| `201` | Created | Successful resource creation (POST) |
| `204` | No Content | Successful deletion without content |
| `400` | Bad Request | Malformed headers or missing required parameters |
| `401` | Unauthenticated | Missing or expired Sanctum Bearer token |
| `403` | Forbidden | Authenticated user lacks permission or organizational scope |
| `404` | Not Found | Requested entity does not exist |
| `409` | Conflict | Duplicate resource or constraint violation |
| `422` | Unprocessable Content | Form validation errors or domain business rule violations |
| `500` | Server Error | Unhandled exceptions (internal stack traces never exposed to client) |

## 4. End-to-End Request Correlation (`X-Request-Id`)

Every HTTP request is assigned a unique correlation ID by the `AssignRequestId` middleware:
1. If the client supplies a valid `X-Request-Id` header, it is reused.
2. Otherwise, a UUIDv7 is generated.
3. The ID is injected into Laravel's logging context (`Log::shareContext`), attached to all database audit records, and echoed back in the `X-Request-Id` response header as well as in `meta.request_id` or `error.request_id`.
