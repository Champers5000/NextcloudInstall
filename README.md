# Nextcloud on Arch Linux Install Guide | LEMP | NGINX | PHP 8
This was test on an ARM based system (rock64) but should be applicapable to x86 based systems as well. Enjoy, and happy troubleshooting!

## Table of Contents
### Section 1: [Linux Setup](##1.-Arch-Linux-Configuration)
### Section 2: [LEMP Setup](##2.-LEMP-and-Nextcloud)
### Section 3: [Nextcloud](##3.-Nextcloud-Install)
### Section 4: [Post-Install](##4.-Post-Install-Steps)

## Prerequisites
* Fresh install of Arch Linux
* Working internet connection
* Knowledge of command line and text editing

## File Locations (for ease of troubleshooting)
Nextcloud Root Directory = `/usr/share/webapps/nextcloud/` \
Nextcloud Config Directory = `/usr/share/webapps/nextcloud/config/ (symlinked to ->) /etc/webapps/nextcloud/config/` \
Nextcloud Config File =  `/usr/share/webapps/nextcloud/config/config.php` \
Nginx config file = `/etc/nginx/nginx.conf` \
Nginx Nextcloud config file = `/etc/nginx/conf.d/nextcloud.conf` \
PHP config file = `/etc/php/php-fpm.d/www.conf ` \


## 1. Arch Linux Configuration
### Run everything in this section as the root user
### 1.1 Basic system setup
Make sure everything's up to date
```
pacman -Syu
```

And install basic necessities
```
pacman -S sudo vim base-devel openssh
```
### 1.2 Text editor
This guide utilizes vim as the text editor, but feel free to use any text editor of your choice.
Open the file `/etc/environment`
```
vim /etc/environment
```
And add the following line 
```
EDITOR="/usr/bin/vim"
```

### 1.3 Hostname
Change the hostname to what you want your server to be called. Put that name in the `/etc/hostname` file
```
vim /etc/hostname
```

### 1.4 Generate Locale
Nextcloud requires that you have generated locales to work. I will be using `en_US.UTF-8 UTF-8`
Uncomment the locale you wish to use in the file `/etc/locale.gen`
```
vim /etc/locale.gen
```
Uncomment desired locale 
```
#en_SG ISO-8859-1
en_US.UTF-8 UTF-8
#en_US ISO-8859-1
```
And generate locale
```
locale-gen 
```

### 1.5 Set Timezone
Set the timezone of your computer. Here is an example setting it to US Central Time
```
timedatectl set-timezone America/Chicago
timedatectl set-ntp true
```
Replace `America/Chicago` with your choice of time zone. A list of available time zones can be viewed by running 
```
timedatectl list-timezones
```

### 1.6 Set a static IP 
Typically when port forwarding, you want your device to have a static IP. Let's set that here
To find your network device run 
```
ip a
```
My device name is `eth0`. This could vary. Pick the device you want to give a static IP. We will be using networkd to set the static ip, so we need to create a new file in `/etc/systemd/network/`
```
vim /etc/systemd/network/eth0.network
```
Now make the contents of the file look like this
```
[Match]
Name=eth0

[Network]
Address=192.168.1.102/24
Gateway=192.168.1.1
DNS=8.8.8.8
DNS=8.8.4.4
```
Replace `eth0` with your device name, and the ip address with your desired static IP address. 
Make sure you also configure your router settings to accomodated for a static IP (choose a static IP that is outside the DHCP pool).

Now lets make sure our change takes effect by enabling and disabling some services. Netctl will not be used anymore, so we remove it if it exists. 
```
pacman -Rns netctl
systemctl stop dhcpcd
systemctl disable dhcpcd
systemctl enable systemd-networkd
systemctl start systemd-networkd
systemctl enable sshd
``` 

### 1.7 External storage drive (optional)
If you so choose to use a external drive as your Nextcloud storage, follow these steps. 
Use `lsblk` to identify which drive you are using
```
lsblk
```
My drive showed up as /dev/sda
Use `fdisk` to wipe the drive and create a single partition.
```
fdisk /dev/sda
```
At the fdisk prompt, delete old partitions and create a new one:
```
Type g. This will clear out any partitions on the drive.
Type p to list partitions. There should be no partitions left.
Type n, 1 for the first partition on the drive,
	and then press ENTER twice to accept the default first and last sector.
Write the partition table and exit by typing w.
```
Format the partition as ext4 and mount it. Replace `/dev/sda1` with your new partition name
```
mkfs.ext4 /dev/sda1
```
Create a mount point for our new partition
```
mkdir /mnt/storage
```
To mount this drive on startup, we need the new partition's UUID. `blkid` can tell us the UUID
```
blkid
```
Copy the UUID of the storage partition you made. 
Next we  modify `/etc/fstab` to mount the drive on startup
```
vim /etc/fstab
```
And we write in this line 
```
UUID=HereUUID /mnt/storage/ ext4 defaults,noatime 0 0
```
To verify that our fstab works, we should try mounting everything in fstab
```
systemctl daemon-reload
mount -a
lsblk
```
If lsblk shows your partition properly mounted at `/mnt/storage`, you're good to go.

