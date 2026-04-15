# Porkbun API v3

_Generated from https://porkbun.com/api/json/v3/spec via MarkItDown-compatible OpenAPI parsing on 2026-04-14._

The Porkbun API enables programmatic domain registration, DNS management, SSL certificate retrieval, and related operations. It is fully usable by AI agents, automation scripts, and developer tools.
## Quickstart

**1. Get your API keys** — visit https://porkbun.com/account/api

**2. Test connectivity**
```bash
curl https://api.porkbun.com/api/json/v3/ip
```
Returns your public IP. No credentials required.

**3. Check domain availability** (also verifies your credentials)
```bash
curl -X POST https://api.porkbun.com/api/json/v3/domain/checkDomain/example.com \
  -H "Content-Type: application/json" \
  -d '{"apikey":"your_api_key","secretapikey":"your_secret_key"}'
```
Returns `avail: "yes"/"no"` and `price` in USD. Returns an error if credentials are invalid.

**4. Register the domain** — convert `price` to pennies (e.g. $9.73 → 973) for `cost`
```bash
curl -X POST https://api.porkbun.com/api/json/v3/domain/create/example.com \
  -H "Content-Type: application/json" \
  -d '{"apikey":"your_api_key","secretapikey":"your_secret_key","cost":973,"agreeToTerms":"yes"}'
```

**5. Add a DNS record**
```bash
curl -X POST https://api.porkbun.com/api/json/v3/dns/create/example.com \
  -H "Content-Type: application/json" \
  -d '{"apikey":"your_api_key","secretapikey":"your_secret_key","type":"A","content":"1.2.3.4","ttl":"600"}'
```

## What you can build

- **Agentic domain registration** — Search availability and pricing across hundreds of TLDs, then register domains on behalf of users with a single API call
- **Automated DNS management** — Provision, update, and tear down DNS records as part of infrastructure automation or app deployment pipelines
- **Dynamic DNS clients** — Use `/ping` or `/ip` to detect IP address changes, then update A/AAAA records automatically
- **Domain portfolio tools** — List, monitor expiry, and configure auto-renewal settings across all domains in an account
- **SSL automation** — Retrieve free SSL certificate bundles for domains registered at Porkbun
- **Domain availability search** — Check availability and real-time pricing across all supported TLDs

## Agent-friendly design

- **Machine-readable error codes** — Every error response includes a `code` field (e.g. `INVALID_DOMAIN`, `INSUFFICIENT_FUNDS`) for programmatic branching
- **Header authentication** — Pass `X-API-Key` / `X-Secret-API-Key` as request headers; no JSON body required for read operations
- **GET support on read endpoints** — All read-only endpoints accept `GET` requests, making safe/idempotent operations distinguishable by HTTP method
- **Rate limit headers** — `X-RateLimit-Limit`, `X-RateLimit-Remaining`, and `X-RateLimit-Reset` on rate-limited endpoints enable intelligent backoff
- **Spec discovery** — Every API response includes `Link: <https://porkbun.com/api/json/v3/spec>; rel="describedby"` so clients can self-discover this spec

## Intended use

The Porkbun API is not a reseller service as defined under ICANN’s Registrar Accreditation Agreement (RAA). All domain registrations are processed directly by Porkbun as the registrar of record. The API is intended for managing domains within your own account or on behalf of clients, and does not establish a reseller relationship.

## Authentication

**1. JSON body (primary)** — Include `apikey` and `secretapikey` in the JSON request body. This is the standard method.

**2. Request headers** — Pass `X-API-Key: <apikey>` and `X-Secret-API-Key: <secretapikey>` as headers instead of in the body. Header auth takes effect only when no body credentials are present.

## HTTP status codes

- **400** — Request error (see `code` and `message` in response body)
- **403** — Additional authentication required (e.g. two-factor code)
- **429** — Rate limit exceeded (see `X-RateLimit-Reset` header for retry time)

## Error codes

Every error response includes a `code` string field alongside `status: "ERROR"` and `message`. Use `code` for programmatic error handling; use `message` for display to users.

**Authentication and protocol**

| Code | Meaning |
|------|---------|
| `INVALID_PROTOCOL` | Request was not made over HTTPS |
| `METHOD_NOT_ALLOWED` | HTTP method not allowed for this endpoint |
| `INVALID_OR_EMPTY_JSON` | Request body is missing or not valid JSON |
| `API_KEY_REQUIRED` | No API key or token was provided |
| `INVALID_API_KEYS_001` | API key and secret combination is invalid |
| `INVALID_TOKEN` | Bearer token is invalid or expired |
| `INVALID_USER` | Account associated with the API key was not found or is not active |

**Rate limiting**

| Code | Meaning |
|------|---------|
| `RATE_LIMIT_EXCEEDED` | Request rate limit reached; see `ttlRemaining` field and `X-RateLimit-Reset` header for reset time |

**Domain operations**

| Code | Meaning |
|------|---------|
| `INVALID_DOMAIN` | Domain parameter is invalid or not in your account |
| `DOMAIN_NOT_AVAILABLE` | Domain is not available for registration |
| `INSUFFICIENT_FUNDS` | Account credit is insufficient to complete the purchase |

**DNS operations**

| Code | Meaning |
|------|---------|
| `INVALID_TYPE` | DNS record type is not supported |
| `INVALID_RECORD_ID` | DNS record ID was not found or is not owned by your account |

Additional endpoint-specific codes may be returned; always check `message` for details.

## Rate limiting

Some endpoints are rate limited. When exceeded, the API returns HTTP 429 with a `RateLimitExceeded` response body. Fixed-limit endpoints (`/apikey/request`, `/apikey/retrieve`) also return `X-RateLimit-Limit`, `X-RateLimit-Remaining`, and `X-RateLimit-Reset` headers on every call.

## Servers
- **Production**: `https://api.porkbun.com/api/json/v3`
- **Production (IPv4 only — use this if your client is on an IPv6 network and needs to reach the API over IPv4)**: `https://api-ipv4.porkbun.com/api/json/v3`

