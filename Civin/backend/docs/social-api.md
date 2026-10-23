# User Social API

All routes use the `/api/v1` prefix and require `Authorization: Bearer <access_token>`.
Collection endpoints return Laravel's paginated resource envelope. `per_page` defaults to
20 and accepts values from 1 through 100.

## Profiles

### Get the current profile

`GET /profile`

### Update the current profile

`PATCH /profile`

Accepted fields:

```json
{
  "nickname": "River",
  "bio": "Profile biography",
  "avatar_url": "https://cdn.example.com/avatar.jpg",
  "cover_image_url": "https://cdn.example.com/cover.jpg",
  "country_id": "019f7f43-0847-7bf4-a8db-ecf1d768b77c",
  "gender": "non_binary",
  "birthday": "2000-01-02",
  "is_private": false
}
```

`gender` accepts `male`, `female`, `non_binary`, or `prefer_not_to_say`.
The existing `display_name` and `birth_date` request fields remain supported as
backward-compatible aliases for `nickname` and `birthday`.

Profile responses include the user UUID, username, nickname, bio, avatar and cover URLs,
country, gender, birthday, level, VIP flag, privacy flag, follower/following/likes counts,
online status, live status, and viewer-relative `follow_status`/`is_blocked` fields.
`follow_status` is `accepted`, `pending`, or `null`. Level, VIP state, and counters are
server-managed.

### Get another user's profile

`GET /users/{user_uuid}/profile`

Returns `403` when either user has blocked the other.

## Followers and following

### Follow a user

`POST /users/{user_uuid}/follow`

Returns an accepted follow for a public account or a pending request for a private account.
Repeating the request is idempotent and never creates a duplicate relationship. The target
receives a database notification.

### Unfollow or cancel a request

`DELETE /users/{user_uuid}/follow`

Returns `204`. Accepted relationship counters are decremented atomically.

### Lists

- `GET /users/{user_uuid}/followers`
- `GET /users/{user_uuid}/following`
- `GET /follower-requests`

Private follower/following lists are visible only to the account owner and accepted followers.
Users blocked by or blocking the viewer are excluded.

### Respond to a request

- `POST /follower-requests/{follow_uuid}/accept`
- `DELETE /follower-requests/{follow_uuid}`

Only the target account can respond. Acceptance updates both counters and notifies the
requester. Invalid ownership returns `403`.

## Blocking

### Block a user

`POST /users/{user_uuid}/block`

Blocking is idempotent. It removes accepted and pending follow relationships in both
directions and repairs the associated counters in the same database transaction.

### Unblock a user

`DELETE /users/{user_uuid}/block`

Returns `204`. It does not restore old follow relationships.

### Blocked users

`GET /blocks`

Blocked users cannot follow each other, view each other's profile or social graph, or discover
each other through user search.

## Reports

### Categories

`GET /report-categories`

Values: `spam`, `harassment`, `hate_speech`, `impersonation`, `nudity`, `violence`, and
`other`. The `details` field is required for `other`.

### Report a user

`POST /users/{user_uuid}/reports`

```json
{
  "category": "harassment",
  "details": "Repeated abusive messages."
}
```

Self-reporting is rejected with `422`. The endpoint is limited to 10 requests per minute.

### Report history

`GET /reports/history`

Returns the authenticated reporter's reports and review state: `pending`, `reviewing`,
`resolved`, or `dismissed`.

### Administration

- `GET /admin/reports`
- `PATCH /admin/reports/{report_uuid}`

These endpoints require a user with `is_admin = true`.

```json
{
  "status": "resolved",
  "review_notes": "Action completed."
}
```

Review status accepts `reviewing`, `resolved`, or `dismissed`. Review completion records the
admin UUID and timestamp and sends the reporter a database notification.

## User search

`GET /users/search`

At least one filter is required:

- `query`: partial username or nickname, or an exact user UUID
- `country`: country UUID, alpha-2 code, alpha-3 code, or name prefix
- `is_online`: boolean
- `per_page`: 1 through 100

Example: `GET /users/search?query=river&country=TR&is_online=1`

Only active, non-deleted users are returned. The current user and users involved in a block
relationship with the current user are excluded.

## User status

### Get current status

`GET /user-status`

### Update current status

`PATCH /user-status`

```json
{
  "is_online": true,
  "is_live": true
}
```

At least one field is required. Going offline records `last_seen_at`. Starting a live session
records `live_started_at`; ending it clears that timestamp.

## Common errors

- `401`: missing or invalid Sanctum access token
- `403`: blocked interaction, private list, relationship ownership, or admin authorization
- `404`: user, follow request, or report does not exist
- `422`: invalid input or a self-directed follow, block, or report
- `429`: endpoint rate limit exceeded
