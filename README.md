![logo](https://i.imgur.com/YAME6yT.png "#FrostyBot")

## IMPORTANT NOTICE

:warning: **Deprecation Notice**: This version of Frostybot is no longer under active development. We highly recommend that you check out [Frostybot-JS](https://github.com/CryptoMF/frostybot-js) instead. It's built using NodeJS so Apache and PHP are not required.

-----

## Summary

FrostyBot is a minimal endpoint that is designed to be used with webhooks in Tradingview alerts. It is designed to work cryptocurrency exchanges, specifically Bitmex, Deribit, FTX and Binance Futures (more will be added in future). It receives simple commands and translates them to specific exchange orders and sends them to the exchange. This allows you to write your strategies and do your backtesting on Tradingview (or python, or whatever you like), and then easily integrate your strategies with your exchange once you are happy with the backtest. This then allows you to use a Tradingview strategy or study as your trading engine, with Frostybot merely facilitating the integration to your exchange. It also allows you to integrate your exchange with any other product that can send webhooks, such as Telegram or Discord.

The way it works is simple:

* There are four main trading commands, namely **long**, **short**, **close** and **cancel**. Depending on the parameters you give these commands, you can open long or short positions, close positions, take profit (partial close) or cancel existing unfilled orders. The bot supports both limit and market orders and the commands are triggered either via a Tradingview alert webhook, or manually by you via the commandline.
* The size of the order is given using the size=xxx parameter, and can either be given in USD such as size=10000. or by a factor of your account balance, such as size=2x or size=200%
* If you specify the price using the price=xxx parameter, a limit order will be used. If you omit the price, a market order will be used at the current price.
* Going short if you are already long will place a short order for the size of your existing long position plus the amount of contracts you requested short, leaving you short the size requested. For example, if you are already long 10000 contracts and you enter a short order for 5000 contracts, the bot will actually place a sell order for 15000 contracts, leaving you 5000 contracts short after the order is filled (vice versa for short to long flips). This is done to simplify the bot logic to be written in Pinescript.
* If you are long 10000 contracts, and you enter a 15000 long order, the bot will place a buy order for only 5000 contracts, leaving you 15000 contracts long when the order is filled. If you are already long more contracts than requested, no additional order will be placed (vice versa for short orders). This allows you to control the maximum size you would like to be long or short, irrelevent of how many times Pinescript triggers the order.
* Anything that can trigger an alert in Tradingview can be used with this bot for trade execution, such as the crossing of a trendline, EMA crosses, etc. If you create your own Pinescript studies, use the alertcondition function to define alerts for buy, sell, stop loss and take profit orders as you need, and then create alerts on those alert conditions to trigger the bot. The possibilities are endless!

## Authors

Developers listed below can normally be found on the [FrostyBot Discord Server](https://discord.gg/txPew7ntmn). They are also known to frequent #the-lab channel on [Krown's Crypto Cave Discord Server](https://discordapp.com/invite/hzKU7qe):

* FrostyAF
* Barnz
* SemiQuasi

We kindly ask that you fully read the documentation before requesting any support, as most of the questions we get asked are already well documented.

## Dedication

Dedicated to @christiaan's mom, an all-round classy lady.

## Disclaimer
Use this bot at your own risk. The authors accept no responsibility for losses incurred through using this software. This is a 0.x release which means it's beta software. So it may and probably will have some bugs. We strongly advise you to use a sub-account with a limited balance, or a testnet account to ensure that the bot is profitable before going live with any strategy. While we have gone to great lengths to test the software, if you do find any bugs, please report them to us in the [FrostyBot Discord Server](https://discord.gg/yK4U93s) or on Github, and we will sort them out. Remember that risk management is your responsibility. If you lose your account, that's entirely on you.

## Supported Exchanges
Currently Bitmex, Deribit and FTX exchanges are supported for perpetual and futures markets. For a list of markets supported on your exchange, use the \<exchange\>:markets command. The bot has been extensively tested on these markets, but should work on others as well:

* **Bitmex:**  BTC/USD and ETH/USD
* **Deribit:** BTC-PERPETUAL and ETH-PERPETUAL
* **FTX:** BTC-PERP and ETH-PERP
* **Binance Futures:** BTC/USDT and ETH/USDT

We will add more exchanges based on user demand. Keeping the code cross-compatible over all the exchanges is quite complex so we will only consider adding additional exchanges if a significant number of users request it. Bear in mind that this software is free, so we are under no obligation to add features or provide support, but we will endevour to help out when and where we can. Support can also be found in the [FrostyBot Discord Server](https://discord.gg/yK4U93s). There are a lot of very knowlegeable guys willing to help out if you need assistance.

## Scope      
This bot is specifically designed to execute orders based on webhook alerts received from Tradingview (or from any other system that can send webhooks, such as Discord, Telegram etc). If you have used Autoview in the past you will understand the concept of converting Tradingview strategies and scripts into executable orders on your exchange. However, unlike Autoview, Frostybot makes use of an external web server (VPS, AWS etc), so does not require your PC to be powered on or your browser to be open for it to work.

While is may seem a little complicated to setup and configure for novices, the point of Frostybot is that it's entirely free. You retain total control over your API keys and trading engine. It is perfect for integration projects where you have a trading engine (like a Tradingview strategy or some custom Python script) that you want to integrate with your exchange.

## Requirements
In order to use Frostybot, you will need the following:
* A Linux server which is publicly accessible over the Internet (either by public IP address, DDNS or DNAT). You must be able to access the web server publicly for Frostybot to work. We recommend using the free Ubuntu server available on [Amazon Lightsail](https://lightsail.aws.amazon.com). They also offer a free public IP address. There is also [a handy guide here](https://github.com/CryptoMF/frostybot/blob/master/LIGHTSAIL.md) which will show you how to set up your Lightsail VPS and Frostybot.
* Apache2/Nginx (whichever you prefer). It is **your** responsibility to secure your web server. We accept no responsibility if your server is hacked. We have provided the necessary *.htaccess* file, and it is up to you to ensure that Apache uses it (AllowOveride All setting). We also highly recommend that you secure it with HTTPS (using [LetsEncrypt](https://www.digitalocean.com/community/tutorials/how-to-secure-apache-with-let-s-encrypt-on-ubuntu-18-04) or something similar). It's free, so there's really no excuse for having an insecure web server. If you're using Amazon Lightsail, [here is a guide](https://github.com/CryptoMF/frostybot/blob/master/LIGHTSAILSSL.md) on how to set up a domain name and SSL certificate for your bot.
* PHP 7.2 or higher. You will need the following PHP modules installed (php-curl, php-mbstring, php-json, php-sqlite3, php-cli), and you will need to ensure that PHP is configured on your web server.
* If you want to clone Frostybot from Github, you will need to have git installed.

*Note:* We have a [handy install script](https://github.com/CryptoMF/frostybot/blob/master/INSTALLATION.md#automatic-installation-using-install-script) to automatically install and configure Apache, PHP and Frostybot. Apache and PHP will only be installed if no web server is detected. It will also install git, nano and wget if they are not already installed.

## Installation

* We have a quick and dirty walkthrough on how to [setup Frostybot on Amazon Lightsail](https://github.com/CryptoMF/frostybot/blob/master/LIGHTSAIL.md)
* There is also a tutorial on how to [configure a domain name and SSL for your Lightsail instance]((https://github.com/CryptoMF/frostybot/blob/master/LIGHTSAILSSL.md)
* If you don't want to use Amazon Lightsail, check out our [Installation and Configuration Guide](https://github.com/CryptoMF/frostybot/blob/master/INSTALLATION.md).

## Changelog

The current version of FrostyBot is 0.9. This version constitutes a massive overhaul of the entire codebase and is basically a redevelopment from scratch, with loads of new features and capabilities.

A big shoutout to everybody who has contributed and collaborated on this project. The changelog is available at [FrostyBot Changelog](https://github.com/CryptoMF/frostybot/blob/master/CHANGELOG.md).

## Usage
Its recommended to use sub-accounts to limit risk. First follow the instructions in the [Install Guide](https://github.com/CryptoMF/frostybot/blob/master/INSTALLATION.md). Then configure your Tradingview alerts to call the webhook using the appropriate commands:

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
      deribitdemo:short size=5000 price=8000 symbol=BTCUSD        (This example uses a symbol mapping in the config to convert BTCUSD to BTC-PERPETUAL)
      deribitdemo:short size=5000 price=8000                      (This example uses the "default" symbol mapping in the config which is mapped to BTC-PERPETUAL)    

*Note:* The order size is always in USD, and the symbol is required for most exchange-specific commands (unless a default symbol mapping has been provided in the config file, in which case that default symbol will be used if no symbol is provided in the command).

To get a list of supported symbols for an exchange, use the following command:

    ./frostybot deribitdemo:markets

* Once all your accounts are configured, ensure that the db/, and log/ directories are writable by the account that you use you run your web server (for example, www-data on Ubuntu). If for some reason you cannot make those directories writable, you can change the location of the directories in the cfg/cfg.config.php file. If you do change those settings, remember to relocate the directories to the new location. Check out the [FrostyBot Install Guide](https://github.com/CryptoMF/frostybot/blob/master/INSTALLATION.md) for more information on how to do this correctly.
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

Once you have found a winning strategy (or made your own using Pinescript on Tradingview), you can set up multiple alerts for long, short, and close (takeprofit/stoploss) orders and let Tradingview and Frostybot fully automate your trading!

*Sample study in Tradingview showing buy, sell and take profit alert points*

![TV Strategy](https://i.imgur.com/isAkusb.png)

*List of tradingview alerts showing long, short and take profit orders*

![List of alert](https://i.imgur.com/qM4OwFJ.png)

**CLI Examples:**

Other than Tradingview firing off webhooks, you can also communicate manually with Frostybot using the commandline interface (CLI). Here are some CLI examples that you can try out.

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
    ./frostybot ftx:cancelall symbol=ADA-PERP                   (Cancel all ADA-PERP orders on FTX)
    ./frostybot bitmex:positions                                (Show current positions on Bitmex, if any)
    ./frostybot deribit:position symbol=BTC-PERPETUAL           (Show current BTC-PERPETUAL position on Deribit)
    ./frostybot ftx:ohlcv symbol=BTC-PERP timeframe=1h          (Show current hourly OHLCV data for FTX BTC-PERP)
    ./frostybot ftx:ohlcv symbol=BTC/USD timeframe=720          (Show current 12hr OHLCV data for Bitmex BTC/USD)
    ./frostybot config                                          (Show your current configuration)
    ./frostybot log                                             (Show log file for bot, last 20 lines by default)
    ./frostybot log lines=50                                    (Show the last 50 log file lines)
    ./frostybot log filter=error                                (Show all lines containing errors)
    ./frostybot log clear=true                                  (Clear the log file)
    ./frostybot flushcache                                      (Flush the cache file, for troubleshooting)

*NOTE:* The command syntax for Tradingview is exactly the same as for the CLI (except for the addition of ./frostybot in front of the command on the CLI). Any command that you can run on the CLI you can also run using webhooks. While this may not necessarily be useful for Tradingview, it can come in handy if you want to integrate something else with Frostybot. All Frostybot output is in JSON, which makes external integration, like scripting and charting quite simple.

**More examples:**

This command will create a limit-entry, market-stop-loss, limit-take-profit order combo.
NOTE: Both limit entries are NOT a reduce-only unless a reduce=true is used and the exchange supports it (FTX only):

    ./frostybot deribit:long price=9593 stoptrigger=9500 profittrigger=9750    

To workaround this limitation, you can use stop-market/reduce-only order for both stop-loss and take-profit on Deribit. This command will create a limit-entry, market-stop-loss (reduce-only) and market-take-profit (reduce-only) order. Assuming the market price is in between the 9500 and 9800, the correct market stop-loss orders should be placed as the bot will calculate the direction of the order based on the market price.

    ./frostybot deribit:long size=1000 price=9550
    ./frostybot deribit:stoploss size=1000 stoptrigger=9500 reduce=true
    ./frostybot deribit:stoploss size=1000 stoptrigger=9800 reduce=true

This example will create a set of 5 orders spread out within the provided price range:

    ./frostybot deribit:long size=1000 price=9550,9600

To ask for a specific number of orders, use a 3rd argument after the price range which stands for quantity:

    ./frostybot deribit:long size=1000 price=9550,9600,3

**Entry/Stop trade based on predefined risk:**

The *long* and *short* commands will also let you set up an entry/stop-loss trade using a predefined risk (either a percentage of the account or dollar amount). Below examples show a long entry that will automatically calculate the size based on the risk (which is 10% of the account and 50 USD respectively):

    ./frostybot deribit:long risk=10% price=9545 stoptrigger=9445 reduce=true
    ./frostybot deribit:long risk=50 price=9545 stoptrigger=9445 reduce=true

You can still use the relative price (by adding +/-). Also, you can skip the *price* variable to enter with a market order, e.g.:

    ./frostybot deribit:long risk=10% price=-100 stoptrigger=-200 reduce=true
    ./frostybot deribit:long risk=10% stoptrigger=-200 reduce=true

NOTE: The relative price is always based of the current market price, so make sure that stoptrigger is not closer to the price than the entry.

**Simple Buy/Sell Commands and Order Pyramiding:**

    ./frostybot deribit:long size=+2000 maxsize=10000
    ./frostybot deribit:long size=-10000 maxsize=50000
    ./frostybot deribit:short size=+500 price=+50 maxsize=5000
    ./frostybot deribit:short size=-1000 price=+50 maxsize=5000

The size parameter for long and short commands can be given in relative terms. For example, size=+1000 will increase current position by $1000, or size=-500 will decrease current position size by $500. You can also provide the optional maxsize parameter (highly recommended) to limit the maximum total position size, for example a long command, which size=+1000 maxsize=10000, when executed sequentially, will increase your long position by $1000 each time the command is executed, up to a maximum of $10000. Likewise, a short command with size=+1000 maxsize=10000, when executed sequentially will increase your short position by $1000 each time the command is executed, to a maximum of $10000. Using negative numbers for the size parameter will decrease your long or short position size, down to zero, but will not flip your position if the size goes below zero. These commands are useful for pyramiding, but to not support and stoptrigger or profittrigger paramters. Your stop losses and take profit orders must be managed separately.

    ./frostybot deribit:buy size=2000 maxsize=10000
    ./frostybot deribit:sell size=10000 maxsize=50000 price=4000
    ./frostybot deribit:buy size=1000 maxsize=20000 price=-100

The buy and sell commands create simple buy and sell orders on the exchange. Only the size, maxsize (optional) and price (optional) parameters are supported. If the resulting buy or sell command would cause your position size to exceed the maxsize parameter, the order size will be automatically adjusted to ensure that your position never exceeds maxsize, be it a long position or a short position. Please note that when using the buy and sell commands, you cannot use relative numbers for the size parameter. The size parameter must be a positive absolute number indicating the size of the buy or sell order in USD. If you execute a sell command that takes your long position below zero, you will be automatically flipped to a short position. These commands are useful for pyramiding, but to not support and stoptrigger or profittrigger paramters. Your stop losses and take profit orders must be managed separately.

## Report Bugs

If you find a bug in the software, kindly report it [here](https://github.com/CryptoMF/frostybot/issues/new) so that we can properly track it.