## Authentication Schemes

### ApiKeyHeader
- Type: `apiKey`
- In: `header`
- Name: `X-API-Key`
- Description: Your API key. Use together with X-Secret-API-Key.

### SecretApiKeyHeader
- Type: `apiKey`
- In: `header`
- Name: `X-Secret-API-Key`
- Description: Your secret API key. Use together with X-API-Key.


## Endpoints

### POST /apikey/request

- Summary: Initiate an API key authorization request
- Operation ID: `apikeyRequest`
- Tags: API Key Management
- Security: none

Initiate an API key authorization flow. No credentials are required. Returns a `requestToken` and `authUrl` that the account holder must visit to approve the request. After approval, the application should poll `/apikey/retrieve` to get the public API key. The secret API key is shown only in the user's browser and must be pasted into the application manually.

**Rate limit:** 20 requests per IP per 3600 seconds.

#### Request Body

- Required: false

- Content-Type: `application/json`
```json
{"type":"object","properties":{"name":{"type":"string","maxLength":255,"description":"Optional human-readable name for the application requesting access."}}}
```

#### Responses

- Status `200`: Authorization request created
  - Content-Type: `application/json`
```json
{"type":"object","required":["requestToken"],"properties":{"status":{"type":"string","example":"SUCCESS"},"requestToken":{"type":"string","description":"Token used to poll `/apikey/retrieve` to check approval status."},"authUrl":{"type":"string","description":"URL the account holder must visit to approve the request","example":"https://porkbun.com/account/apiKeyApproval/abc123..."},"expiration":{"type":"string","description":"ISO datetime when this request expires (10 minutes from creation)","example":"2026-03-30 14:30:00"},"message":{"type":"string","description":"Human-readable instructions"}}}
```

- Status `400`: Validation error (e.g. app name too long)
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

- Status `429`: Rate limit exceeded
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/RateLimitExceeded"}
```

- Status `500`: Internal error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/apikey/request \
  -H 'Content-Type: application/json' \
  -d '[]'
```

### POST /apikey/retrieve

- Summary: Poll for API key approval
- Operation ID: `apikeyRetrieve`
- Tags: API Key Management
- Security: none

Poll to check whether the account holder has approved an API key authorization request. Returns `status: PENDING` while awaiting approval. On approval, returns the public API key. The secret API key is never transmitted via this endpoint — it is displayed in the user's browser and must be pasted into the application.

**Rate limit:** 120 requests per IP per 3600 seconds.

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"type":"object","required":["requestToken"],"properties":{"requestToken":{"type":"string","description":"The token returned by /apikey/request. Must be a 64-character lowercase hex string.","pattern":"^[a-f0-9]{64}$"}}}
```

#### Responses

- Status `200`: Approval status. `status` may be `SUCCESS` (approved), `PENDING` (awaiting user action), or `ERROR` (expired/denied/invalid).
  - Content-Type: `application/json`
```json
{"type":"object","properties":{"status":{"type":"string","enum":["SUCCESS","PENDING","ERROR"],"example":"SUCCESS"},"apikey":{"type":"string","description":"The approved public API key. Present only when status is SUCCESS."},"message":{"type":"string"},"code":{"type":"string","description":"Machine-readable error code. Present when status is ERROR."}}}
```

- Status `400`: Validation error or expired/denied token
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

- Status `403`: Request was denied by the account holder
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

- Status `429`: Rate limit exceeded
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/RateLimitExceeded"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/apikey/retrieve \
  -H 'Content-Type: application/json' \
  -d '[]'
```

### POST /dns/create/{domain}

- Summary: Create DNS record
- Operation ID: `dnsCreate`
- Tags: DNS
- Security: none

Create a new DNS record for a domain. The record ID is returned in the response.

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/CreateDnsRequest"}
```

#### Responses

- Status `200`: Record created
  - Content-Type: `application/json`
```json
{"type":"object","properties":{"status":{"type":"string","example":"SUCCESS"},"id":{"type":"string","description":"The numeric ID of the newly created record","example":"123456789"}}}
```

- Status `400`: Validation error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/dns/create/{domain} \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_...","name":"www","type":"A","content":"1.2.3.4","ttl":600,"prio":10}'
```

### POST /dns/createDnssecRecord/{domain}

- Summary: Create DNSSEC record
- Operation ID: `dnsCreateDnssecRecord`
- Tags: DNS
- Security: none

Create a DNSSEC DS or key record at the registry. DNSSEC requirements vary by registry — `keyTag`, `alg`, `digestType`, and `digest` are the minimum required fields. Key data fields are optional and will be omitted if not accepted by the registry.

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/CreateDnssecRequest"}
```

#### Responses

- Status `200`: DNSSEC record created
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/BasicResponse"}
```

- Status `400`: Error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/dns/createDnssecRecord/{domain} \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_...","keyTag":"12345","alg":"13","digestType":"2","digest":"ABCD1234..."}'
```

### POST /dns/delete/{domain}/{id}

- Summary: Delete DNS record by ID
- Operation ID: `dnsDelete`
- Tags: DNS
- Security: none

Delete a specific DNS record. SOA and default Porkbun NS records cannot be deleted.

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |
| id | path | yes | string | Numeric DNS record ID |

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/AuthRequest"}
```

#### Responses

- Status `200`: Deleted
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/BasicResponse"}
```

- Status `400`: Error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/dns/delete/{domain}/{id} \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_..."}'
```

### POST /dns/deleteByNameType/{domain}/{type}/{subdomain}

- Summary: Delete DNS records by name and type
- Operation ID: `dnsDeleteByNameType`
- Tags: DNS
- Security: none

Delete all DNS records matching the given subdomain and type. SOA and NS records cannot be deleted with this method (use delete by ID instead).

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |
| type | path | yes | string |  |
| subdomain | path | no | string | Subdomain portion only. Omit or leave empty for root domain records. |

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/AuthRequest"}
```

#### Responses

- Status `200`: Deleted
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/BasicResponse"}
```

