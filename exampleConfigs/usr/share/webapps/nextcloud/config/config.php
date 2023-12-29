<?php
$CONFIG = array (
  'datadirectory' => '/mnt/storage',
  'logfile' => '/var/log/nextcloud/nextcloud.log',
  'apps_paths' => 
  array (
    0 => 
    array (
      'path' => '/usr/share/webapps/nextcloud/apps',
      'url' => '/apps',
      'writable' => false,
    ),
    1 => 
    array (
      'path' => '/var/lib/nextcloud/apps',
      'url' => '/wapps',
      'writable' => true,
    ),
  ),
  'instanceid' => 'autoGeneratedInstanceID',
  'passwordsalt' => 'autoGenereated',
  'secret' => 'autoGenerated',
  'trusted_domains' => 
  array (
    0 => '192.168.0.219',
    1 => 'yourdomainhere.com',
  ),
  'dbtype' => 'mysql',
  'version' => '27.1.4.1',
  'overwrite.cli.url' => 'http://192.168.0.219',
  'dbname' => 'nextcloud',
  'dbhost' => 'localhost',
  'dbport' => '',
  'default_phone_region' => 'US',
  'dbtableprefix' => 'oc_',
  'mysql.utf8mb4' => true,
  'dbuser' => 'databaseUser',
  'dbpassword' => 'databasePassword',
  'installed' => true,
  'memcache.local' => '\\OC\\Memcache\\APCu',
  'updater.secret' => 'autoGenerated',
  'mail_smtpmode' => 'smtp',
  'mail_smtpauth' => 1,
  'mail_sendmailmode' => 'smtp',
  'mail_from_address' => 'emailUsername',
  'mail_domain' => 'outlook.com',
  'mail_smtpport' => '587',
  'mail_smtphost' => 'smtp-mail.outlook.com',
  'mail_smtpname' => 'emailUsername@outlook.com',
  'mail_smtppassword' => 'emailPassword',
);