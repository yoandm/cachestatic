# cachestatic

This is a simple cache plugin for WordPress to get "static content".
It's only for website who doesn't need dynamic content. 

It's an alpha version I've quickly tested. 

I think this kind of approch is interesting and it was a proof of concept. 

When a page is cached, Apache will serve it directly without use WordPress engine. It's cool for ecoconception and performance. 
It'll work only if you are using internal WordPress rewriting with directory structure (/page/ for exemple)
For the moment, you need Apache (htaccess usage).

Before testing, make a backup of you htaccess file. 
It's not really for production for now. I insist on it. 
Don't be surprised if you find problems. 
Feel free to test, adapt and contribute. 

Yoan De Macedo
mail@yoandm.com