- Status `400`: Error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/dns/deleteByNameType/{domain}/{type}/{subdomain} \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_..."}'
```

### POST /dns/deleteDnssecRecord/{domain}/{keytag}

- Summary: Delete DNSSEC record
- Operation ID: `dnsDeleteDnssecRecord`
- Tags: DNS
- Security: none

Delete a DNSSEC record from the registry by key tag. Note: most registries delete all records matching the key data, not only the record with the specified key tag.

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |
| keytag | path | yes | string | The DNSSEC key tag value |

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/AuthRequest"}
```

#### Responses

- Status `200`: Deleted
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/BasicResponse"}
```

- Status `400`: Error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/dns/deleteDnssecRecord/{domain}/{keytag} \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_..."}'
```

### POST /dns/edit/{domain}/{id}

- Summary: Edit DNS record by ID
- Operation ID: `dnsEdit`
- Tags: DNS
- Security: none

Edit a specific DNS record by its numeric ID. SOA and default Porkbun NS records cannot be edited.

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |
| id | path | yes | string | Numeric DNS record ID |

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/EditDnsRequest"}
```

#### Responses

- Status `200`: Record updated
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/BasicResponse"}
```

- Status `400`: Validation error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/dns/edit/{domain}/{id} \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_...","type":"A","content":"5.6.7.8","ttl":0,"prio":0}'
```

### POST /dns/editByNameType/{domain}/{type}/{subdomain}

- Summary: Edit DNS records by name and type
- Operation ID: `dnsEditByNameType`
- Tags: DNS
- Security: none

Replace the content of all records matching the given subdomain and type. SOA and NS records cannot be edited with this method (use edit by ID instead).

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |
| type | path | yes | string |  |
| subdomain | path | no | string | Subdomain portion only. Omit or leave empty for root domain records. |

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/EditDnsByNameTypeRequest"}
```

#### Responses

- Status `200`: Records updated
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/BasicResponse"}
```

- Status `400`: Validation error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/dns/editByNameType/{domain}/{type}/{subdomain} \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_...","content":"5.6.7.8","ttl":0,"prio":0}'
```

### GET /dns/getDnssecRecords/{domain}

- Summary: Get DNSSEC records
- Operation ID: `getDnssecRecords`
- Tags: DNS
- Security: none

Retrieve DNSSEC records associated with the domain at the registry. Supports both GET (with header auth) and POST (with body or header auth).

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |
| X-API-Key | header | no | string | API key header auth (use with X-Secret-API-Key) |
| X-Secret-API-Key | header | no | string | Secret API key header auth (use with X-API-Key) |

#### Responses

- Status `200`: DNSSEC records keyed by key tag
  - Content-Type: `application/json`
```json
{"type":"object","properties":{"status":{"type":"string","example":"SUCCESS"},"records":{"type":"object","description":"Object keyed by key tag value. Each value is an object containing the DNSSEC data fields.","additionalProperties":{"type":"object","properties":{"keyTag":{"type":"string"},"alg":{"type":"string"},"digestType":{"type":"string"},"digest":{"type":"string"},"pubKey":{"type":"string","description":"Present for key data records"}}}}}}
```

- Status `400`: Error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl 'https://api.porkbun.com/api/json/v3/dns/getDnssecRecords/{domain}' \
  -H 'X-API-Key: pk1_...' \
  -H 'X-Secret-API-Key: sk1_...'
```

### POST /dns/getDnssecRecords/{domain}

- Summary: Get DNSSEC records
- Operation ID: `dnsGetDnssecRecords`
- Tags: DNS
- Security: none

Retrieve DNSSEC records associated with the domain at the registry. Supports both GET (with header auth) and POST (with body or header auth).

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/AuthRequest"}
```

#### Responses

- Status `200`: DNSSEC records keyed by key tag
  - Content-Type: `application/json`
```json
{"type":"object","properties":{"status":{"type":"string","example":"SUCCESS"},"records":{"type":"object","description":"Object keyed by key tag value. Each value is an object containing the DNSSEC data fields.","additionalProperties":{"type":"object","properties":{"keyTag":{"type":"string"},"alg":{"type":"string"},"digestType":{"type":"string"},"digest":{"type":"string"},"pubKey":{"type":"string","description":"Present for key data records"}}}}}}
```

- Status `400`: Error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/dns/getDnssecRecords/{domain} \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_..."}'
```

### GET /dns/retrieve/{domain}

- Summary: Retrieve all DNS records
- Operation ID: `getDnsRecords`
- Tags: DNS
- Security: none

Retrieve all editable DNS records for a domain. SOA records and Porkbun default NS records are excluded. Supports both GET (with header auth) and POST (with body or header auth).

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |
| X-API-Key | header | no | string | API key header auth (use with X-Secret-API-Key) |
| X-Secret-API-Key | header | no | string | Secret API key header auth (use with X-API-Key) |

#### Responses

- Status `200`: DNS records
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/DnsRecordsResponse"}
```

- Status `400`: Error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl 'https://api.porkbun.com/api/json/v3/dns/retrieve/{domain}' \
  -H 'X-API-Key: pk1_...' \
  -H 'X-Secret-API-Key: sk1_...'
```

### POST /dns/retrieve/{domain}

- Summary: Retrieve all DNS records
- Operation ID: `dnsRetrieve`
- Tags: DNS
- Security: none

Retrieve all editable DNS records for a domain. SOA records and Porkbun default NS records are excluded. Supports both GET (with header auth) and POST (with body or header auth).

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/AuthRequest"}
```

#### Responses

- Status `200`: DNS records
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/DnsRecordsResponse"}
```

- Status `400`: Error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/dns/retrieve/{domain} \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_..."}'
```

### GET /dns/retrieve/{domain}/{id}

- Summary: Retrieve DNS record by ID
- Operation ID: `getDnsRecordById`
- Tags: DNS
- Security: none

