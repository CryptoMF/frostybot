![logo](https://i.imgur.com/YAME6yT.png "#FrostyBot")

## Create an Ubuntu Lightsail Instance on Amazon Web Services

This document is a quick and dirty walkthrough to show you how to set up a Amazon Lightsail Ubuntu VPS for use with Frostybot. 

### Create an Amazon Lightsail account

* You will need to signup at [Amazon Lightsail](https://lightsail.aws.amazon.com/) (it's only about $3.50 per month). 

* Once you have signed up and logged in, you will see your Lightsail dashboard.

  ![Lightsail1](https://i.imgur.com/JsTXxQ0.png)

### Create an Ubuntu Instance

* Click on the Create Instance button to create a new instance. Under the Pick Your Image Instance section, select Linux/Unix, then select OS Only, and then select Ubuntu 18.04, in the order as indicated on this screenshot:

  ![Lightsail2](https://i.imgur.com/e65cVF6.png)

* Under the Choose your Instance Plan section, select the cheapest option ($3.50 a month). This is more than sufficient to run Frostybot.

  ![Lightsail3](https://i.imgur.com/eIv78wn.png)

* Lastly, give your new instance a name under the "Identify your Instance" section

  ![Lightsail4](https://i.imgur.com/MuS401l.png)
  
* Then click the Create Instance button at the bottom to create the instance. It should take about 2 or 3 minutes to complete. While the instance is being created, its status will be "Pending" like in the screenshot below:

  ![Lightsail5](https://i.imgur.com/V5AFi1G.png)

* Once the instance has been created, its status will change to "Running". You can also see the IP address assigned to the instance. In this case its 52.28.96.113. This IP address is dynamic and will change every time your reboot the instance. For this reason we cannot use it for Frostybot, as Tradingview will not know which IP address to send the commands to.

  ![Lightsail6](https://i.imgur.com/5UT7vP2.png)

### Create a Static IP Address

* What we need to do now is create a static IP address that does not change when you reboot the instance. To do this, select the Networking tab in your Lightsail dashboard, and then click on the Create Static IP button.

  ![Lightsail7](https://i.imgur.com/sKBdIPt.png)

* Attach the static IP to your newly created instance and give the static IP a name. Then click the Create button to create the static IP address.

  ![Lightsail8](https://i.imgur.com/3QOt3xZ.png)

* You will then be presented with your new static IP adddress. This IP address will not change, even if you reboot the instance. You can also point a domain name such as frostybot.mydomain.com to this IP address if you want (the procedure to do this is not provided in this document, but it's easy enough to find tutorials on how to do this).

  ![Lightsail9](https://i.imgur.com/JSuQXAz.png)
  
### Install Web Server, PHP and Frostybot

* Click the Home button at the top of the page to be taken back to the dashboard. You can now see that the instance has been given the static IP address. Click on the Console button (circled in the screenshot below) to open the console of your instance.

  ![Lightsail10](https://i.imgur.com/jGsqePL.png)

* Once you have opened the console, you can proceed to install Frostybot using the install script. All you need to type in the console is the following 3 commands (you can also copy/paste them into the console):

      wget -4 https://tinyurl.com/frostybot-installer -O /tmp/install.sh
      chmod 700 /tmp/install.sh
      /tmp/install.sh
      
  The output should look similar to the following screenshot:
      
  ![Lightsail11](https://i.imgur.com/yAL5Rkj.png)      
  
* Frostybot is now installed and accessible via http://\<yourstaticip\>/frostybot (replace \<yourstaticip\> with the static IP address that you created earlier. In this example's case the URL is http://18.185.145.110/frostybot/. If you try to browse to the address, you should see output similar to the following:
  
  ![Lightsail12](https://i.imgur.com/yplGT2H.png)        
  
  This error is expected, as Frostybot will only accept commands coming from Tradingview for security reasons, but the fact that you can see the error means that Frostybot is working correctly. 

### Configure Frostybot

* The URL that you used now will be the same URL you enter in the Webhook URL section of your Tradingview alerts. You can now proceed to configure your preferred exchange API keys - Please continue [here](https://github.com/CryptoMF/frostybot/blob/master/INSTALLATION.md#post-installation-configuration) for more information on configuring your bot and setting up Tradingview webhooks.
  
  
  
  
