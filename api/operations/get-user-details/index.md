# Get authenticated user details

GET

/api/v1/me

Select code samplecURL (shell:curl)

```js
const url = 'https://example.com/api/v1/me';
const options = {method: 'GET', headers: {Authorization: 'Bearer <token>'}};


try {
  const response = await fetch(url, options);
  const data = await response.json();
  console.log(data);
} catch (error) {
  console.error(error);
}
```

```sh
curl --request GET \
  --url https://example.com/api/v1/me \
  --header 'Authorization: Bearer <token>'
```

## Authorizations

* **[oauth2](https://laravel-starter.entapp.northwestern.edu/api/#oauth2)**
* **[bearerToken](https://laravel-starter.entapp.northwestern.edu/api/#bearertoken)**

## Responses

### 200

Authenticated user profile.

Media typeapplication/json

> object
>
> **data**
>
> required
>
> > object
> >
> > **id**
> >
> > required
> >
> > integer format: int64
> >
> > **username**
> >
> > required
> >
> > string
> >
> > **auth\_type**
> >
> > required
> >
> > How the user authenticates to the application.
> >
> > string
> >
> > Allowed values: sso local api
> >
> > **first\_name**
> >
> > required
> >
> > string
> >
> > **last\_name**
> >
> > required
> >
> > string
> >
> > **full\_name**
> >
> > required
> >
> > string
> >
> > **email**
> >
> > required
> >
> > string format: email
> >
> > **primary\_affiliation**
> >
> > Primary Northwestern affiliation for the user.
> >
> > string
> >
> > nullable
> >
> > Allowed values: student faculty staff affiliate not-matched
> >
> > **departments**
> >
> > Array\<string>
> >
> > **job\_titles**
> >
> > Array\<string>
> >
> > **created\_at**
> >
> > string format: date-time
> >
> > nullable
> >
> > **updated\_at**
> >
> > string format: date-time
> >
> > nullable
> >
> > **roles**
> >
> > Array\<object>
> >
> > nullable
> >
> > > object
> > >
> > > **id**
> > >
> > > required
> > >
> > > integer format: int64
> > >
> > > **name**
> > >
> > > required
> > >
> > > string
> > >
> > > **assignment\_locked**
> > >
> > > boolean
> > >
> > > **role\_type**
> > >
> > > One of:
> > >
> > > **object**
> > >
> > > > object
> > > >
> > > > **slug**
> > > >
> > > > required
> > > >
> > > > string
> > > >
> > > > Allowed values: system-managed application-admin application-role api-integration
> > > >
> > > > **label**
> > > >
> > > > required
> > > >
> > > > string
> > >
> > > **created\_at**
> > >
> > > string format: date-time
> > >
> > > nullable
> > >
> > > **updated\_at**
> > >
> > > string format: date-time
> > >
> > > nullable
> > >
> > > **permissions**
> > >
> > > Included when permissions are eager loaded.
> > >
> > > Array\<object>
> > >
> > > nullable
> > >
> > > > object
> > > >
> > > > **id**
> > > >
> > > > required
> > > >
> > > > integer format: int64
> > > >
> > > > **name**
> > > >
> > > > required
> > > >
> > > > Machine-friendly permission name.
> > > >
> > > > string
> > > >
> > > > **label**
> > > >
> > > > required
> > > >
> > > > Human readable permission label.
> > > >
> > > > string
> > > >
> > > > **description**
> > > >
> > > > string
> > > >
> > > > nullable
> > > >
> > > > **scope**
> > > >
> > > > required
> > > >
> > > > Whether this permission applies system-wide or only to the authenticated user’s context.
> > > >
> > > > string
> > > >
> > > > Allowed values: system-wide personal
> > > >
> > > > **system\_managed**
> > > >
> > > > required
> > > >
> > > > boolean
> > > >
> > > > **api\_relevant**
> > > >
> > > > required
> > > >
> > > > Whether this permission impacts API access patterns.
> > > >
> > > > boolean
> > > >
> > > > **created\_at**
> > > >
> > > > string format: date-time
> > > >
> > > > nullable
> > > >
> > > > **updated\_at**
> > > >
> > > > string format: date-time
> > > >
> > > > nullable
> > >
> > > **users\_count**
> > >
> > > Included when a user count aggregate is loaded.
> > >
> > > integer
> > >
> > > nullable

##### Example

```json
{
  "data": {
    "id": 1,
    "username": "waw1234",
    "auth_type": "api",
    "first_name": "NUIT",
    "last_name": "API",
    "full_name": "NUIT API",
    "email": null,
    "primary_affiliation": "not-matched",
    "departments": [],
    "job_titles": [],
    "created_at": "2025-11-06T15:20:00+00:00",
    "updated_at": "2025-04-09T18:45:10+00:00",
    "roles": [
      {
        "id": 1,
        "name": "API Role",
        "role_type": {
          "slug": "api-integration",
          "label": "API Integration"
        },
        "created_at": "2025-11-06T15:20:00+00:00",
        "updated_at": "2025-11-06T15:20:00+00:00",
        "permissions": [
          {
            "id": 1,
            "name": "view-users",
            "label": "View Users",
            "description": "Allows viewing all user profiles and their details.",
            "scope": "system-wide",
            "system_managed": false,
            "api_relevant": true,
            "created_at": "2025-11-06T15:20:00+00:00",
            "updated_at": "2025-11-06T15:20:00+00:00"
          }
        ]
      }
    ]
  }
}
```

### 401

Missing or invalid bearer token.

Media typeapplication/json

Standard RFC 9457 problem details envelope.

> object
>
> **type**
>
> required
>
> RFC 9457 compliant problem details response builder.
>
> string format: uri
>
> **title**
>
> required
>
> string
>
> **status**
>
> required
>
> integer
>
> **detail**
>
> string
>
> nullable
>
> **instance**
>
> required
>
> string format: uri-reference
>
> **trace\_id**
>
> Unique identifier for the API request (if available).
>
> string
>
> nullable

##### Example

```json
{
  "type": "about:blank",
  "title": "Unauthorized",
  "status": 401,
  "detail": "Authentication failed",
  "instance": "/api/v1/me",
  "trace_id": "b4f5aa7a-1470-4d92-8d3c-98e7c7de9f5f"
}
```