Retrieve a specific DNS record by its numeric ID. Supports both GET (with header auth) and POST (with body or header auth).

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |
| id | path | yes | string | Numeric DNS record ID |
| X-API-Key | header | no | string | API key header auth (use with X-Secret-API-Key) |
| X-Secret-API-Key | header | no | string | Secret API key header auth (use with X-API-Key) |

#### Responses

- Status `200`: DNS record(s) matching the ID
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/DnsRecordsResponse"}
```

- Status `400`: Error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl 'https://api.porkbun.com/api/json/v3/dns/retrieve/{domain}/{id}' \
  -H 'X-API-Key: pk1_...' \
  -H 'X-Secret-API-Key: sk1_...'
```

### POST /dns/retrieve/{domain}/{id}

- Summary: Retrieve DNS record by ID
- Operation ID: `dnsRetrieveById`
- Tags: DNS
- Security: none

Retrieve a specific DNS record by its numeric ID. Supports both GET (with header auth) and POST (with body or header auth).

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |
| id | path | yes | string | Numeric DNS record ID |

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/AuthRequest"}
```

#### Responses

- Status `200`: DNS record(s) matching the ID
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/DnsRecordsResponse"}
```

- Status `400`: Error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/dns/retrieve/{domain}/{id} \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_..."}'
```

### GET /dns/retrieveByNameType/{domain}/{type}/{subdomain}

- Summary: Retrieve DNS records by name and type
- Operation ID: `getDnsRecordsByNameType`
- Tags: DNS
- Security: none

Retrieve all DNS records for a domain that match a specific subdomain and record type. Omit `subdomain` (or leave the path segment empty) to query the root domain. Supports both GET (with header auth) and POST (with body or header auth).

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |
| type | path | yes | string | DNS record type (A, AAAA, CNAME, MX, TXT, etc.) |
| subdomain | path | no | string | Subdomain portion only. Omit or leave empty for root domain records. |
| X-API-Key | header | no | string | API key header auth (use with X-Secret-API-Key) |
| X-Secret-API-Key | header | no | string | Secret API key header auth (use with X-API-Key) |

#### Responses

- Status `200`: Matching DNS records
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/DnsRecordsResponse"}
```

- Status `400`: Error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl 'https://api.porkbun.com/api/json/v3/dns/retrieveByNameType/{domain}/{type}/{subdomain}' \
  -H 'X-API-Key: pk1_...' \
  -H 'X-Secret-API-Key: sk1_...'
```

### POST /dns/retrieveByNameType/{domain}/{type}/{subdomain}

- Summary: Retrieve DNS records by name and type
- Operation ID: `dnsRetrieveByNameType`
- Tags: DNS
- Security: none

Retrieve all DNS records for a domain that match a specific subdomain and record type. Omit `subdomain` (or leave the path segment empty) to query the root domain. Supports both GET (with header auth) and POST (with body or header auth).

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |
| type | path | yes | string | DNS record type (A, AAAA, CNAME, MX, TXT, etc.) |
| subdomain | path | no | string | Subdomain portion only. Omit or leave empty for root domain records. |

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/AuthRequest"}
```

#### Responses

- Status `200`: Matching DNS records
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/DnsRecordsResponse"}
```

- Status `400`: Error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/dns/retrieveByNameType/{domain}/{type}/{subdomain} \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_..."}'
```

### POST /domain/addUrlForward/{domain}

- Summary: Add URL forward
- Operation ID: `domainAddUrlForward`
- Tags: Domain
- Security: none

Add a URL forward for a domain or subdomain.

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/AddUrlForwardRequest"}
```

#### Responses

- Status `200`: Forward added
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/BasicResponse"}
```

- Status `400`: Validation error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/domain/addUrlForward/{domain} \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_...","subdomain":"www","location":"https:\/\/destination.example.com","type":"temporary","includePath":"yes","wildcard":"yes"}'
```

### POST /domain/checkDomain/{domain}

- Summary: Check domain availability
- Operation ID: `domainCheckDomain`
- Tags: Domain
- Security: none

Check if a domain is available for registration and retrieve current pricing. Includes registration, renewal, and transfer prices.

**Rate limit:** Configurable per API key. Default is 1 check per 10 seconds per account. Rate limit usage is returned in the `limits` field of the response.

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/AuthRequest"}
```

#### Responses

- Status `200`: Availability and pricing
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/CheckDomainResponse"}
```

- Status `400`: Rate limit exceeded (returned as 200 body with status ERROR) or other error. See also 429.
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

- Status `429`: Rate limit exceeded
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/RateLimitExceeded"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/domain/checkDomain/{domain} \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_..."}'
```

### POST /domain/create/{domain}

- Summary: Register a domain
- Operation ID: `domainCreate`
- Tags: Domain
- Security: none

Register a domain using account credit. Requirements:
- Account email and phone must be verified
- Account must have sufficient credit
- `agreeToTerms` must be `'yes'` or `'1'`
- `cost` must equal the current price for the domain's minimum registration duration (in pennies)
- Account must have placed at least one previous domain registration
- Premium domains cannot be registered via API

Registrations are always for the registry-minimum duration (usually 1 year).

**Rate limits (both apply):**
- Attempt limit (default: 1 attempt per 10 seconds per account)
- Success limit (default: 10 successful registrations per 86400 seconds per account)

Both limits are configurable per API key and their current values are returned in the `limits` field of the response.

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/CreateDomainRequest"}
```

#### Responses

- Status `200`: Domain registered successfully
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/CreateDomainResponse"}
```

- Status `400`: Validation, fraud, or rate-limit error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

- Status `429`: Rate limit exceeded
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/RateLimitExceeded"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/domain/create/{domain} \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_...","cost":0,"agreeToTerms":"yes"}'
```

### POST /domain/createGlue/{domain}/{subdomain}

- Summary: Create glue record
- Operation ID: `domainCreateGlue`
- Tags: Domain
- Security: none

Create a glue record (host object) for a nameserver hostname under the domain. Use this when you want to host a nameserver at a subdomain of the domain itself (e.g. ns1.example.com).

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |
| subdomain | path | yes | string | The subdomain portion only (e.g. 'ns1' for ns1.example.com) |

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/GlueRecordRequest"}
```

