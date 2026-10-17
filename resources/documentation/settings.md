# Client Settings
## Introduction
Client Settings are separated into 2 groups: Advanced and Basic.
Do not change Advanced settings unless you know what you are doing.

## Common Settings
Settings that you might want to adjust at some point:
- Tor client config - tor_address, tor_port - Adjust if the Tor client is on a separate machine, is installed directly or uses the Tor Browser
- Proxy support - proxy_enabled, proxy_type, proxy_address, proxy_port - Route peer to peer requests (except Tor addresses) through an HTTP or SOCKS proxy server. See the Proxy section below.
- IPFS Client - ipfs_address, ipfs_read_only - Adjust the IPFS address. Use a public IPFS gateway which is read-only.
- Port Forward - port_forwarding_enabled - Enable the port forwarding functionality of the router
- Automatic Update - automatic_update_enabled - Update your client automatically. It's recommended to be on for security reasons.
- OpenNIC API Address - opennic_api_address_ip - Update the OpenNIC IP address to the current one when DNS might be blocked

## Proxy
The Client can route its outbound peer to peer Guzzle requests through a proxy server.  
This is useful when direct outbound connections are blocked, when you want all traffic to leave from a specific gateway, or when you run a proxy on this or another machine.

Settings:
- proxy_enabled - Turn proxy usage on or off. Off by default.
- proxy_type - One of: http, https, socks4, socks5
- proxy_address - Address of the proxy server. For a proxy running on the same machine: 127.0.0.1
- proxy_port - Port of the proxy server. A common HTTP proxy (for example Squid) listens on 3128
- proxy_user, proxy_password - Authentication for proxies which require it (Advanced settings)

Notes:
- When enabled, the proxy is used for peer API requests, tracker announcing, peer connectivity checks, updates and the first run bootstrap request.
- Addresses ending in .onion always go through the Tor client (tor_address, tor_port). The proxy does not affect them.
- Requests to the client itself (localhost) never go through the proxy.
- The Client periodically checks the proxy and records the result in was_proxy_connectable. You can also trigger the admin/test_proxy_access endpoint.

Deploying a Squid HTTP proxy on the same machine with Docker:
```
docker run -d --name sn-squid --restart unless-stopped -p 127.0.0.1:3128:3128 ubuntu/squid:latest
```
Then set proxy_enabled to on with proxy_type http, proxy_address 127.0.0.1 and proxy_port 3128.