![logo](https://i.imgur.com/YAME6yT.png "#FrostyBot")

## Changelog

A huge thank you to all of those that have contributed and collaborated on FrostyBot over the past 6 months. A particular shoutout to @Barnz for his huge contribution to the bot. Many things have changed since I started out on this project, and this is still very much a work in progress. I hope to improve on it sugnificantly over the next year.

<table>
  <tr>
    <th>Version</th>
    <th>Changes</th>
  </tr>
  <tr>
    <td>0.1</td>
    <td><ul><li>Initial version</li></ul></td>
  </tr>
  <tr>
    <td>0.2</td>
    <td>
      <ul>
        <li>Code cleanup</li>
        <li>Removed the FLIPLONG and FLIPSHORT commands. Incorporated into LONGENTRY and SHORTENTRY.</li>
        <li>Added POSITION command to show current position</li>
        <li>Added size parameter</li>
      </ul>
    </td>
  </tr>
  <tr>
    <td>0.3</td>
    <td>
      <ul>
        <li>Added ORDERS and CANCEL command to show open orders and cancel orders (limit orders)</li>
        <li>Added price parameter (limit orders)</li>
        <li>Changed output of BALANCE, TRADES and POSITION commands to JSON</li>
        <li>Changed IP whitelist to const to prevent skull fuckery</li>
      </ul>
    </td>
  </tr>
  <tr>
    <td>0.4</td>
    <td>
      <ul>
        <li>Added size parameter support for % of balance (ie. 200pct = 2x)</li>
        <li>Added TAKEPROFIT command with size parameter as % of open order (ie. 50pct)</li>
      </ul>
    </td>  
  </tr>
  <tr>
    <td>0.5</td>
    <td><ul><li>Named changed to Frostybot so I don't get sued</li></ul></td>
  </tr>
  <tr>
    <td>0.6</td>
    <td>
<ul>
<li>ccxt installation via an install.sh script (and cleanup of 750 mb of unneeded files)\n- now bot is usable with testnet or alternate api endpoint urls (base api url is now configurable for exchange)</li>
<li>support bitmexico</li>
<li>flag to turn bot on and off</li>
<li>separate configs per exchange bot, with core functionality in lib extracted so we can have multiple bots without code dupes</li>
<li>allow for space separated command (same syntax as command line) in POST body. This enables:</li>
<li>simpler syntax for users in the alert message box of tradingview</li>
<li>allow pinescript to setup the commands (preconfigure a bot)</li>
<li>let the user override the command by editing the message box instead of a webhook url</li>
<li>webhook url can then just simply point to the bot without querystring which is a bit cleaner</li>
<li>support % besides pct for percentages which is convenient in POST and command line like 'long 50%'</li>
<li>changed api.
<ul>
<li>replaced LONGENTRY, LONGEXIT, SHORTENTRY, SHORTEXIT, TAKEPROFIT</li>
<li>with LONG, SHORT, CLOSE</li>
<li>where CLOSE is replacing LONGEXIT, SHORTEXIT and TAKEPROFIT</li>
<li>CLOSE works the same as LONG / SHORT with percentages or absolute sizes.</li>
</ul>
<li>support for max position (enter multiple longs in a row wont blow you up)</li>
<li>auto adjust order size in case the max limit is reached. Say max position is 100% of your account and you're 50% long. Adding a 'long 100%' would in theory be 150% account size. This last order is auto adjusted to 50% so we end up at the 
maximum position size (which we wanted).</li>
<li>changed api examples to use lower case (easier to type :-))</li>
<li>early exit with more descriptive warnings in case of typo's or wrong parameters.</li>
<li>placed readme and changelog in separate files</li>
<li>added usd balance to log</li>
<li>introduced many bugs, uglified the code :-)</li>
</ul>
</td>
  </tr>
  <tr>
    <td>0.7</td>
    <td>- skipped cause changelog not in sync with version numbers (0.6 was distributed as 0.7, whoops)</td>
  </tr>
  <tr>
    <td>0.8</td>
    <td><ul>
