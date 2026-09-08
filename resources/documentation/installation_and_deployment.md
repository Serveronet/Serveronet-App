
      
<br>


**Choose how you deploy:**


## On your PC
<a name="windows"></a>
 
### Windows

- Download client bundle: 
  
  [Serveronet Windows Client https://serveronet.org/windows_client_bundle.zip](https://serveronet.org/windows_client_bundle.zip)

- Unzip downloaded zip
  
- Run Serveronet_Start.bat
- Go to http://snet.localhost:15080
- Perform First Run setup
- Optionally: Start Tor Brower and IPFS Client and configure in settings to use it
- Done ✅

 
⁣
<a name="linux"></a>

### Linux 64bit (Ubuntu & Debian) and MacOS

- Download client bundle
  - `curl -L https://serveronet.org/linux_and_mac_client_bundle.zip -o linux_and_mac_client_bundle.zip`
- Unzip downloaded zip - 
  - `unzip linux_and_mac_client_bundle.zip -d serveronet && unlink linux_and_mac_client_bundle.zip`
- Run Start_Serveronet.sh
  - `cd serveronet && chmod +x Start_Serveronet.sh`
  - `./Start_Serveronet.sh`
- Go to http://snet.localhost:15080/
  
- Perform First Run setup
  
- Optionally: Start Tor Brower and IPFS Client and configure in settings to use it
  
- Done ✅


  


## On a Web server - Shared Hosting, VPS


### Web Server
  
- Check [Requirements ▶](https://laravel.com/docs/13.x/deployment#server-requirements)
  
- Obtain [Server Bundle ▶](https://serveronet.org/server_bundle.zip) (not a client bundle!)
  
- Upload and unzip to your server to the **non public** directory
  
- Make server serve /public dir as your subdomain or a domain https://serveronet.example.com/
  
- If needed adjust file permissions to the web server user
  
- Browse to your server to perform First Run setup
  
- Done ✅