#### Responses

- Status `200`: Created
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/BasicResponse"}
```

- Status `400`: Error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/domain/createGlue/{domain}/{subdomain} \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_...","ips":["1.2.3.4","2001:db8::1"]}'
```

### POST /domain/deleteGlue/{domain}/{subdomain}

- Summary: Delete glue record
- Operation ID: `domainDeleteGlue`
- Tags: Domain
- Security: none

Delete a glue record (host object) for a subdomain.

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |
| subdomain | path | yes | string |  |

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/AuthRequest"}
```

#### Responses

- Status `200`: Deleted
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/BasicResponse"}
```

- Status `400`: Error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/domain/deleteGlue/{domain}/{subdomain} \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_..."}'
```

### POST /domain/deleteUrlForward/{domain}/{id}

- Summary: Delete URL forward
- Operation ID: `domainDeleteUrlForward`
- Tags: Domain
- Security: none

Delete a specific URL forward by ID.

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |
| id | path | yes | string | URL forward record ID |

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/AuthRequest"}
```

#### Responses

- Status `200`: Deleted
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/BasicResponse"}
```

- Status `400`: Error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/domain/deleteUrlForward/{domain}/{id} \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_..."}'
```

### GET /domain/getGlue/{domain}

- Summary: Get glue records
- Operation ID: `getDomainGlue`
- Tags: Domain
- Security: none

Retrieve all glue records (host objects) registered under the domain. Supports both GET (with header auth) and POST (with body or header auth).

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |
| X-API-Key | header | no | string | API key header auth (use with X-Secret-API-Key) |
| X-Secret-API-Key | header | no | string | Secret API key header auth (use with X-API-Key) |

#### Responses

- Status `200`: Glue records
  - Content-Type: `application/json`
```json
{"type":"object","properties":{"status":{"type":"string","example":"SUCCESS"},"hosts":{"type":"array","description":"Array of [hostname, ipAddresses] tuples. Each element is a two-item array: index 0 is the full hostname (string), index 1 is an object with `v4` (array of IPv4 strings) and `v6` (array of IPv6 strings). Example: `[\"ns1.example.com\", {\"v4\": [\"1.2.3.4\"], \"v6\": []}]`","items":{"type":"array","description":"Two-element array: [0] full hostname string, [1] IP addresses object","prefixItems":[{"type":"string","description":"Full hostname (e.g. 'ns1.example.com')"},{"type":"object","description":"IP address object","properties":{"v4":{"type":"array","items":{"type":"string"},"description":"Array of IPv4 address strings"},"v6":{"type":"array","items":{"type":"string"},"description":"Array of IPv6 address strings"}}}]}}}}
```

- Status `400`: Error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl 'https://api.porkbun.com/api/json/v3/domain/getGlue/{domain}' \
  -H 'X-API-Key: pk1_...' \
  -H 'X-Secret-API-Key: sk1_...'
```

### POST /domain/getGlue/{domain}

- Summary: Get glue records
- Operation ID: `domainGetGlue`
- Tags: Domain
- Security: none

Retrieve all glue records (host objects) registered under the domain. Supports both GET (with header auth) and POST (with body or header auth).

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/AuthRequest"}
```

#### Responses

- Status `200`: Glue records
  - Content-Type: `application/json`
```json
{"type":"object","properties":{"status":{"type":"string","example":"SUCCESS"},"hosts":{"type":"array","description":"Array of [hostname, ipAddresses] tuples. Each element is a two-item array: index 0 is the full hostname (string), index 1 is an object with `v4` (array of IPv4 strings) and `v6` (array of IPv6 strings). Example: `[\"ns1.example.com\", {\"v4\": [\"1.2.3.4\"], \"v6\": []}]`","items":{"type":"array","description":"Two-element array: [0] full hostname string, [1] IP addresses object","prefixItems":[{"type":"string","description":"Full hostname (e.g. 'ns1.example.com')"},{"type":"object","description":"IP address object","properties":{"v4":{"type":"array","items":{"type":"string"},"description":"Array of IPv4 address strings"},"v6":{"type":"array","items":{"type":"string"},"description":"Array of IPv6 address strings"}}}]}}}}
```

- Status `400`: Error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/domain/getGlue/{domain} \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_..."}'
```

### GET /domain/getNs/{domain}

- Summary: Get nameservers
- Operation ID: `getDomainNs`
- Tags: Domain
- Security: none

Retrieve the authoritative nameservers listed at the registry for the domain. Supports both GET (with header auth) and POST (with body or header auth).

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |
| X-API-Key | header | no | string | API key header auth (use with X-Secret-API-Key) |
| X-Secret-API-Key | header | no | string | Secret API key header auth (use with X-API-Key) |

#### Responses

- Status `200`: Nameserver list
  - Content-Type: `application/json`
```json
{"type":"object","properties":{"status":{"type":"string","example":"SUCCESS"},"ns":{"type":"array","items":{"type":"string"},"example":["ns1.porkbun.com","ns2.porkbun.com"]}}}
```

- Status `400`: Error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl 'https://api.porkbun.com/api/json/v3/domain/getNs/{domain}' \
  -H 'X-API-Key: pk1_...' \
  -H 'X-Secret-API-Key: sk1_...'
```

### POST /domain/getNs/{domain}

- Summary: Get nameservers
- Operation ID: `domainGetNs`
- Tags: Domain
- Security: none

Retrieve the authoritative nameservers listed at the registry for the domain. Supports both GET (with header auth) and POST (with body or header auth).

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/AuthRequest"}
```

#### Responses

- Status `200`: Nameserver list
  - Content-Type: `application/json`
```json
{"type":"object","properties":{"status":{"type":"string","example":"SUCCESS"},"ns":{"type":"array","items":{"type":"string"},"example":["ns1.porkbun.com","ns2.porkbun.com"]}}}
```

