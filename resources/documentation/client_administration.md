# Client Administration
## Introduction
Serveronet client is a web application which has admin section accesible to the client owner. Sites can be visited by Visitors and Owner. Using a client with "Owner only" requires authenticating as an Admin. Owner can manage hosted Sites, ban or pin them.  
Client bootstraps it's operations by internal requests loop.  
To be fully connectable to other Peers you might need to expose client ports or enable UPnP.  
Start IFPS and Tor Browser to fully enable your client.  
On a first visit of the Site, a site definition is retrieved.  
Required files are downloaded.  
Depending on size of the Site, Site is set to be hosted or not.  
If is to be hosted then replication begins.  
This includes Site's files, database records and visitor files.

## Admin actions
### Manage Site
- Hosting target state - explicitly set if Site is is to be hosted
- Pin - Site will appear fist on the list of sites
- Ban - You can mark a Site as Banned. Site will be known and not hosted, nor accesible on a Client.
- Update Site - This will cause the Client to check for newer or updated Site Definitions, Visitor Records or Files.
- Purge Site - Site will be removed. This includes Site Definitions, resources, database.

### Files and Visitor's Files listing
On a client you can see a list of resources which make files. They are called chunks.

### Site Definitions
Once a new Site Definition and it's files are fully downloaded then it will become served.
Older Site Definitions are deleted and related resouces will be purged.

## Serving Single Site
Your client can also work as a traditional web server and serve your serveronet site through your domain.  
Configure it in single_site_mode.php file.  
Change requires config clear: php artisan config:clear

## Client Bundle
Client Bundle is bundle consisting of frankenphp instance and a serveronet client. You can adjust ports, addresses using Caddyfile from frankenphp directory.  

### Updating client bundles - Windows, Mac, Linux Client update steps

1. Download new client bundle from the Serveronet site
2. Unzip bundle
3. Delete [serveronet-app] subdirectory with default installation from just unzipped dir
4. Move previous [serveronet-app] subdirectory into new bundle directory
5. Done

## Recreating Site's database
Client Administrator can create Site's database a new.  
Database will be purged. Visitor Records will be imported into Site's databased.  
This can be a lengthy process.  
Execute artisan command in terminal to initiate the process.  
```php artisan sn:recreate-site-db {site_id}```

## Resources maintenance
Add to cache existing files and remove resources without files stored.
Execute artisan command in terminal to initiate the process.  
```php artisan sn:resources-maintenance```


    



