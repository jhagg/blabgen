#!/bin/sh

# force empty cache
rm -rf $HOME/.cache/mozilla/firefox/*

# setup http_proxy & https_proxy if needed.
# Mostly used to do first time setup of Firefox
if [ -e $HOME/proxy.local ]; then
	. $HOME/proxy.local
elif [ -e $HOME/proxy ]; then
	. $HOME/proxy
fi

urlfile=$HOME/url
if [ -e $HOME/url.local ]; then
	urlfile=$HOME/url.local
fi
xrandr --output VGA1 --mode 1024x768

PATH=/usr/bin:/bin export PATH

xset s off
xset -dpms


fluxbox&

xscreensaver -nosplash&

iceweasel `cat $urlfile`
#iceweasel -safe-mode