- Status `400`: Error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/domain/getNs/{domain} \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_..."}'
```

### GET /domain/getUrlForwarding/{domain}

- Summary: List URL forwards
- Operation ID: `getDomainUrlForwarding`
- Tags: Domain
- Security: none

Retrieve all active URL forwards for a domain. Supports both GET (with header auth) and POST (with body or header auth).

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |
| X-API-Key | header | no | string | API key header auth (use with X-Secret-API-Key) |
| X-Secret-API-Key | header | no | string | Secret API key header auth (use with X-API-Key) |

#### Responses

- Status `200`: URL forward list
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/GetUrlForwardingResponse"}
```

- Status `400`: Error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl 'https://api.porkbun.com/api/json/v3/domain/getUrlForwarding/{domain}' \
  -H 'X-API-Key: pk1_...' \
  -H 'X-Secret-API-Key: sk1_...'
```

### POST /domain/getUrlForwarding/{domain}

- Summary: List URL forwards
- Operation ID: `domainGetUrlForwarding`
- Tags: Domain
- Security: none

Retrieve all active URL forwards for a domain. Supports both GET (with header auth) and POST (with body or header auth).

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/AuthRequest"}
```

#### Responses

- Status `200`: URL forward list
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/GetUrlForwardingResponse"}
```

- Status `400`: Error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/domain/getUrlForwarding/{domain} \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_..."}'
```

### GET /domain/listAll

- Summary: List all domains
- Operation ID: `getDomains`
- Tags: Domain
- Security: none

Retrieve all domain names in the account. Results are returned in chunks of up to 1000 domains, ordered by expiration date ascending. Use `start` to paginate. Paginate by incrementing `start` by 1000. An empty `domains` array indicates all domains have been retrieved. Supports both GET (with header auth) and POST (with body or header auth).

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| X-API-Key | header | no | string | API key header auth (use with X-Secret-API-Key) |
| X-Secret-API-Key | header | no | string | Secret API key header auth (use with X-API-Key) |
| start | query | no | integer | Zero-based offset for pagination. Returns up to 1000 domains per call. If the response contains fewer than 1000 domains, all results have been returned. |
| includeLabels | query | no | string | Return label metadata for each domain. Defaults to no. |

#### Responses

- Status `200`: Domain list
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/DomainListAllResponse"}
```

- Status `400`: Authentication error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl 'https://api.porkbun.com/api/json/v3/domain/listAll' \
  -H 'X-API-Key: pk1_...' \
  -H 'X-Secret-API-Key: sk1_...'
```

### POST /domain/listAll

- Summary: List all domains
- Operation ID: `listDomains`
- Tags: Domain
- Security: none

Retrieve all domain names in the account. Results are returned in chunks of up to 1000 domains, ordered by expiration date ascending. Use `start` to paginate. Paginate by incrementing `start` by 1000. An empty `domains` array indicates all domains have been retrieved. Supports both GET (with header auth) and POST (with body or header auth).

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ListAllRequest"}
```

#### Responses

- Status `200`: Domain list
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/DomainListAllResponse"}
```

- Status `400`: Authentication error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/domain/listAll \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_...","start":0,"includeLabels":"yes"}'
```

### POST /domain/updateAutoRenew/{domain}

- Summary: Update auto-renew setting
- Operation ID: `domainUpdateAutoRenew`
- Tags: Domain
- Security: none

Update the auto-renew setting for one or more domains. The domain can be passed in the URL path or in the `domains` array in the request body (or both). Both are combined and deduplicated.

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | no | string | Optional single domain in URL. Omit or use `/domain/updateAutoRenew/` (without trailing domain) when using the `domains` body array instead. |

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/UpdateAutoRenewRequest"}
```

#### Responses

- Status `200`: Per-domain results
  - Content-Type: `application/json`
```json
{"type":"object","properties":{"status":{"type":"string","example":"SUCCESS"},"results":{"type":"object","description":"Object keyed by domain name","additionalProperties":{"type":"object","properties":{"status":{"type":"string"},"message":{"type":"string"}}}}}}
```

- Status `400`: Validation error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/domain/updateAutoRenew/{domain} \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_...","status":"on","domains":[]}'
```

### POST /domain/updateGlue/{domain}/{subdomain}

- Summary: Update glue record
- Operation ID: `domainUpdateGlue`
- Tags: Domain
- Security: none

Update the IP addresses of a glue record. All existing IP addresses are replaced with the supplied list.

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |
| subdomain | path | yes | string |  |

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/GlueRecordRequest"}
```

#### Responses

- Status `200`: Updated
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/BasicResponse"}
```

- Status `400`: Error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/domain/updateGlue/{domain}/{subdomain} \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_...","ips":["1.2.3.4","2001:db8::1"]}'
```

### POST /domain/updateNs/{domain}

- Summary: Update nameservers
- Operation ID: `domainUpdateNs`
- Tags: Domain
- Security: none

Update the nameservers for the domain at the registry.

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/UpdateNsRequest"}
```

#### Responses

- Status `200`: Update result
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/BasicResponse"}
```

- Status `400`: Error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/domain/updateNs/{domain} \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_...","ns":["ns1.example.com","ns2.example.com"]}'
```

### POST /email/setPassword

- Summary: Set email hosting password
- Operation ID: `emailSetPassword`
- Tags: Email Hosting
- Security: none

Set the password for an email hosting account associated with a domain managed by your API key.

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"allOf":[{"$ref":"#/components/schemas/AuthRequest"},{"type":"object","required":["emailAddress","password"],"properties":{"emailAddress":{"type":"string","description":"The full email address (e.g. user@example.com)","example":"user@example.com"},"password":{"type":"string","description":"The new password. Must pass Porkbun password validation rules."}}}]}
```

#### Responses

- Status `200`: Password updated
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/BasicResponse"}
```

- Status `400`: Validation or permission error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/email/setPassword \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_...","emailAddress":"user@example.com"}'
```

### GET /ip

- Summary: Get caller IP address
- Operation ID: `getIp`
- Tags: Utility
- Security: none

Returns the caller's public IP address. No credentials required. Use the `api-ipv4.porkbun.com` hostname if you need to force an IPv4 address.

