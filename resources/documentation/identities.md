# Identities 
## Introduction
Serveronet uses PQ cryptographic identities. 

## New Identity
You can generate a new identity by registering as a new Visitor.
When registering a new identity, be sure to download the backup json file with the relevant private seed.
The private seed of an Identity is recoverable by the Client Owner.
Your new identity will be stored on the Client and visible only to you on the list of recent identities.
During the registration you need to specify a password that will be used to authenticate as the Visitor.

## Process of getting edit rights
As every Site is a separate realm, the Site Owner manages rights for each Visitor. Site Owner has all the rights by default.
1. Create an Identity
2. Register your Identity on a Client
3. Site Owner assigns rights either by setting as a Site Admin or issuing a Grant Record
4. Visitor can post records or upload on a Site

## Permissions
### Visitor with rights
Site Owner can grant rights to create or edit owned records. This is performed by publishing a grant record. A grant record indicates that the specified Visitor has rights to post to a specified database table or upload files. To revoke access, the grant record needs to be marked as deleted. Peers will revalidate rights periodically.  
To issue a Grant Record, the Site Admin logs in to the Client, specifies to which Visitor Identity to issue it to, and publishes a record. See the Tech Demo Site for an example.

### Admin rights
Site Owner can also grant admin rights to other identities. Such rights allow moderating, tampering with or deleting all the records. Site Administrators are defined in the Site Definition file.
To assign Site Admin rights, the Site Owner publishes a new Site Definition with the visitor_id added to the site_Admin_Signers property.
Site Admin can mark a record as Site Admin Locked (_sn_is_site_admin_locked). This prevents deleted or moderated records from being editable by the Visitor.

### Site Owner rights
The Site Owner also has the right to manage all the records plus publish a new Site Definition.
Site Owner rights are inherited when publishing a new Site version and its Site Definition.

## Using an Identity
When posting a record or uploading a file, the Client will use the Identity's private seed to sign the data. 
