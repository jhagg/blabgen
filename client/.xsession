#!/bin/sh

xrandr --output VGA1 --mode 1024x768

PATH=/usr/bin:/bin export PATH
. /home/blabgen/proxy

xset s off
xset -dpms


fluxbox&

xscreensaver -nosplash&

iceweasel `cat /home/blabgen/url`