#### Responses

- Status `200`: Caller IP address
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/IpResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl 'https://api.porkbun.com/api/json/v3/ip'
```

### POST /ip

- Summary: Get caller IP address
- Operation ID: `ipPost`
- Tags: Utility
- Security: none

Returns the caller's public IP address. No credentials required. Use the `api-ipv4.porkbun.com` hostname if you need to force an IPv4 address.

#### Request Body

- Required: false

- Content-Type: `application/json`
```json
{"type":"object"}
```

#### Responses

- Status `200`: Caller IP address
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/IpResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/ip \
  -H 'Content-Type: application/json' \
  -d '[]'
```

### GET /marketplace/getAll

- Summary: List marketplace domains
- Operation ID: `getMarketplaceListings`
- Tags: Marketplace
- Security: none

Retrieve a list of domains currently listed on the Porkbun marketplace. Token-based access is not supported. Results are paginated; up to 5000 domains can be returned per request. Supports both GET (with header auth) and POST (with body or header auth).

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| X-API-Key | header | no | string | API key header auth (use with X-Secret-API-Key) |
| X-Secret-API-Key | header | no | string | Secret API key header auth (use with X-API-Key) |
| start | query | no | integer | Offset to start retrieving domains from (default: 0) |
| limit | query | no | integer | Number of domains to return (default: 1000, max: 5000) |

#### Responses

- Status `200`: Marketplace domain list
  - Content-Type: `application/json`
```json
{"type":"object","properties":{"status":{"type":"string","example":"SUCCESS"},"count":{"type":"integer","description":"Number of domains returned in this response"},"domains":{"type":"array","items":{"type":"object","properties":{"create_date":{"type":"string","description":"Date the listing was created"},"domain":{"type":"string"},"tld":{"type":"string"},"price":{"type":"number","description":"Listing price in USD"}}}}}}
```

- Status `400`: Authentication error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl 'https://api.porkbun.com/api/json/v3/marketplace/getAll' \
  -H 'X-API-Key: pk1_...' \
  -H 'X-Secret-API-Key: sk1_...'
```

### POST /marketplace/getAll

- Summary: List marketplace domains
- Operation ID: `listMarketplaceListings`
- Tags: Marketplace
- Security: none

Retrieve a list of domains currently listed on the Porkbun marketplace. Token-based access is not supported. Results are paginated; up to 5000 domains can be returned per request. Supports both GET (with header auth) and POST (with body or header auth).

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"allOf":[{"$ref":"#/components/schemas/AuthRequest"},{"type":"object","properties":{"start":{"type":"integer","description":"Offset to start retrieving domains from (default: 0)","example":0},"limit":{"type":"integer","description":"Number of domains to return (default: 1000, max: 5000)","example":1000,"maximum":5000}}}]}
```

#### Responses

- Status `200`: Marketplace domain list
  - Content-Type: `application/json`
```json
{"type":"object","properties":{"status":{"type":"string","example":"SUCCESS"},"count":{"type":"integer","description":"Number of domains returned in this response"},"domains":{"type":"array","items":{"type":"object","properties":{"create_date":{"type":"string","description":"Date the listing was created"},"domain":{"type":"string"},"tld":{"type":"string"},"price":{"type":"number","description":"Listing price in USD"}}}}}}
```

- Status `400`: Authentication error
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/marketplace/getAll \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_...","start":0,"limit":1000}'
```

### GET /ping

- Summary: Test credentials and get caller IP
- Operation ID: `pingGet`
- Tags: Utility
- Security: none

Returns the caller's public IP address. Optionally validates API credentials.

- **No credentials supplied** — returns IP only.
- **Valid credentials supplied** — returns IP with `credentialsValid: true`.
- **Invalid credentials supplied** — returns an error.

Useful for agents and clients to verify their API key is working before making other calls.

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| X-API-Key | header | no | string | API key header auth (use with X-Secret-API-Key) |
| X-Secret-API-Key | header | no | string | Secret API key header auth (use with X-API-Key) |

#### Responses

- Status `200`: Success — IP returned, credentials validated if supplied
  - Content-Type: `application/json`
```json
{"allOf":[{"$ref":"#/components/schemas/IpResponse"},{"type":"object","properties":{"credentialsValid":{"type":"boolean","description":"Present and true when valid credentials were supplied"}}}]}
```

- Status `400`: Invalid credentials
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl 'https://api.porkbun.com/api/json/v3/ping'
```

### POST /ping

- Summary: Test credentials and get caller IP
- Operation ID: `ping`
- Tags: Utility
- Security: none

Returns the caller's public IP address. Optionally validates API credentials.

- **No credentials supplied** — returns IP only.
- **Valid credentials supplied** — returns IP with `credentialsValid: true`.
- **Invalid credentials supplied** — returns an error.

Useful for agents and clients to verify their API key is working before making other calls.

#### Request Body

- Required: false

- Content-Type: `application/json`
```json
{"type":"object","properties":{"apikey":{"type":"string","description":"Your API key (optional)"},"secretapikey":{"type":"string","description":"Your secret API key (optional, required if apikey is supplied)"}}}
```

#### Responses

- Status `200`: Success — IP returned, credentials validated if supplied
  - Content-Type: `application/json`
```json
{"allOf":[{"$ref":"#/components/schemas/IpResponse"},{"type":"object","properties":{"credentialsValid":{"type":"boolean","description":"Present and true when valid credentials were supplied"}}}]}
```

- Status `400`: Invalid credentials
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/ping \
  -H 'Content-Type: application/json' \
  -d '[]'
