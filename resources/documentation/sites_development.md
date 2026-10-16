# Sites Development
## Introduction
Serveronet Client serves static files. You can publish HTML, JS and images as content of your site.  
Client exposes an API Backend for every site.
## Site API
To access the Site API documentation, browse to this path while on a Site 

```/sn_client_resources/site_api_docs/index.html```

[Site API Documentation ?](http://visitor-control-panel.snet.localhost:15080/sn_client_resources/site_api_docs/index.html)

Values from the database can be queried with Site API backend using query parameters.  
Pass `query_parameters` as a JSON object (or a JSON string) in the request body.  
Following query parameters are supported:

| Parameter | JSON shape | Behaviour |
| --- | --- | --- |
| `table` | `"posts"` | Required. Name of the table to query. |
| `where` | `[["title","=","excluded_title"],["views",">=","10"]]` | Array of `[column, operator, value]` triples. All triples are ANDed together. |
| `orWhere` | `[["title","like","%news%"],["author","=","alice"]]` | Array of `[column, operator, value]` triples combined into ONE OR group, which is ANDed with the `where` group: `where... AND (or1 OR or2 OR ...)`. Arbitrary mixed AND/OR nesting is not expressible. |
| `whereNull` | `"author"` | A single column name string (not a list). Matches rows where the column IS NULL. |
| `whereNotNull` | `"author"` | A single column name string (not a list). Matches rows where the column IS NOT NULL. |
| `whereIn` | `["status",["draft","published"]]` | A single `[column, [values]]` pair (not an array of pairs). |
| `whereNotIn` | `["status",["archived"]]` | A single `[column, [values]]` pair (not an array of pairs). |
| `orderBy` | `["_sn_entity_updated","desc"]` | A `[column, "asc"\|"desc"]` pair. If omitted, results are ordered by `_sn_entity_id`. |
| `offset` | `40` | Number of rows to skip. Use with `orderBy` for stable pagination. |
| `limit` | `20` | Maximum number of rows returned. |

Pagination behaviour: if neither `limit` nor `offset` is set, no LIMIT clause is applied. If either one is set, `limit` defaults to `20` unless given explicitly.

Operators are passed directly to Eloquent, so any SQL operator string works (`=`, `!=`, `like`, `>=`, ...). Negated operators must be sent lowercase as a string, for example `"not like"`.

Deleted records never appear in results: a `whereNull("_sn_entity_deleted")` condition is always appended to the query.

Full example body:

```json
{
  "table": "posts",
  "where": [["status", "=", "published"]],
  "orWhere": [["title", "like", "%news%"], ["author", "=", "alice"]],
  "whereNotNull": "body",
  "whereIn": ["category", ["announcements", "updates"]],
  "whereNotIn": ["status", ["archived"]],
  "orderBy": ["_sn_entity_updated", "desc"],
  "offset": 20,
  "limit": 10
}
```

### CSRF token for POST requests
Site API POST endpoints are protected by Laravel CSRF middleware. Only `site_api/v1/api_*` endpoints (API token auth) are exempt;
every other POST (`query_endpoint`, `visitor_record_create`, ...) fails with HTTP 419 without a token.

Most JS frameworks handle this automatically - for example axios re-sends the `XSRF-TOKEN` cookie as an `X-XSRF-TOKEN` header,
which is why the Tech Demo site works out of the box. A hand-written client (like `fetch()`) must send the header itself:

1. `GET /site_api/v1/csrf-cookie` - sets the `XSRF-TOKEN` cookie
2. Read the `XSRF-TOKEN` cookie value and URL-decode it
3. Send it as `X-XSRF-TOKEN` header with each POST request

## Single Page Applications - SPA
SPA Sites require **single_Page_Application** in Site Config to be set to **true** for proper routing.  
Index.html will be served for all non-file requests and base path adjusted.  
This setting is required for example for Angular and other js frameworks.

### Finding your Site API base URL - `site_root` cookie
The same site files can be served under a client path prefix (non-direct hosting), so a SPA must not assume it lives at the origin root.  
When serving site files, the Client sets cookies on every response:

- `site_root` - absolute base URL of the Site API backend
- `client_root` - absolute URL of the Client home
- `site_id` - Site ID the files are served for
- `ui_addresses_json` - JSON list of Client UI addresses

Read `site_root` and prefix all Site API calls with it, like the Tech Demo does:

```js
const siteRoot = getCookieValueByName('site_root') // e.g. "http://site-id.snet.localhost:15080/"
await fetch(siteRoot + 'site_api/v1/csrf-cookie')
```

## Publishing your Site
Steps to publish a new Site:

- Place site's files in a new subdirectory in **developed_sites** directory
**serveronet-app/storage/app/developed_sites/**
- In your Client go to `[Developed Sites]` so the Client can index files and assign a new Site ID
- You can configure the Site's database schema, define administrators, allow visitor files and more
- Click `[Publish Site]` to start the publishing process
- The Client will validate the size and correctness of the new Site Definition, hash the files, publish to IPFS, generate a signature, and publish the new version and assets to known peers
- It's recommended to ensure that your client is connectable by other peers
- Your Site should be accessible by other peers!

## Site Definition
Site Definition consists of the following
- Set of metadata - creation timestamp or total size of static files
- File Listing - list of files with their chunks
- Site Config - configurations
### Site Config
Site Config is a set of key value pairs. Values can be strings, booleans, arrays. If not defined by the Site Owner they will fall back to the default.
#### trusted_Site_Peers
This setting is used when querying peers for database records. When a trusted peer responds, no quorum will be required. Usually this should list a server which is controlled by the Site Owner and has the Serveronet Client installed.
#### prefered_Trackers
Preferred BitTorrent trackers specified by the site owner. Clients will use those trackers when announcing or getting peers. If not specified, the algorithm will assign a preferred tracker for the Site.
#### site_Admin_Signers
Admins/Moderators can edit all records. List their identities in a string. Site Owner is an admin by default so doesn't need to be listed.
#### site_Requires_Authentication
For sites like a mailbox where data should require a logged-in user and would not work without it. Boolean.
#### single_Page_Application
Enables SPA behaviour. Index.html is served for all non-file requests and the base path is adjusted. Boolean.
#### allow_Visitor_Files
A Site can allow uploading of Visitor's files. The Site Owner can set it to false to speed up the replication process. Boolean.
#### site_Has_Database
A Site optionally can have its own database. Configure the schema in db_Schema_Versions. Boolean.
#### db_Schema_Versions
DB Schema Versions definition that will result in SQL DDLs. They will bring the site database to the most recent version. It will be executed on each client hosting this site. Several system fields will be added automatically.  
##### Version identifiers
Each version is indicated by a comparable string value of the `version_number` key. The identifier format is not strictly defined - "1", "2", "v1", "v2", "a", "b", "2026-05..." all work. It is stored on the Site as its current database version.  
On upgrade, changes to the database will be applied on all the peers.
Versions are applied in order to bring the database up to date: every version identifier greater than the stored current version is executed, sorted ascending. Numeric identifiers compare as numbers, other identifiers compare as strings, so keep one consistent style ("v2" sorts after "v10").  
Each version is identified by its key in `db_Schema_Versions` and the matching `version_number` field - the key and the `version_number` value must be equal, otherwise the version is never found and silently skipped. That means:
- Object form: `"v1": { "version_number": "v1", ... }` - any identifier works.
- List form: position is the identifier, so `version_number` must be `0`, `1`, `2`, ... matching the index.
Start with version `"0"`, `tableCreates` and `indexCreates`. See `Tech Demo` site and example Site Config on how to manage database versions.  
With the next version you can add `columnAlters`. 

You can define following database schema manipulations:
- tableCreates
- columnAlters
- indexCreates
- uniqueIndexCreates
- columnDrops
- tableDrops
  
The following data types are currently supported:
- string
- text
- dateTime
- date
- dateTimeTz
- timestamp
- integer
- bigInteger
- boolean
- decimal
- float
- double
  
They can be nullable or not.  
You can define default values.  
Table and column names must match ^[a-zA-Z_][a-zA-Z0-9_]*$ and be ? 64

#### show_Adults_Only_Gate
A Site optionally can show the Adults Only gate. Age Check compliant. Boolean.
#### list_Providers
Some sites can provide a list of Banned Sites or domains. Such lists can be enabled. See the Tech Demo site for an example.
#### inject_Feeling_Library
Injects a JavaScript script which adds a helper button with useful links and information. Boolean.

## Limits and Limitations
### Limits
To keep the network healthy there are limits to entities which are enforced by the Client.  
- Single record can be up to 1MB - a Site may need to split long articles into multiple related records
- Query with parameters can be up to 200KB - it might become important in whereIn clause with many ids
- Single resource defined in Site Definition File Listing can be up to 1GB - Use Visitor Resource for larger files where there is no limit
- Resources are chunked into smaller files and their limit is 4MB
- IPFS publish size is limited to 100MB

### Limitations
Serveronet is a distributed P2P Network and database relations are not enforced. 
Implement relations with dedicated columns or additional relational tables.
There are no Joins, Aggregates, Grouping. 
Queries have to be deterministic.

## Reserved paths
Site API backend reserves certain paths. They are used for Client static paths or API endpoints.

Don't name files or folders in the site directory that start with one of these prefixes:

- `register`
- `login`
- `logout`
- `visitor_actions`
- `retrieving`
- `site_api`
- `visitor_file`
- `site_assets`
- `sn_client_resources`

## Reserved database field
When creating a schema definition for a Site, do not use the following column names. They are used internally by the Client and will be overwritten.

- _sn_entity_id
- _sn_record_json
- _sn_signer
- _sn_site_id
- _sn_signer_verification_key_base64
- _sn_is_site_admin_locked
- _sn_grantee_visitor_id
- _sn_is_grant_record
- _sn_signature
- _sn_visitor_id
- _sn_entity_created
- _sn_entity_updated
- _sn_entity_deleted

## Classic DNS domains support
Site Owner can make a Site discoverable by a classic DNS domain name. 
The Serveronet Client will query the DNS TXT field to find the proper Site ID.
The Site Owner has to add a DNS TXT entry - `snetdnslink`

Steps assuming your domain is `example.com`

- Create the subdomain `snetdnslink.example.com` by adding an `A` record snetdnslink pointing to any IP (can be 127.0.0.1)
- Add a `TXT` record with snetdnslink as host (snetdnslink.example.com) with value snetdnslink with the site ID, for example: `snetdnslink=gjdg45...`
- The Serveronet client will query the subdomain snetdnslink.example.com for the Site ID of example.com

You can check if the configuration is correct with dig (Linux)
```
dig snetdnslink.example.com -t TXT
;; ANSWER SECTION:
snetdnslink.example.com. 3600 IN    TXT     "snetdnslink=gjdg45..."
```
OpenNIC servers ([OpenNIC ?](https://www.opennic.org/)) are used to get DNS records pointing to the site address.

## Allow remote access to SN backend
When developing a compiled SPA site you might need to access the Site API Backend from the app.  
See the Site API for endpoints which support API Token authentication.

Edit the .env file and set or add the values below

- API_TOKEN_BACKEND_ENABLED=true - to enable API Token Site API endpoints
- DEV_SITE_API_CORS_ALLOW_ALL=true - to allow the Site API from dev environments - Cross-Origin Resource Sharing
  
The change requires a config clear: php artisan config:clear

## Automated publishing
### Visitor Records
There may be a need to frequently send new records or updates to your Site. 
You can post your updates in an automated way using a token and the Site API. 

Steps:

- Go to the Visitor's Control Panel. 
- Click on `[Enable API Token]` to generate a new API Token
- With an API Token you can make requests as the Visitor
- Allow remote access to the SN backend, see section - `Allow remote access to SN backend`
- Make a POST request as in this example

   ```
   curl 'http://site-id.snet.localhost:15080/site_api/v1/api_token_visitor_record_create' \
   -X POST -H 'Content-Type: application/json' -H 'Authorization: Bearer your_visitor_api_token' \
   --data-raw '{"_sn_record_json":"{\"title\":\"My title\", \"_sn_table\":\"posts\"}"}'
   ```
   > Please note that HTTPS or running the client locally is highly recommended in such a case.

Header and query parameter authentication are supported as well
   ```?api_token=your_visitor_api_token```  
   ```Authorization: Bearer your_visitor_api_token```  

### Site Version (Site Definition) publishing
The API Token endpoint allows publishing new versions of the Site. For example with additional Administrators or with updated files.

Steps
- Allow remote access to the SN backend, see section - Allow remote access to SN backend
- In the Client database set the API Token in the admins table
- Make a POST request to the Client URL (not the Site URL) with the required properties  
  Example: 
  ```http://snet.localhost:15080/p2p_api/v1/api_publish_site```  
  See Automated publishing - Visitor Records section for a similar example.

Following properties will be required:
- title - New Site title
- site_config_json - New Site Config as json
- site_id - Site ID to publish as
- developed_site_dir - directory with site files to be indexed

## Vanity Address
Generation steps:

- Generate your vanity address using the Vanity generator
- Once generated, go to the Admin section in the Serveronet Client -> Add Manually 
- Paste your new private seed in Add Site using Seed
- The Site will be added as a shell Site
- Edit the database, find your new site in the sites table, edit the column developed_site_dir to have the name of a directory where the site's files are developed
- Publish as a new Site ID

