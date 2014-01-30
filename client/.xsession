#!/bin/sh

PATH=/usr/bin:/bin export PATH
. /home/blabgen/proxy

xset s off
xset -dpms


fluxbox&

xscreensaver&

iceweasel `cat /home/blabgen/url`
