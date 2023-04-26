# Access to google contacts through the google people API

 ![Latest Stable Version](https://img.shields.io/badge/release-v1.0.0-brightgreen.svg)
 ![License](https://img.shields.io/packagist/l/gomoob/php-pushwoosh.svg) 
 [![Donate](https://img.shields.io/static/v1?label=donate&message=PayPal&color=orange)](https://www.paypal.me/SKientzler/5.00EUR)
 ![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.4-8892BF.svg)
 [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Stefanius67/GContacts/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Stefanius67/GContacts/?branch=master)
 
----------
## Overview

This package provides all classes and functions to manage the contacts of a Google account.
Access is via the Google Perons API with OAuth2 authentication.
The following functionalities are supported:
- Login and authentication to the google account
- List contacts
- Search in contacts
- Filter contacts per conatct group (-> Label)
- create / edit / delete contacts
- list contact groups
- create / edit / delete contact groups
- assign /remove contacts to contact groups
- set / remove contact photos

The package only uses the standard **PHP cURL library** and have no further dependencies 
to any other 3rd party libraries or packages.

The fact that on almost every mobile phone that runs under Android, the contacts are managed either 
with the Google Contacts or with a compatible app and are also automatically be synchronised with 
the Google account contacts (with the appropriate setting), makes this package interesting for 
management- and/or synchronization purposes.

## Usage
A smart example of using the package is provided. This example is only intended to demonstrate 
the use of the package. The UI is only coded 'quick and dirty', contains no validations and should
therefore only be used as a starting point for your own implementation.

Take a lock at the files
- ContactList.php
- ContactDetails.php
- DoAction.php
- GoogleLogin.php
- GoogleOauth2Callback.php

**The starting point is the file ´ContactList.php´**

A complete documentation of the classes and a detailed description, how to create your own
credential to access the google API can be found at https://github.com/Stefanius67/GContacts/wiki

## Logging
This package can use any PSR-3 compliant logger. The logger is initialized with a NullLogger-object 
by default. The logger of your choice have to be passed to the constructor of the ´GClient´ class. 

If you are not working with a PSR-3 compatible logger so far, this is a good opportunity 
to deal with this recommendation and may work with it in the future.  

There are several more or less extensive PSR-3 packages available on the Internet.  

You can also take a look at the 
 [**'XLogger'**](https://www.phpclasses.org/package/11743-PHP-Log-events-to-browser-console-text-and-XML-files.html)
package and the associated blog
 [**'PSR-3 logging in a PHP application'**](https://www.phpclasses.org/blog/package/11743/post/1-PSR3-logging-in-a-PHP-application.html)
as an introduction to this topic.

