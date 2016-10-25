#!/bin/bash
LAST_INSERT==$(date "+%Y-%m-%d %H:%M:00")
while true 
do
echo "starting readHgData.sh" >> /var/log/syslog
/bin/nc 192.168.95.101 23 -w 3 | while read line
do
DATE=$(date "+%Y-%m-%d %H:%M:00")
echo $line
if [[ $DATE != $LAST_INSERT ]]
then
#echo $line | sed "s/pm /null,\\'$DATE\\',/"| sed -r 's/\s+/,/g' |  sed -re "s/([0-9A-F]{4}),/unhex(\\'\1\\'),/g"   | sed 's/.*/echo "insert ignore into t_hg_data values (&)"/e'| sed 's/,)/,null);/' | mysql -u uvr1611 --password="uvr1611" --database="uvr1611"
#echo $line
echo $line | sed -n -e 's/^pm/pm/p' |  sed "s/pm /null,\'$DATE\',/"| sed -r 's/\s+/,/g' | sed -re "s/,([0-9a-fA-F]+)/,unhex(\'\1\')/179g" | sed 's/.*/echo "insert ignore into t_hg_data_new values (&)"/e'| sed 's/,)/,null);/' 

echo $line | sed -n -e 's/^pm/pm/p' |  sed "s/pm /null,\'$DATE\',/"| sed -r 's/\s+/,/g' | sed -re "s/,([0-9a-fA-F]+)/,unhex(\'\1\')/179g" | sed 's/.*/echo "insert ignore into t_hg_data_new values (&)"/e'| sed 's/,)/,null);/' | mysql -u uvr1611 --password="uvr1611" --database="uvr1611"

#echo $line | sed "s/pm /null,\\'$DATE\\',/"| sed -r 's/\s+/,/g' | sed -re "s/,([0-9a-fA-F])/,unhex(\'\1\')/88g" |         sed 's/.*/echo "insert into t_hg_data values (&)"/e'| sed 's/,)/,null);/' 
LAST_INSERT=$DATE
#echo $LAST_INSERT
fi 
done
done
echo $DATE Connection lost trying to reconnect >> /var/log/syslog
sleep 20
exit 1