```

### GET /pricing/get

- Summary: Retrieve domain pricing (public)
- Operation ID: `getPricingGet`
- Tags: Pricing
- Security: none

Retrieve default domain pricing information for all supported TLDs. Does not require authentication. Prices are in US dollars.

This GET form returns pricing for all TLDs. To filter by specific TLDs, use `POST /pricing/get` with a `tlds` array in the request body.

#### Responses

- Status `200`: Pricing data
  - Content-Type: `application/json`
```json
{"type":"object","properties":{"status":{"type":"string","example":"SUCCESS"},"pricing":{"type":"object","description":"Object keyed by TLD string","additionalProperties":{"type":"object","properties":{"registration":{"type":"string","example":"9.73"},"renewal":{"type":"string","example":"9.73"},"transfer":{"type":"string","example":"9.73"},"specialType":{"type":"string","description":"Present only for special TLDs (e.g. 'handshake')"},"coupons":{"type":"object","description":"Active coupon codes for this TLD, keyed by product type","additionalProperties":{"type":"object","properties":{"code":{"type":"string"},"max_per_user":{"type":"integer"},"first_year_only":{"type":"string","enum":["yes","no"]},"type":{"type":"string"},"amount":{"type":"number"}}}}}}}}}
```

#### Code Samples

- curl (curl)
```curl
curl 'https://api.porkbun.com/api/json/v3/pricing/get'
```

### POST /pricing/get

- Summary: Retrieve domain pricing (public)
- Operation ID: `getPricing`
- Tags: Pricing
- Security: none

Retrieve default domain pricing information for all supported TLDs. Does not require authentication. Prices are in US dollars.

#### Request Body

- Required: false

- Content-Type: `application/json`
```json
{"type":"object","properties":{"tlds":{"type":"array","items":{"type":"string"},"description":"Optional array of TLDs to filter results. If omitted, all supported TLDs are returned."}}}
```

#### Responses

- Status `200`: Pricing data
  - Content-Type: `application/json`
```json
{"type":"object","properties":{"status":{"type":"string","example":"SUCCESS"},"pricing":{"type":"object","description":"Object keyed by TLD string","additionalProperties":{"type":"object","properties":{"registration":{"type":"string","example":"9.73"},"renewal":{"type":"string","example":"9.73"},"transfer":{"type":"string","example":"9.73"},"specialType":{"type":"string","description":"Present only for special TLDs (e.g. 'handshake')"},"coupons":{"type":"object","description":"Active coupon codes for this TLD, keyed by product type","additionalProperties":{"type":"object","properties":{"code":{"type":"string"},"max_per_user":{"type":"integer"},"first_year_only":{"type":"string","enum":["yes","no"]},"type":{"type":"string"},"amount":{"type":"number"}}}}}}}}}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/pricing/get \
  -H 'Content-Type: application/json' \
  -d '{"tlds":[]}'
```

### GET /ssl/retrieve/{domain}

- Summary: Retrieve SSL bundle
- Operation ID: `getSslRetrieve`
- Tags: SSL
- Security: none

Retrieve the Let's Encrypt SSL certificate bundle for a domain. The certificate must already be issued (status HAVECERT). Token-based access is not supported for this endpoint. Supports both GET (with header auth) and POST (with body or header auth).

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |
| X-API-Key | header | no | string | API key header auth (use with X-Secret-API-Key) |
| X-Secret-API-Key | header | no | string | Secret API key header auth (use with X-API-Key) |

#### Responses

- Status `200`: SSL certificate bundle
  - Content-Type: `application/json`
```json
{"type":"object","properties":{"status":{"type":"string","example":"SUCCESS"},"certificatechain":{"type":"string","description":"The full PEM-encoded certificate chain (certificate + intermediates)"},"privatekey":{"type":"string","description":"The PEM-encoded private key"},"publickey":{"type":"string","description":"The PEM-encoded public key"}}}
```

- Status `400`: Error (e.g. certificate not ready, domain not found)
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl 'https://api.porkbun.com/api/json/v3/ssl/retrieve/{domain}' \
  -H 'X-API-Key: pk1_...' \
  -H 'X-Secret-API-Key: sk1_...'
```

### POST /ssl/retrieve/{domain}

- Summary: Retrieve SSL bundle
- Operation ID: `sslRetrieve`
- Tags: SSL
- Security: none

Retrieve the Let's Encrypt SSL certificate bundle for a domain. The certificate must already be issued (status HAVECERT). Token-based access is not supported for this endpoint. Supports both GET (with header auth) and POST (with body or header auth).

#### Parameters

| Name | In | Required | Type | Description |
|---|---|---|---|---|
| domain | path | yes | string |  |

#### Request Body

- Required: true

- Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/AuthRequest"}
```

#### Responses

- Status `200`: SSL certificate bundle
  - Content-Type: `application/json`
```json
{"type":"object","properties":{"status":{"type":"string","example":"SUCCESS"},"certificatechain":{"type":"string","description":"The full PEM-encoded certificate chain (certificate + intermediates)"},"privatekey":{"type":"string","description":"The PEM-encoded private key"},"publickey":{"type":"string","description":"The PEM-encoded public key"}}}
```

- Status `400`: Error (e.g. certificate not ready, domain not found)
  - Content-Type: `application/json`
```json
{"$ref":"#/components/schemas/ErrorResponse"}
```

#### Code Samples

- curl (curl)
```curl
curl -X POST https://api.porkbun.com/api/json/v3/ssl/retrieve/{domain} \
  -H 'Content-Type: application/json' \
  -d '{"apikey":"pk1_...","secretapikey":"sk1_..."}'
```

## Component Schemas
- `AddUrlForwardRequest`
- `AuthRequest`
- `BasicResponse`
- `CheckDomainResponse`
- `CreateDnsRequest`
- `CreateDnssecRequest`
- `CreateDomainRequest`
- `CreateDomainResponse`
- `DnsRecordsResponse`
- `DomainListAllResponse`
- `EditDnsByNameTypeRequest`
- `EditDnsRequest`
- `ErrorResponse`
- `GetUrlForwardingResponse`
- `GlueRecordRequest`
- `IpResponse`
- `ListAllRequest`
- `RateLimitExceeded`
- `UpdateAutoRenewRequest`
- `UpdateNsRequest`

## Source
- Interactive docs: https://porkbun.com/api/json/v3/documentation
- OpenAPI spec: https://porkbun.com/api/json/v3/spec

