# Introduction
Those are advanced deployment steps and might require some degree of IT skills. Proceed with them when you know what your doing as misconfiguration might result in security issues.

# Manual installation - Apache Server
**Newer Ubuntu, Debian, Raspbian**

You can deploy Serveronet client as a site on your server. 

Requires php >= 8.3

In this deployment flow we are deploying as http://localhost/ in /var/www/serveronet directory

## Install prerequisites

```
sudo apt install apache2 php libapache2-mod-php php-sqlite3 php-gmp php-curl php-xml php-zip php-mbstring curl 
```

## Steps
- Activate rewrite mod. Important! for security 
  
  `sudo a2enmod rewrite`

- Download and unzip bundle into proper location. In this example /var/www/serveronet will be used. 

  ```curl -L https://serveronet.org/server_bundle.zip -o server_bundle.zip```

  ```sudo unzip server_bundle.zip -d /var/www/serveronet```
- Correct permissions 
    
  ```cd /var/www/serveronet```
    
  ```sudo chown -R www-data:www-data ./```

  ```sudo chmod 755 -R ./```

- Edit configuration file /etc/apache2/sites-available/000-default.conf

  ```sudo nano /etc/apache2/sites-available/000-default.conf```

  - Replace line with **DocumentRoot** with
    
    ```
    DocumentRoot /var/www/serveronet/public
    <Directory /var/www/serveronet/public>
        AllowOverride All
        Require all granted
    </Directory>
    ```

- Deploy relavant configuration for the HTTPS version - not covered by this documentation.

- Reload web server configuration
  
  ```sudo systemctl restart apache2```

- Navigate to http://localhost/ (or proper host) to complete the First Run

⁣

## As a Tor hidden service
Follow **manual instalation** steps and apply these adjustments

- Generate onion address - not covered by this documentation.

- In **/etc/tor/torrc** adjust to additional ports
```HiddenServicePort 80 127.0.0.1:80```

- Add VirtualHost to enabled site
```<VirtualHost *:80>
    DocumentRoot /var/www/torwww/public
    <Directory /var/www/torwww/public>
        AllowOverride All
        Require all granted
    </Directory>
    </VirtualHost>
```

- Restart Tor and Apache
  
```sudo systemctl restart apache2```

```sudo systemctl restart tor```

References:

https://community.torproject.org/onion-services/setup/

⁣

# Docker
- Create Empty volume - required to access developed sites directory
  
    ```
  docker volume create serveronet_volume
  ```
  
- Download and unzip server bundle into the volume

  ```
  docker run --rm \
  -v serveronet_volume:/app \
  alpine sh -c "
    apk add --no-cache curl unzip &&
    curl -L https://serveronet.org/server_bundle.zip -o /tmp/site.zip &&
    unzip /tmp/site.zip -d /app
  "
  ```
- Run the container
  
  ```
    docker run -d \
    --name serveronet_frankenphp \
    -v serveronet_volume:/app \
    -p 15080:80 -p 15443:443 \
    dunglas/frankenphp
  ```
- Connect to the Container and install missing php-zip extension

  ```
  docker exec -it serveronet_frankenphp sh -c "install-php-extensions zip"
  ```
- Restart after the installation
  ```
  docker restart serveronet_frankenphp
  ```
- Browse to https://snet.localhost:15443 to complete the First Run

⁣

# Termux and Android Terminal (and Termux docker)

- **docker only:** Deploy image  
  
  ```docker run -p 15080:15080 -p 15443:15443 -it termux/termux-docker:latest```

  
- **docker only:** Enable root shell  -  [🔗termux-docker](https://github.com/termux/termux-docker)

  ```/entrypoint.sh```
- Connect to shell and execute following:

  ```curl -L https://serveronet.org/linux_and_mac_client_bundle.zip -o serveronet.zip```

  ```unzip serveronet.zip -d serveronet```

  ```cd serveronet && chmod +x Start_Serveronet.sh Stop_Serveronet.sh```

  ```./Start_Serveronet.sh```
  
- Browse to http://snet.localhost:15080/

⁣

# Tor Setup

How to make client accesible on a Tor onion address

Steps:

1. Close Tor Browser or stop Tor
2. Edit torrc configuration file and add following lines
   
   ```HiddenServiceDir ../hidden_service/```

   ```HiddenServicePort 15080 snet.localhost:15080```

   ```HiddenServicePort 15443 snet.localhost:15443```
3. Start Tor or Tor Browser
4. Configure Tor port accordingly in Client Settings:
   
   Defaults: 9050 (PC and Orbot), for Tor Browser: 9150