### 1.8 Setup a sudo user (optional)
It's dangerous to always run as root user. We can setup a sudo user to use for the rest of the guide here.  I'll name my user "ncadmin".
```
#create user with home folder
useradd -m ncadmin

#set the user password
passwd ncadmin

#add the user to the wheel group for sudo to work
usermod -aG wheel ncadmin
```
The user is now successfully created, but we need to allow members of group wheel to be sudoers.
Run the `visudo` command 
```
EDITOR=vim visudo
```
And uncomment this line
```
## Uncomment to allow members of group wheel to execute any command
%wheel ALL=(ALL:ALL) ALL
```

### 1.9 Reboot
Reboot your system to make sure all changes take effect
```
reboot
```

## 2. LEMP
\
Log in as your sudo user. If you skipped step 1.8, then login as root. 
### 2.1 Packages
Let's get all the packages we need in one command
```
sudo pacman -Sy nginx-mainline mariadb php-fpm php-gd php-apcu nextcloud cronie
```

### 2.2 NGINX setup
Not much to do here, just enable NGINX and make sure it works
```
sudo systemctl start nginx
sudo systemctl enable nginx
sudo systemctl status nginx
```
Navigate to http://serverIP/ to make sure the service is running

### 2.3 MYSQL Install
```
#Initialize the MariaDB data directory prior to starting the service.
mysql_install_db --user=mysql --basedir=/usr --datadir=/var/lib/mysql
#Start and enable service
systemctl start mysqld
systemctl enable mysqld
systemctl status mysqld
```
Run the post-installation security script. Carefully read and just choose the default/recommended options
```
mysql_secure_installation
```

### 2.4 PHP Setup
We need to tell Nginx to run PHP using php-fpm. Edit the file `/etc/nginx/nginx.conf`
```
sudo vim /etc/nginx/nginx.conf
```
Find the block that starts with `location ~ \.php$`. The block will likely be commented out. Uncomment it and make it look like this
```
location ~ \.php$ {
	root           /usr/share/nginx/html;
	fastcgi_pass   unix:/run/php-fpm/php-fpm.sock;
	fastcgi_index  index.php;
	fastcgi_param  SCRIPT_FILENAME  $document_root$fastcgi_script_name;
	include        fastcgi_params;
}
```
\
Now we need to enable the php modules that nextcloud uses. Locate the file `/etc/php/php.ini`
```
sudo vim /etc/php/php.ini
```
And uncomment the following lines.
```
extension=mysqli
extension=pdo_mysql
extension=gd
extension=intl
extension=exif
extension=sysvsem
extension=gmp
extension=bcmath
```
\
Then start and enable php-fpm
```
systemctl start php-fpm
systemctl enable php-fpm
systemctl status php-fpm
```
\
Test PHP processing
```
echo "<?php phpinfo(); ?>" | sudo tee -a /usr/share/nginx/html/test.php
systemctl reload nginx
```
Browse to http://serverIP/test.php
\
You should see a page giving you detailed php information about your system. If everything is okay, remove test.php
```
sudo rm /usr/share/nginx/html/test.php
```

## 3. Nextcloud Install
What you really came here for.
\
To make updates to nextcloud easy, this guide makes both nginx and php-fpm run under the `nextcloud` user instead of http user. This is one possible way of setting up nextcloud. It is possible to instead make nextcloud run under the http user, but that will not be covered in this guide. 

### 3.1 MySQL Nextcloud user creation
Log into MariaDB database server
```
sudo /usr/bin/mariadb -u root -p
```
Then create a database for Nextcloud.
```
create database nextcloud;
```
Create the database user. Replace USER and PASSWORD with your preferred values.
```
create user USER@localhost identified by 'PASSWORD';
```
Grant this user all privileges on the nextcloud database
```
grant all privileges on nextcloud.* to USER@localhost identified by 'PASSWORD';
```
Flush the privileges table and exit.
```
flush privileges;
exit;
```
\
Enable Binary Logging in MariaDB by editing the file `/etc/my.cnf.d/mysql-clients.cnf`
```
vim /etc/my.cnf.d/mysql-clients.cnf
```
In the `[mysql] ` section, add these two lines
```
log-bin        = mysql-bin
binlog_format  = mixed
```
\
Restart service
```
systemctl restart mysqld
```

