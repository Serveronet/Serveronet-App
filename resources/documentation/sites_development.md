# Sites Development
## Introduction
Serveronet Client serves static files. You can publish html, js and images as contents of your site.  
Client exposes API Backend for every site.
## Site API
To access Site API documentation browse to this path when on a Site 

```/sn_client_resources/site_api_docs/index.html```

[Site API Documentation ▶](http://visitor-control-panel.snet.localhost:15080/sn_client_resources/site_api_docs/index.html)

Values from the database can be queried with Site API backend using query parameters.   
Following query parameters are supported:
- where
- orWhere
- whereNull
- whereNotNull
- whereIn
- whereNotIn
- orderBy
- limit

## Single Page Applications - SPA
SPA Sites require **single_Page_Application** in Site Config to be set to **true** for proper routing.  
Index.html will be served for all non-file requests and base path adjusted.  
This setting is required for example for Angular and other js frameworks.

## Publishing your Site
Steps to publish a new Site:

- Place site's files in a new subdirectory in **developed_sites** directory
**serveronet-app/storage/app/developed_sites/**
- In your Client go to `[Developed Sites]` so the Client can index files and assign a new Site ID
- You can configure Site's database schema, define administrator, allow visitor files and other
- Click `[Publish Site]` to start publishing process
- Client will validate size and correctness of new Site Definition, hash files, publish to IPFS, generate signature, publish new version and assets to known peers
- It's recommended to ensure that your client is connectable by other peers
- Your Site should be accesible by other peers!

## Site Definition
Site Definition consits of following
- Set of metadata - creation timestamp or total size od static files
- File Listing - list of files with their chunks
- Site Config - configurations
### Site Config
Site Config is a set of key value pairs. Values can be strings, booleans, arrays. If not defined by the Site Owner they will fallback to the default.
#### trusted_Site_Peers
This setting is used when quering peers for database records. When trusted peer responds no quorum will be required. Usually this should list a server which is in control by the Site Owner and has Serveronet Client installed.
#### prefered_Trackers
Prefered Bittorrent trackers specified by site owner. Clients will use those trackers when announcing or getting peers. If not specified, then axlgorithm will assign prefered tracker for Site.
#### site_Admin_Signers
Admins/Moderators can edit all records. List their identities in a string. Site Owner is an admin by default so doesn't need to be listed.
#### site_Requires_Authentication
For sites like mailbox where data should require logged-in user and would not work without it. Boolean.
#### single_Page_Application
Enable SPA behaviour. Index.html is served for all non-file requests and base path is adjusted. Boolean.
#### allow_Visitor_Files
Site can allow to upload Visitor's file. Site Owner can set it as false to speed up the replication process. Boolean.
#### site_Has_Database
Site optionally can have it's own database. Configure the schema in db_Schema_Versions. Boolean.
#### db_Schema_Versions
DB Schema Versions definition that will result in SQL DDLs. They will bring site database to the most recent version. It will be executed on each client hosting this site. Several systemic fields will be added automatically.  
Each version should be indicated by a comparable string value of `version_number` key. These will work: "0", "v0", "2026-05...".
On upgrade changes to the database will applied on all the peers.
Start with `version_number` "0", `tableCreates` and `indexCreates`. See `Tech Demo` site and example Site Config on how to manage database versions. With next version you can add `columnAlters`. 

You can define following database schema manipulations:
- tableCreates
- columnAlters
- indexCreates
- uniqueIndexCreates
- columnDrops
- tableDrops
  
Following data types are now supported:
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

#### show_Adults_Only_Gate
Site optionally can show Adults Only gate. Age Check compliant. Boolean.
#### list_Providers
Some sites can provide list of Banned Sites or domains. Such lists can be enabled. See Tech Demo site for an example.
#### inject_Feeling_Library
Inject javascript script which adds helper button with usefull links and information. Boolean.

## Reserved paths
Site API backend reserves certain paths. They are used for Client static paths or API endpoints.
Do not create site folders starting with those paths: `sn_client_resources`, `site_api`, `visitor_file`

## Reserved database field
When creating schema defintion for a Site do not use following column names. They will be overwritten.

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
Site Owner can make Site discoverable by a classic DNS domain name. 
Serveronet Client will query DNS TXT field to find a proper Site ID.
Site Owner has to add a DNS TXT entry - `snetdnslink`

Steps assuming your domain is `example.com`

- Create subdomain `snetdnslink.example.com` by adding `A` record snetdnslink pointing to any ip (can be 127.0.0.1)
- Add `TXT` record with snetdnslink as host (snetdnslink.example.com) with value snetdnslink with site ID, for example: `snetdnslink=gjdg45...`
- Serveronet client will query subdomain snetdnslink.example.com for Site ID of example.com

You can check if configuration is correct with dig (Linux)
```
dig snetdnslink.example.com -t TXT
;; ANSWER SECTION:
snetdnslink.example.com. 3600 IN    TXT     "snetdnslink=gjdg45..."
```
OpenNIC servers ([OpenNIC ▶](https://www.opennic.org/)) are used to get DNS records pointing to the site address.

## Allow remote access to SN backend
When developing a compiled SPA site you might need access Site API Backend from the app.

Edit .env file and set or add those values as below

- API_TOKEN_BACKEND_ENABLED=true
- DEV_SITE_API_CORS_ALLOW_ALL=true
  
Change requires config clear: php artisan config:clear

## Automated publishing
There may be a need to frequently send new records or updates to your Site. 
You can post your updates in an automated way using token and Site API. 

Steps:

- Go to the Visitor's Control Panel. 
- Click on `[Enable API Token]` to generate a new API Token
- With API Token you can make requests as the Visitor
- Allow remote access to SN backend, as in section - `Allow remote access to SN backend`
- Make a POST request as in this example

   ```
   curl 'http://site-id.snet.localhost:15080/site_api/v1/api_token_visitor_record_create' \
   -X POST -H 'Content-Type: application/json' \
   --data-raw '{"api_token":"your_visitor_api_token","_sn_record_json":"{\"title\":\"My title\", \"_sn_table\":\"posts\"}"}'
   ```
   > Please note that HTTPS or running client locally is highly recommeded in such a case. 

## Vanity Address
Generation steps:

- Generate your vanity address using the Vanity generator
- Once generated go to Admin section in Serveronet Client -> Add Manually 
- Paste your new private seed in Add Site using Seed
- Site will be added as a shell Site
- Edit database, find you new site in sites table, edit column developed_site_dir to have a name of a directory where sites files are developed
- Publish as a new Site ID

