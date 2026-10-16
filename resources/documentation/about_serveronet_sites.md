

# Serveronet Sites
## Introduction
Serveronet Sites are websites which live within a p2p network. 
They are semi-encapsulated in a Serveronet Client. 
Sites are retrieved from a P2P network and served by your client.

Sites interact with the network through the client's Backend. Database queries are sent to the network if the site is not yet hosted. Files are served from the local cache and, if missing, are requested from the P2P network or IPFS.

A Site consists of the following items:

 - Site Definition json file - Site Definition is a set of configurations signed by the site owner.
 - Visitor Records - Json files which represent database entries. They are synchronized through the P2P network.
 - Visitor Resources - Json files which are the definition of a file published by a visitor or the site owner.


##  Site Address
Every Site has its own unique address. The site address appears in the URL as a subdomain. Site ID is a hash of the PQ verification key.

Example of Site ID: hwy4phcbfbigqgr5duodyntqcz34ypr2bdwx6d3oa36rsuvujt7a

`http://`**abc_site_address**`.snet.localhost:15080/`

## Domains
### Classic DNS
You can also use classic domains which are mapped to the site address.

Enter example.com in the Go field and your client will retrieve the proper Site ID.


<!-- `http://`**example-com**`.snet.localhost:15080/` => **abcsiteaddress**  -->
<!-- Site ID as domain - = . -->
See site development documentation on how to configure.



### Dot Snet domains
Serveronet also supports .snet domains.
Enter a .snet domain in the Go field and your client will retrieve the proper Site ID. 

Example: techdemo.snet
<!-- `http://`**about-snet**`.snet.localhost:15080/` -->





