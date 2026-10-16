# Client Administration
## Introduction
Serveronet client is a web application which has an admin section accessible to the client owner. Sites can be visited by Visitors and the Owner. Using a client with "Owner only" requires authenticating as an Admin. The Owner can manage hosted Sites, ban or pin them.  
The client bootstraps its operations with an internal requests loop.  
To be fully connectable to other Peers you might need to expose client ports or enable UPnP.  
Start IPFS and Tor Browser to fully enable your client.  
On the first visit of a Site, a site definition is retrieved.  
Required files are downloaded.  
Depending on the size of the Site, the Site is set to be hosted or not.  
If it is to be hosted then replication begins.  
This includes Site's files, database records and visitor files.

## Admin actions
### Manage Site
- Hosting target state - explicitly set if the Site is to be hosted
- Pin - Site will appear first on the list of sites
- Ban - You can mark a Site as Banned. The Site will be known but not hosted, nor accessible on the Client.
- Update Site - This will cause the Client to check for newer or updated Site Definitions, Visitor Records or Files.
- Purge Site - Site will be removed. This includes Site Definitions, resources, database.

### Files and Visitor's Files listing
On a client you can see a list of resources which make up the files. They are called chunks.

### Site Definitions
Once a new Site Definition and its files are fully downloaded, it will become served.
Older Site Definitions are deleted and related resources will be purged.

## Serving Single Site
Your client can also work as a traditional web server and serve your Serveronet site through your domain.  
Configure it in the single_site_mode.php file.  
The change requires a config clear: php artisan config:clear

## Client Bundle
The Client Bundle is a bundle consisting of a FrankenPHP instance and a Serveronet client. You can adjust ports and addresses using the Caddyfile in the frankenphp directory.  

### Updating client bundles - Windows, Mac, Linux Client update steps

1. Download new client bundle from the Serveronet site
2. Unzip bundle
3. Delete [serveronet-app] subdirectory with default installation from just unzipped dir
4. Move previous [serveronet-app] subdirectory into new bundle directory
5. Done

## Recreating a Site's database
The Client Administrator can recreate a Site's database.  
The database will be purged. Visitor Records will be imported into the Site's database.  
This can be a lengthy process.  
Execute the artisan command in a terminal to initiate the process.  
```php artisan sn:recreate-site-db {site_id}```

## Resources maintenance
Adds existing files to the cache and removes resources without stored files.
Execute the artisan command in a terminal to initiate the process.  
```php artisan sn:resources-maintenance```


    



