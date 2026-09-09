

# Serveronet Sites
## Introduction
Serveronet Sites are websites which live within a p2p network. 
They are semi-encapsulated in a Serveronet Client. 
Sites are retrieved from a P2P network and served by your client.

Sites interract with the network through client's Backend. Database queries are sent to the network if site not yet hosted. Files are served from the local cache and if missing, are requested from the p2p Network or IPFS.

Site consist of following items:

 - Site Definition json file - Site Definition is a set of configurations signed by the site owner.
 - Visitor Records - Json files which represent database entries. They are syncronized through the p2p network.
 - Visitor Resources - Json files which are definition of a file published by visitor or site owner.


##  Site Address
Every Site have it's unique address. Site address appears in the url as a subdomain. Site ID is a hash of PQ verification key.

Example of Site ID: hwy4phcbfbigqgr5duodyntqcz34ypr2bdwx6d3oa36rsuvujt7a

`http://`**abc_site_address**`.snet.localhost:15080/`

## Domains
### Classic DNS
You can also use classic domains which are mapped to the site address.

Enter example.com in the Go field and your client will retrieve proper Site ID.


<!-- `http://`**example-com**`.snet.localhost:15080/` => **abcsiteaddress**  -->
<!-- Site ID as domain - = . -->
See site development documentation on how to configure.



### Dot Snet domains
Serveronet supports also .snet domains.
Enter .snet domain in the Go field and your client will retrieve proper Site ID. 

Example: techdemo.snet
<!-- `http://`**about-snet**`.snet.localhost:15080/` -->





