#!/bin/sh

PATH=/usr/bin:/bin export PATH
. /home/blabgen/proxy

xset s off
xset -dpms


fluxbox&

xscreensaver -nosplash&

iceweasel `cat /home/blabgen/url`
#iceweasel -safe-mode