### 3.2 Nextcloud NGINX config
Create a new folder for the nextcloud configuration for nginx. 
```
sudo mkdir /etc/nginx/conf.d
sudo vim /etc/nginx/conf.d/nextcloud.conf
```
In the newly created `/etc/nginx/conf.d/nextcloud.conf` file, put in this configuration. Replace the `server_name` variable with your domain. 

```
upstream php-handler {
server unix:/run/php-fpm/php-fpm.sock;
}
server {
listen 80;
server_name champersnc.duckdns.org;
# Add headers to serve security related headers
add_header X-Content-Type-Options nosniff;
add_header X-Frame-Options "SAMEORIGIN";
add_header X-XSS-Protection "1; mode=block";
add_header X-Robots-Tag none;
add_header X-Download-Options noopen;
add_header X-Permitted-Cross-Domain-Policies none;
# Path to the root of your installation
root /usr/share/webapps/nextcloud/;
location = /robots.txt {
  allow all;
  log_not_found off;
  access_log off;
}
# The following 2 rules are only needed for the user_webfinger app.
# Uncomment it if you're planning to use this app.
#rewrite ^/.well-known/host-meta /public.php?service=host-meta last;
#rewrite ^/.well-known/host-meta.json /public.php?service=host-meta-json;
# last;
location = /.well-known/carddav {
  return 301 $scheme://$host/remote.php/dav;
}
location = /.well-known/caldav {
   return 301 $scheme://$host/remote.php/dav;
}
location ~ /.well-known/acme-challenge {
  allow all;
}
# set max upload size
client_max_body_size 512M;
fastcgi_buffers 64 4K;
# Disable gzip to avoid the removal of the ETag header
gzip off;
# Uncomment if your server is build with the ngx_pagespeed module
# This module is currently not supported.
#pagespeed off;
error_page 403 /core/templates/403.php;
error_page 404 /core/templates/404.php;
location / {
   rewrite ^ /index.php$uri;
}
location ~ ^/(?:build|tests|config|lib|3rdparty|templates|data)/ {
   deny all;
}
location ~ ^/(?:\.|autotest|occ|issue|indie|db_|console) {
   deny all;
 }
location ~ ^/(?:index|remote|public|cron|core/ajax/update|status|ocs/v[12]|updater/.+|ocs-provider/.+|core/templates/40[34])\.php(?:$|/) {
   include fastcgi_params;
   fastcgi_split_path_info ^(.+\.php)(/.*)$;
   fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
   fastcgi_param PATH_INFO $fastcgi_path_info;
   #Avoid sending the security headers twice
   fastcgi_param modHeadersAvailable true;
   fastcgi_param front_controller_active true;
   fastcgi_pass php-handler;
   fastcgi_intercept_errors on;
   fastcgi_request_buffering off;
}
location ~ ^/(?:updater|ocs-provider)(?:$|/) {
   try_files $uri/ =404;
   index index.php;
}
# Adding the cache control header for js and css files
# Make sure it is BELOW the PHP block
location ~* \.(?:css|js)$ {
  try_files $uri /index.php$uri$is_args$args;
  add_header Cache-Control "public, max-age=7200";
  # Add headers to serve security related headers (It is intended to
  # have those duplicated to the ones above)        
  add_header X-Content-Type-Options nosniff;
  add_header X-Frame-Options "SAMEORIGIN";
  add_header X-XSS-Protection "1; mode=block";
  add_header X-Robots-Tag none;
  add_header X-Download-Options noopen;
  add_header X-Permitted-Cross-Domain-Policies none;
  # Optional: Don't log access to assets
  access_log off;
   }
   location ~* \.(?:svg|gif|png|html|ttf|woff|ico|jpg|jpeg)$ {
  try_files $uri /index.php$uri$is_args$args;
  # Optional: Don't log access to other assets
  access_log off;
   }
}
```
\
Edit /etc/nginx/nginx.conf file.
```
nano /etc/nginx/nginx.conf
```
Add the following line in the http section so that individual Nginx config files will be loaded.
```
include /etc/nginx/conf.d/*.conf;
```
\
Reload service and make sure there are no errors
```
systemctl reload nginx
```
## 3.3 Permissions Management
At this point, nextcloud should be visible if you navigate to http://serverIP. You should be an error along the lines of "config directory not readable". This is because nginx and php=fpm default to running using the `http` user, while nextcloud files are owned my the `nextcloud` user. Let's make everything run under the `nextcloud user`
\
First ensure that `nextcloud` user owns everything
```
sudo chown nextcloud:nextcloud /usr/share/webapps/nextcloud/ -R
sudo chown nextcloud:nextcloud /mnt/storage -R
```
\
Change nginx to run under the user `nextcloud ` by editing the file `/etc/nginx/nginx.conf`
```
sudo vim /etc/nginx/nginx.conf
```
Change the `user` variable to be `nextcloud`
```
user nextcloud;
```
\
Now	for php-fpm. Edit the file `/etc/php/php-fpm.d/www.conf `
```
sudo vim /etc/php/php-fpm.d/www.conf 
```
And change the variables `user` and `group` both to `nextcloud`
```
; Unix user/group of the child processes. This can be used only if the master
; process running user is root. It is set after the child process is created.
; The user and group can be specified either by their name or by their numeric
; IDs.
; Note: If the user is root, the executable needs to be started with
;       --allow-to-run-as-root option to work.
; Default Values: The user is set to master process running user by default.
;                 If the group is not set, the user's group is used.
user = nextcloud
group = nextcloud
```
In the same file, also change `listen.owner` and `listen.group` to `nextcloud`
```
#also  need to change listen owner and group to nextcloud in the same [www] section
; Set permissions for unix socket, if one is used. In Linux, read/write
; permissions must be set in order to allow connections from a web server. Many
; BSD-derived systems allow connections regardless of permissions. The owner
; and group can be specified either by name or by their numeric IDs.
; Default Values: Owner is set to the master process running user. If the group
;                 is not set, the owner's group is used. Mode is set to 0660.
listen.owner = nextcloud
listen.group = nextcloud
;listen.mode = 0660
```
Exit and save the file
\
We also need to modify the php-fpm service to grant it access to nextcloud folders. 
```
sudo systemctl edit php-fpm.service
```
And add these lines
```
[Service]
ReadWritePaths = /usr/share/webapps/nextcloud/
ReadWritePaths = /etc/webapps/nextcloud/config
ReadWritePaths = /mnt/storage
```
\
Finally, restart services for changes to take effect
```
sudo systemctl restart nginx php-fpm
```