<li>refactor directory/file structure. added lib / config directories and fixed install script for that.</li>
<li>renamed folders and use exchange/pair structure which is more scalable.</li>
<li>currency pairs should now be easy to add in the future.</li>
<li>bot configs are smaller as exchange anomalies are solved in the code.</li>
<li>separated output functions into output.php</li>
<li>this means that the url structure also changes. see INSTALLATION anf README</li>
<li>removed install.sh from the distro. not needed anymore because we include ccxt in the distro for easier setup. licence is included and is MIT, so no issues.</li>
<li>added support for ETH on Deribit and Bitmex</li>
<li>removed hardcoded local ip, added global config for adding your local ip to the whitelist</li>
<li>added exchange configs so you don't have to repeatedly put your keys in for each currency pair</li>
<li>support main accounts in exchange.config.php (default)</li>
<li>can be overridden per bot subaccount in the currency_pair/config.php (recommended)</li>
<li>made main.php more generic. Removed all hardcoded refs to BTC.</li>
<li>added INSTALLATION guide with hopefully easy steps the get going</li>
<li>log file restructuring, placed outside the public www dir (was security issue)</li>
<li>added command 'log' to show the contents of the log file,</li>
<ul>
<li>by default shows last 10 lines</li>
<li>you can give the number of lines as a parameter</li>
</ul>
<li>added 'summary' command. gives an overview for currency pair:</li>
<ul>
<li>balance</li>
<li>position</li>
<li>orders</li>
</ul>
<li>added many comments to make it easier for users to configure the bot</li>
<li>add exchange errors to the log (in raw format)</li>
</ul></td>
  </tr>
  <tr>
    <td>0.9</td>
    <td><ul>
<li>support for FTX exchange added</li>
<li>redeveloped Frostybot from the ground up using object-oriented code as far as possible</li>
<li>all output is now in JSON object format to allow for easy integration and scripting</li>
<li>all account configs are now stored in the SQLite database. accounts can be added, updated and removed using the CONFIG command.</li>
<li>config allows for the mapping of custom symbols to market symbols so that you can keep the same custom symbols across multiple exchanges. This also allows you to specify a default symbol so that you do not need to enter the symbol param all the time. these mappings are stored in the SQLite database.</li>
<li>added open/high/low/close/volume (ohlcv) function to get data for future planned charting capabilities</li>
<li>added caching mechanism to speed up response time and reduce exchange traffic for certain functions (lib.cache.php). cache data is stored in the SQLite database.</li>
<li>added SQLLite support for ohlcv data and request caching and in preparation for future capabilities (lib.database.php)</li>
<li>added error and exception handlers to ensure that all messages are captured and outputted to JSON (lib.output.php)</li>
<li>exchange output normalizers are now extended classes and produce predefined objects (lib.classes.php) in a consistent way across all supported exchanges (lib.normalizer.*.php). </li>
<li>a lot of work done to ensure that all exchanges support the same commands in exactly the same way with the same output structure.</li>
<li>wrote emulation code to generate "ohlcv" data for Deribit, since the exchange lacks ohlcv support, but since this code has to generate ohlcv data from thousands of individual trades, it is extremely slow. To avoid this, there is now a centrallised API at api.frostytrading.com that will server ohclv data from deribit and bitmex. more information is further down.</li>
<li>added unit test capabilties to provide accelerated testing during development (lib.unittests.php)</li>
<li>added install script to aid with download and install of Frostybot (https://tinyurl.com/frostybot-installer)</li>
<li>introduced a new central FrostyAPI (https://api.frostytrading.com) which is currently being used to get OHLCV data for Bitmex and Deribit. In the future, this central API could be used by all the Frostybots for various enhancements, such as version control, usage statistics, backtesting, notifications, trade analytics etc. For now though, it's only being used for OHLCV data.</li>
<li>all caching, account information, symbol mappings and whitelist IP addresses are now stored in the SQLite database.</li>
<li>added support for stoploss and takeprofit orders.</li>
</ul>
</td>
  </tr>
</table>
