#!/bin/sh
#
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
SHLVL=1
PID_FILE=/data/sites/www/tmp/cron_each_minute.pid
SCRIPT_NAME=cron_each_minute.sh
#
# check for PID file
if [ -f $PID_FILE ]; then
  # PID file is there, is it readable?
  if [ -r $PID_FILE ]; then
    # it's readable, is there a process?
    PID=$(awk '{print $1}' $PID_FILE)
    PID_NAME=$(ps -p$PID -o ucmd h)
    if [ "$PID_NAME" = "$SCRIPT_NAME" ]; then
      # test if freezed
      current_hour=`date +%H`
      current_min=`date +%M`
      file_hour=`ls -l $PID_FILE | awk '{print substr($8,1,2)}'`
      file_min=`ls -l $PID_FILE | awk '{print substr($8,4,5)}'`
      if [ "$current_hour" -ne "$file_hour" ]; then
              $current_hour = `expr $current_hour + 24`
      fi
      current_time=`expr $current_hour '*' 60`
      current_time=`expr $current_time +  $current_min`
      file_time=`expr $file_hour '*' 60`
      file_time=`expr $file_time + $file_min`
      diff_time=`expr $current_time - $file_time`
      echo " $diff_time minutes"
      if [ "$diff_time" -gt 3 ]; then
              echo "     kill process"
              kill -9 $PID
              exit 1
      fi
      echo "Looks like it's already running - EXIT"
      exit 1
    else
      echo "Looks like a stale PID file"
      rm -f $PID_FILE
    fi
  else
    echo "$PID_FILE file exists, but is not accessible"
    exit 1
  fi
fi
#       store PID
echo $$ > $PID_FILE
#
# sleep 14 seconds to get data received from stations
sleep 14
sudo -u www-data /usr/bin/php /data/sites/www/application/process-average.php >>/data/sites/www/log/process-average.log &
sudo -u www-data /usr/bin/php /data/sites/api/application/generate-arrays.php >>/data/sites/api/log/generate-arrays.log &
exit 0
