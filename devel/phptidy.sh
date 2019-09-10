#!/bin/sh
# wrapper for phptidy (https://github.com/cmrcx/phptidy)
#

ME_ABOUT='wrapper for phptidy (https://github.com/cmrcx/phptidy)'
ME_USAGE='[<...OPTIONS>] [[--]<...phptidy passthru args>]'
ME_NAME='phptidy.sh'

#
# paths
#
[ -n "$APP_DIR" ] || { ME_DIR="/$0"; ME_DIR=${ME_DIR%/*}; ME_DIR=${ME_DIR:-.}; ME_DIR=${ME_DIR#/}/; ME_DIR=$(cd "$ME_DIR"; pwd); APP_DIR=$(cd $ME_DIR/../; pwd); }
PHPTIDY=phptidy.php

print_hint() {
   echo "  Hint, try: $ME_NAME --usage"
}

print_copyright() {
	echo "Copyright (c) 2018-$(date +'%Y'), Doug Bird. All Rights Reserved."
}

OPTION_STATUS=0
while getopts :?qhua-: arg; do { case $arg in
   h|u|a) HELP_MODE=1;;
   -) LONG_OPTARG="${OPTARG#*=}"; case $OPTARG in
      help|usage|about) HELP_MODE=1; break;;
      phptidy) PHPTIDY=$LONG_OPTARG;;
      '') break ;; # end option parsing
      *) >&2 echo "$ME_NAME: unrecognized long option --$OPTARG"; OPTION_STATUS=2;;
   esac ;; 
   *) >&2 echo "$ME_NAME: unrecognized option -$OPTARG"; OPTION_STATUS=2;;
esac } done
shift $((OPTIND-1)) # remove parsed options and args from $@ list
[ "$OPTION_STATUS" != "0" ] && { >&2 echo "$ME_NAME: (FATAL) one or more invalid options"; >&2 print_hint; exit $OPTION_STATUS; }

if [ "$HELP_MODE" ]; then
   echo "$ME_NAME"
   echo "$ME_ABOUT"
   echo "$(print_copyright)" | head -1
   echo ""
   echo "Usage:"
   echo "  $ME_NAME $ME_USAGE"
   echo ""
   echo "Options:"
   echo "  --phptidy=<PHPTIDY-PATH>"
   echo "   Provide the phptidy command path."
   echo "   Default: $PHPTIDY"
   exit 0
fi

$PHPTIDY -h > /dev/null 2>&1 || {
   >&2 echo "$ME_NAME: (FATAL) phptidy command '$PHPTIDY' is not available"
   exit 1 
}

if [ "$APP_DIR" != "$(pwd)" ]; then
  cd $APP_DIR || {
     >&2 echo "$APP_DIR: failed to change to app root directory"
     exit 1
  }
fi

$PHPTIDY replace











