# Identities 
## Introduction
Serveronet uses PQ cryptographic identities. 

## New Identity
You can generate a new identity by registering a new Visitor.
When registering a new identity be sure to download backup json file with relevant private seed.
Private seed of an Identity is recoverable by the Client Owner.
Your new identity will be stored on the Client and visible only for you on the list of recent identities.
During the registration you need to specify a password that will be used to authenticate as the Visitor.

## Permissions
### Visitor with rights
Site Owner can grant rights to create or edit owned records. It is performed by publishing a grant record. Grant record indicates that specified Visitor has rights to post to specified database table or upload files. To revoke access the grant record needs to be marked as deleted. Peers will revalidate right periodically.
### Admin rights
Site Owner can also grant admin rights to other identities. Such rights allow to moderate, tamper or delete all the records. Site Administrators are defined in Site Definition file.
### Site Owner rights
Site Owner has also rights manage all the records plus publish a new Site Definition.

## Using an Identity
When posting a record or uploading a file the Client will use Indentity's private seed to sign the data. 