### 3.4 Nextcloud setup
You should now be able to access the nextcloud webpage. Let's make this work.
\
Put this in the form on the webpage
```
admin account
alf_admin1337
really_strong_password

Data folder
/mnt/wddrive/nextcloud/data/

Configure the database
USER (from when you setup the database user)
PASSWD (from when you setup the database user)
nextcloud
localhost
```
After submitting, I got a timeout. 504 bad gateway. reload page. reload the page, log in and wait...

## 4. Post-Install Steps
### 4.1 Set PHP environment variables properly
In the file `/etc/php/php-fpm.d/www.conf`
```
sudo vim /etc/php/php-fpm.d/www.conf 
```
Uncomment in the following lines
```
env[HOSTNAME] = $HOSTNAME
env[PATH] = /usr/local/bin:/usr/bin:/bin
env[TMP] = /tmp
env[TMPDIR] = /tmp
env[TEMP] = /tmp
```
Reload php-fpm service
```
systemctl reload php-fpm
```
### 4.2 PHP Caching
https://wiki.archlinux.org/index.php/PHP#Caching
Make nextcloud faster by enabling php caching
\
In /etc/php/php.ini
```
sudo vim /etc/php/php.ini
```
Uncomment the line 
```
zend_extension=opcache.so
```
In the same file, add these lines at the end of the extensions list
```
extension=apcu
apc.enabled=1
apc.shm_size=32M
apc.ttl=7200
apc.enable_cli=1
```
Save and exit
\
Now in the nextcloud config file `/usr/share/webapps/nextcloud/config/config.php`
```
sudo vim /usr/share/webapps/nextcloud/config/config.php
```
Add this line at the end of the file 
```
'memcache.local' => '\OC\Memcache\APCu',
```
Restart services
```
sudo systemctl reload nginx php-fpm
```

### 4.3 Nextcloud Cron jobs
Add crontab entry // ATTENTION!!! vi might open as default editor
```
EDITOR=vim crontab -u nextcloud -e
```
Add this line 
```
*/15  *  *  *  * php -f /usr/share/nginx/nextcloud/cron.php
```
Start and enable service
```
systemctl start cronie.service
systemctl enable cronie.service
```
Set CRON radio button at Nextcloud Admin page

