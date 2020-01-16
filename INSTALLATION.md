![logo](https://i.imgur.com/YAME6yT.png "#FrostyBot")

## Installation Guide

This document is aimed at being a comprehensive installation guide to get you up and running on FrostyBot.

### Prerequisites

If order to use Frostybot, you will need the following:
* A Linux server which is publicly accessible over the Internet (either by public IP address, DDNS or DNAT). You must be able to access the web server publicly for Frostybot to work. We recommend using the free Ubuntu server available on [Amazon Lightsail](https://lightsail.aws.amazon.com). They also offer a free public IP address. There is also [a handy guide here](https://github.com/CryptoMF/frostybot/blob/master/LIGHTSAIL.md) which will show you how to set up your Lightsail VPS and Frostybot.
* Apache2/Nginx (whichever you prefer). We highly recommend that you secure it with HTTPS (using [LetsEncrypt](https://www.digitalocean.com/community/tutorials/how-to-secure-apache-with-let-s-encrypt-on-ubuntu-18-04) or something similar). It's free, so there's really no excuse for having an insecure web server. 
* PHP 7.2 or higher. You will need the following PHP modules installed (php-curl, php-mbstring, php-json, php-sqlite3, php-cli), and you will need to ensure that PHP is configured on your web server.
* If you want to clone Frostybot from Github, you will need to have git installed. If you do not want to install git, you'll at least require the wget and unzip packages installed for the alternative install option.

*Note:* We have a handy install script to automatically install and configure Apache, PHP and Frostybot. Apache and PHP will only be installed if no web server is detected. It will also install git, nano and wget if they are not already installed. If you'd rather perform the installation manually, you can either use git or you can download and unzip the zip file. Both procedures are provided [here](https://github.com/CryptoMF/frostybot/blob/master/INSTALLATION.md#manual-installation). The choice is yours!

### Automatic Installation Using Install Script

**Prerequisites**

* The automatic install script has been tested on Ubuntu and CentOS operating systems, as well as Apache and Nginx web servers. If your configuration differs to this, the install script may not run as expected. If a web service is not detected on your server, the install script will automatically install and configure Apache and PHP. It will also install git, nano and wget if they are not already installed. It will then proceed to install Frostybot and configure the correct filesystem permissions. It will not configure Frostybot, that is up to you to do in the cfg/cfg.config.php file, as explained [later in this document](https://github.com/CryptoMF/frostybot/blob/master/INSTALLATION.md#post-installation-configuration).

**Quick Automatic Install Script**

*NOTE:* This script will install and configure Apache, PHP, git, nano, wget and Frostybot on a clean Ubuntu or CentOS system. It will also install and configure firewalld, and allow SSH, HTTP and HTTPS by default. If you already have a web server installed, then the script will only install Frostybot and set the optimal filesystem permissions.

* Download the install script and make it executable:

      wget -4 https://tinyurl.com/frostybot-installer -O /tmp/install.sh
      chmod 700 /tmp/install.sh
      
* Now run the install script.

      /tmp/install.sh
      
  *NOTE:* The script may ask you for the sudo password, simply enter it when prompted.

  The output of the commands above should look similar to this:
  ![screenshot0](https://i.imgur.com/rSFgb7F.png "Screenshot")

   If the installation is successful, you can proceed to [post-installation configuration](https://github.com/CryptoMF/frostybot/blob/master/INSTALLATION.md#post-installation-configuration). If the install fails, try performing an advanced automatic installation or a manual installation using the procedures below.     

**Advanced Automatic Install Options**

The install script will try to automatically detect if you are running Apache or Nginx, and will automatically use the install path "\<web server root\>/frostybot". If you would prefer to specify the install path, you can do so using the script like this:
  
      /tmp/install.sh [install path]
  
  You need to replace \[install path\] with the path you would like to install Frostybot to. Do not create this path, it will be automatically created by the install script. 
  
  The script will also try to automatically determine your web server's user account (like www-data/nobody/nginx etc). If the script is unable to determine the account automatically, or if you would like to override this, you can specify the account on the commandline like this:
  
      /tmp/install.sh [install path] [user account]
  
  Replace \[user account\] with the user account that your web server runs as, so that it will be able to read the Frostybot files. The install script will automatically set the appropriate filesystem permissions.

  If the installation is successful, you can proceed to [post-installation configuration](https://github.com/CryptoMF/frostybot/blob/master/INSTALLATION.md#post-installation-configuration). If the install fails, try performing a manual installation using the procedures below.

### Manual Installation

If for some reason the install script does not work for you, you can instead manually install Frostybot using Git or Unzip. Both procedures are provided below (Note that you will need to install and configure your web server and PHP yourself):

**Manually Install Using Git**

* Firstly, ensure that you have the git program installed. Here is the command to install it on Ubuntu

      sudo apt install -y git
      
  If you are using Redhat/CentOS/Fedora, use this command to install git:

      sudo yum install -y git

* Next, change to your home directory. This will ensure that you have write permission to download the source

      cd ~

* Next, run git clone to download the software

      git clone https://github.com/CryptoMF/frostybot.git
    
  You should see output similar to the following:
  
  ![screenshot1](https://i.imgur.com/5rm1bMX.png "Screenshot")
 
* Next, move the frostybot directory to your web root directory (/var/www/html/ on Ubuntu using Apache) using the following command. You will need sudo privileges to complete this step. If your web root is different to /var/www/html then replace it with the relevant directory.

      sudo mv frostybot /var/www/html/

**Manually Install Using Zip File**

If you would prefer not to use git, you can also manually download and unzip the package as shown below:

* Firstly, ensure that you have the unzip package installed. Here is the command to install it on Ubuntu

      sudo apt install -y unzip
      
  If you are using Redhat/CentOS/Fedora, use this command to install unzip:

      sudo yum install -y unzip
       
* Next, change to your home directory. This will ensure that you have write permission to download the source

      cd ~

* Next, run wget to download the software

      wget https://github.com/CryptoMF/frostybot/archive/master.zip
  
  You can then proceed to unzip the master.zip package
  
      unzip master.zip
    
  You should see output similar to the following:
  
  ![screenshot2](https://i.imgur.com/ViAiBWY.png "Screenshot")
 
* Next, move the frostybot directory to your web root directory (/var/www/html/ on Ubuntu using Apache) using the following command. You will need sudo privileges to complete this step. If your web root is different to /var/www/html then replace it with the relevant directory.

      sudo mv frostybot-master /var/www/html/frostybot

**Manually Set File and Folder Permissions (not required if you installed using the install script)**

* Next we need to configure directory ownership and permissions. Run the following commands (replace /var/www/html with your web root directory if it is different):

      cd /var/www/html
      sudo chgrp -R www-data frostybot
      sudo chmod -R 640 frostybot
      sudo chmod -R 660 frostybot/db
      sudo chmod -R 660 frostybot/log
      sudo chmod 750 frostybot/frostybot
      sudo chmod -R +X frostybot
      
  This should provide access to both your account and the web server.

## Post-Installation Configuration
      
* At this stage you should already be able to access the bot by browsing to http://yourdomain.com/frostybot/  - You should receive a response similar to this:
    
      {
        "results": {
            "code": 900,
            "message": "Request received from invalid address: 123.69.123.69",
            "type": "ERROR"
        },
        "messages": []
      }

 * What this response tells us is that the bot is responding as expected over http. At this point in time you can install an SSL certificate or configure a custom domain for your bot if you like. The actual procedures for doing this are not in this guide, but there are plenty tutorials available on the internet [like this one](https://www.digitalocean.com/community/tutorials/how-to-secure-apache-with-let-s-encrypt-on-ubuntu-18-04) to help you complete these tasks.
 
 * Add your Exchange API account information to the bot configuration using the **config** command as follows:

       ./frostybot config stub=deribitdemo exchange=deribit apiKey="<your api key>" secret="<your api secret>" description="Deribit Test Account" testnet=true

  The **stub** parameter can be anything you like, as long as it's alphanumeric with no spaces. You will use the **stub** when sending commands to the bot, so make it something short and simple. I've just called it "deribitdemo", but if you wanted to use 2 accounts on deribit, you could call the one stub "deribitmain" and the other one "deribittest" for example, it's entirely up to you.
  
  The **exchange** parameter can be ftx, bitmex or deribit, depending on which exchange you use. The **apiKey** and **secret** are self explanatory, simply add your own api key and secret there (Important: notice the uppercase K in apiKey, it's important to keep it like that). The **description** field can be anything you like, but if you use spaces remember to enclose it in "quotes". Lastly, the **testnet** parameter just lets the bot know which network to connect to. If you want to test it out for a bit, create an api key on the testnet and try it out. The testnet parameter only works for Deribit and Bitmex, because FTX does not have a testnet.
  
  Lastly, if you are using the FTX exchange, and you are using a subaccount, please also add **subaccount**="\<sub account name\>". This is required by the FTX API, and it will not work until you add it to the config, but only if you're using subaccounts.
  
* You can list your current config by using the following command:

       ./frostybot config
       
  If you would like to remove a config, use the command like this:
  
       ./frostybot config stub=deribitdemo delete=true
  
  If you just want to update an existing config, just rerun the config command with the same stub name and the other values will be updated.

* Using the deribitdemo config I made above, some example command syntax would be as follows: 
    
      deribitdemo:long size=5000 price=8000 symbol=BTC-PERPETUAL  (This example provides the symbol on the commandline)
      deribitdemo:short size=5000 price=8000 symbol=BTCUSD        (This example uses a symbol mapping in the config o convert BTCUSD to BTC-PERPETUAL)
      deiribitdemo:short size=5000 price=8000                      (This example uses the "default" symbol mapping in the config which is mapped to BTC-PERPETUAL)    
    
*Note:* The order size is always in USD, and the symbol is required for most exchange-specific commands (unless a default symbol mapping has been provided in the config file, in which case that default symbol will be used if no symbol is provided in the command).

To get a list of supported symbols for an exchange, use the following command:

    ./frostybot deribitdemo:markets

* Once all your accounts are configured, ensure that the db/, and log/ directories are writable by the account that you use you run your web server (for example, www-data on Ubuntu). If for some reason you cannot make those directories writable, you can change the location of the directories in the cfg/cfg.config.php file. If you do change those settings, remember to relocate the directories to the new location. 
* Run some FrostyBot CLI commands to check if you have done the configuration correctly and to ensure that you are able to reach the exchange (Some CLI commands examples are listed further below).
* Check that your bot is accessible over the public internet by browsing to it's address. You should see a message similar to the following:

    {
        "results": {
            "code": 900,
            "message": "Request received from invalid address: 123.69.123.69",
            "type": "ERROR"
        },
        "messages": []
    }

*Note:* This error is expected, but its a good way to check if your bot is responding over http/https. You will receive this specific error because the bot has built-in security that will only accept http/https requests from Tradingview's servers. You can still communicate directly with Frostybot using CLI commands. If you need to communicate to the bot over http, you will need to add your IP address into the "whitelist" by using the following command:

        ./frostybot whitelist add="<ip address>" description="An optional description for your own info"
       
By default, only Tradingview's servers have access over http/https. Any other machines that you want to allow to access the bot will need to be added to the whitelist. If you want to remove an IP address from the whitelist, use this command:

        ./frostybot whitelist delete="<ip address>"

Note that you cannot delete the default Tradingview addresses in the whitelist, as they are protected against deletion. 

Once you've confirmed that Frostybot is responding over the internet, you can start creating Tradingview alerts:

### Tradingview Alerts and Webhook Configuration

  Once you've confirmed that Frostybot is responding over the internet, you can start creating Tradingview alerts:

* In Tradingview, create a new alert.
* The Webhook URL should point to your bot address.
* The message box of the Tradingview alert should have the bot command(s), here are some exmaples:
         
**Tradingview Alert Messagebox Examples:**

    deribit:long symbol=BTC-PERPETUAL size=1000         ($1000 market buy on Deribit BTC-PERPETUAL)
    ftx:short symbol=BTC-PERP size=2000 price=7600      ($2000 limit sell at $7600 on FTX BTC-PERP)
    bitmex:long symbol=BTC/USD size=3x                  (3x long market buy on Bitmex BTC/USD)
    ftx:close size=50% symbol=ETH-PERP                  (Market close 50% of oposition on FTX ETH-PERP)
    deribit:close symbol=ETH-PERPETUAL                  (Close entire ETH-PERPETUAL position on Deribit)
    ftx:short size=200% symbol=BTC-PERP                 (Market sell 200% of your account / 2x short)
      
*NOTE:* Anything in the message box of the Tradingview alert is interpreted as a command, so do not use the message box for a general description! Also note, you can provide multiple commands in the message box, as long as they are each on a new line. For example, you can close all your open stop loss orders before entering a new position.

Here is an example of a Tradingview alert showing multiple commands:

![TV Alert](https://i.imgur.com/p8YFTah.png)
     
### Command Line Interface (CLI) Usage

Other than Tradingview firing off webhooks, you can also communicate manually with Frostybot using the commandline interface (CLI). Here are some CLI examples that you can try out.
 
**CLI Examples:** 

    ./frostybot deribit:long symbol=BTC-PERPETUAL size=1000     ($1000 market buy on Deribit BTC-PERPETUAL)
    ./frostybot ftx:short symbol=BTC-PERP size=2000 price=7600  ($2000 limit sell at $7600 on FTX BTC-PERP)
    ./frostybot bitmex:long symbol=BTC/USD size=3x              (3x market buy on Bitmex BTC/USD)
    ./frostybot ftx:close size=50% symbol=ETH-PERP              (Market close 50% of position on FTX ETH-PERP)
    ./frostybot deribit:close symbol=ETH-PERPETUAL              (Close entire ETH-PERPETUAL position on Deribit)
    ./frostybot ftx:short size=200% symbol=BTC-PERP             (Market sell 200% of your account / 2x short)
    ./frostybot ftx:markets                                     (Show avaiable markets on FTX exchange)
    ./frostybot bitmex:market symbol=BTC/USD                    (Show market data for BTC/USD on Bitmex)
    ./frostybot ftx:trades                                      (Recent trades, oldest listed first)
    ./frostybot deribit:balance                                 (Current account balance on Deribit)
    ./frostybot bitmex:orders status=open                       (Open orders on Bitmex)
    ./frostybot ftx:cancel id=<orderid>                         (Cancel a specific order on FTX)
    ./frostybot deribit:cancel id=all                           (Cancel all open orders on Deribit)
    ./frostybot bitmex:cancelall                                (Cancel all open orders on Bitmex)
    ./frostybot ftx:cancel symbol=ADA-PERP                      (Cancel all ADA-PERP orders on FTX)
    ./frostybot bitmex:positions                                (Show current positions on Bitmex, if any)
    ./frostybot deribit:position symbol=BTC-PERPETUAL           (Show current BTC-PERPETUAL position on Deribit)
    ./frostybot ftx:ohlcv symbol=BTC-PERP timeframe=1h          (Show current hourly OHLCV data for FTX BTC-PERP)
    ./frostybot ftx:ohlcv symbol=BTC/USD timeframe=720          (Show current 12hr OHLCV data for Bitmex BTC/USD)
    ./frostybot config                                          (Show/modify your current configuration)
    ./frostybot whitelist add="45.32.65.2"                      (Add an IP address to the whitelist)
    ./frostybot log                                             (Show log file for bot, last 20 lines by default)
    ./frostybot log lines=50                                    (Show the last 50 log file lines)
    ./frostybot log filter=error                                (Show all lines containing errors)
    ./frostybot log clear=true                                  (Clear the log file)
    ./frostybot flushcache                                      (Flush the cache file, for troubleshooting)

*NOTE:* The command syntax for Tradingview is exactly the same as for the CLI (except for the addition of ./frostybot in front of the command on the CLI). Any command that you can run on the CLI you can also run using webhooks. While this may not necessarily be useful for Tradingview, it can come in handy if you want to integrate something else with Frostybot. All Frostybot output is in JSON, which makes external integration, like scripting and charting quite simple.

