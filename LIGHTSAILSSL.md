![logo](https://i.imgur.com/YAME6yT.png "#FrostyBot")

## Configure SSL security for your Frostybot on Amazon Lightsail 

This document is a quick and dirty walkthrough to show you how to enable SSL security for your Frostybot instance on Amazon Lightsail 

### Get a domain

For this step, I'll show you how to get a domain name on Zonedit, you can use whatever domain host you like

* Browse to [www.zonedit.com](https:/www.zonedit.com) and Sign Up

* Once you have signed up and logged in, you can use the Domains menu to add a new domain

  ![SSL1](https://i.imgur.com/0kNt1xz.png)
  
* At the top of the form, under "register a new domain", enter the domain name you'd like and click Next

  ![SSL2](https://i.imgur.com/dwRqD7u.png)
  
* If the domain has already been taken by somebody else, you'll get an error saying "Sorry, but the domain google.com does not appear to be available.". You will be given an opportunity to try a different name.

* If the domain is available, You be taken to the following page. Check the box to agree to the Terms and Conditions, and click Next

  ![SSL3](https://i.imgur.com/FOluwa8.png) 
  
* The following page will show a summary, and offer a multi year discount if you want it. Select what you want and click Next

  ![SSL4](https://i.imgur.com/Xymzbp2.png)
  
* Complete the Domain Owner information with your info and click Next

  ![SSL5](https://i.imgur.com/LfMFbsY.png)

* The domain will be added to your cart. Click Checkout and complete the checkout process using your credit card.

  ![SSL6](https://i.imgur.com/yzsEXkQ.png)
  
* Once you have paid for the domain, it will show up under your Zonedit Member Home console. Click the DNS link to manage the DNS of the domain

  ![SSL7](https://i.imgur.com/rT6R3By.png)

* Scroll down to A Records, and click the wrench icon to configure your A records for the domain

  ![SSL8](https://i.imgur.com/uVPlLLi.png)
  
* Enter "frostybot" under host and the static IP address of your Lightsail instance under IP address and click Next

  ![SSL9](https://i.imgur.com/DAEq1ug.png)
  
* On the final screem, click Done

  ![SSL10](https://i.imgur.com/SgRcSfv.png)
  
* You now have a DNS record for your Frostybot (frostybot.yourdomain.com)

### Configure Apache

* Connect to the console of your Lightsail instance

* Create a new Apache configuration file using this command (replace the domain with your actual domain, and remember the .conf at the end)

```sudo nano /etc/apache2/sites-available/frostybot.mydomain.com.conf```

* A text editor will open, paste the following into the editor, and replace the domain and email address with your domain name and email address

  ```
	<VirtualHost *:80>
        ServerName frostybot.mydomain.com
        ServerAdmin webmaster@mydomain.com
        DocumentRoot /var/www/html/frostybot
        ErrorLog ${APACHE_LOG_DIR}/error.log
        CustomLog ${APACHE_LOG_DIR}/access.log combined
	</VirtualHost>
  ```
  
* To save the file and exit the editor, press CTRL+x, then Y, and then Enter

* the following command should display the contents of the file, just to check that it was saved correctly

  ```cat /etc/apache2/sites-available/frostybot.mydomain.com.conf```
  
  Here is the sample output:
  
  ![SSL11](https://i.imgur.com/4UqHLQo.png)
 
* Next you need to enable your new configuration, use these commands to do so (replace the name in the first command with your actual filename you created above)

  ```
  sudo a2ensite frostybot.mydomain.com.conf
  sudo systemctl reload apache2
  ```
  
  Here is the sample output
  
  ![SSL12](https://i.imgur.com/LXG1y38.png)
  
* Your bot should now respond when entering the domain name in your browser (http://frostybot.mydomain.com). Note, you might get an error saying "Request received from invalid address", this is perfectly normal so dont worry about it. We only need to know that your bot is responding to the URL.

### Enabling HTTPS in Lighsail

* On Amazon Lightsail, click on your Frostybot instance, and then click on the Networking tab

  ![SSL13](https://i.imgur.com/rFOihTN.png)
  
* Scroll down to Firewall, click Add Rule, Select HTTPS from the dropdown box, and then click Create Rule. Now the firewall is configured to allow HTTPS traffic.

  ![SSL14](https://i.imgur.com/15tfI73.png)
  
### Installing Certbot and Generating a New SSL Certificate

* Connect to the console of your Lightsail instance

* Add the repository for Certbot using this command (if prompted, press Enter to accept the certificate of the repository)

  ```sudo add-apt-repository ppa:certbot/certbot && sudo apt update```
  
  Here is a sample output:
  
  ![SSL15](https://i.imgur.com/7VN5upk.png)
  
* Install certbot using this command (Enter Y if prompted to confirm the install):

  ```sudo apt install python-certbot-apache```

* Now run certbot to create the SSL certificate for your site and install it:

  ```sudo certbot --apache```
  
  At this stage you may prompted to enter your email address. This email address will be used to inform you if there were any issues renewing your certificate in future, so ensure that you enter a valid address
  
* Next, you'll be presented with a list of sites configured on your Apache server, enter the number corresponding to the site for your domain name (in the case below, you'd enter 1.

  ![SSL16](https://i.imgur.com/edbvtGg.png)
  
* Once the certificate has been created and installed, you'll be asked if you would like your site to automatically redirect HTTP requests to HTTPS, enter 2 to enable this option

  ![SSL17](https://i.imgur.com/Rf1sfqC.png)
  
* Congratulations, at this point your bot should respond to https://frostybot.yourdomain.com

### Disable the default site

* Lastly, you must disable the original site that was serving Frostybot over HTTP. Do this using these commands:

  ```
  sudo a2dissite 000-default.conf
  sudo systemctl reload apache2
  ```
  
* All done! Your webhook address for Tradingview alerts should now be https://frostybot.yourdomain.com