### 4.4 BIG file upload (up to 16GB)
16GB or whatever limit your heart desires should you want to increase it even more
In file `/usr/share/webapps/nextcloud/.user.ini`
```
sudo vim /usr/share/webapps/nextcloud/.user.ini 
```
Add these lines
```
upload_max_filesize = 16G
post_max_size = 16G
memory_limit=1G
output_buffering=0
```
\
In file `/etc/php/php.ini`
```
sudo vim /etc/php/php.ini
```
Search for and set these variables accordingly
```
post_max_size = 16G
upload_max_filesize = 16G
max_input_time = 3600
max_execution_time = 3600
output_buffering = Off
upload_tmp_dir = /mnt/storage/upload_tmp_dir
```
\
In file `/etc/nginx/conf.d/nextcloud.conf`
```
sudo vim /etc/nginx/conf.d/nextcloud.conf
```
Set the `client_max_body_size` variable to `16G`
```
client_max_body_size 16G;
```
\
In file `/etc/nginx/nginx.conf`
```
sudo vim /etc/nginx/nginx.conf
```
In the http context (within the http {} block), add this line
```
client_body_temp_path /mnt/storage/upload_tmp_dir;
```
And in http, server, location php context, add this line
```
fastcgi_read_timeout 600;
```
So now that block looks like this
```
location ~ \.php$ {
   root           /usr/share/nginx/html;
   fastcgi_pass   unix:/run/php-fpm/php-fpm.sock;
   fastcgi_index  index.php;
   fastcgi_param  SCRIPT_FILENAME  $document_root$fastcgi_script_name;
   include        fastcgi_params;
   fastcgi_read_timeout 600;
}
```
\
Now lets make the new temporary upload location 
```
sudo mkdir /mnt/storage/upload_tmp_dir
sudo chown nextcloud:nextcloud /mnt/storage/upload_tmp_dir -R
```
And restart the nginx and php services
```
sudo systemctl restart nginx php-fpm
```

### 4.5 HTTPS SSL Encryption Setup
First make sure that you've added the trusted domains you need to nextcloud config
```
sudo vim /usr/share/webapps/nextcloud/config/config.php
```
Add your domain to the `trusted_domains` array
\
Install certbot and generate a certificate for your server
```
sudo pacman -Sy certbot cerbot-nginx
sudo certbot --nginx -d yourdomainhere.com
```
Follow the instructions that certbot gives you
\
Setup a cron job to auto renew your certificate once a month
```
EDITOR=vim sudo crontab -e
```
And add this line
```
1 2 3 * * sudo certbot renew
```

## 5. Resolving some nextcloud annoyances
Under "Administration Settings", you will probably get some warnings. Here's a partial list of solutions to some of them

#### The "X-Robots-Tag" HTTP header is not set to "noindex, nofollow". This is a potential security or privacy risk, as it is recommended to adjust this setting accordingly.
In file `/etc/nginx/conf.d/nextcloud.conf``
```
sudo vim /etc/nginx/conf.d/nextcloud.conf
```
Change all instances of `X-Robots-Tag` from none to `"noindex, nofollow"`
```
add_header X-Robots-Tag "noindex, nofollow";
```
And restart services
```
sudo systemctl restart nginx php-fpm
```

#### Your web server is not properly set up to deliver .woff2 files. This is typically an issue with the Nginx configuration. For Nextcloud 15 it needs an adjustement to also deliver .woff2 files. Compare your Nginx configuration to the recommended configuration in our documentation ↗.
In file `/etc/nginx/conf.d/nextcloud.conf`
```
sudo vim /etc/nginx/conf.d/nextcloud.conf
```
Add these lines to the `server` context
```
location ~ \.woff2?$ {
   try_files $uri /index.php$request_uri;
   expires 7d;         # Cache-Control policy borrowed from `.htaccess`
   access_log off;     # Optional: Don't log access to assets
}
```
And restart services
```
sudo systemctl restart nginx php-fpm
```

#### The PHP module "imagick" is not enabled although the theming app is. For favicon generation to work correctly, you need to install and enable this module.
Install imagick package
```
sudo pacman -Sy php-imagick
```
Enable it in `/etc/php/php.ini`
```
sudo vim /etc/php/php.ini
```
Add this line
```
extension=imagick
```
Restart services
```
sudo systemctl restart nginx php-fpm
```