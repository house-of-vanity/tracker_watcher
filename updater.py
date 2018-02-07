#!/usr/bin/python3
import pymysql.cursors
import urllib.request, json 
import datetime as dt
from configparser import ConfigParser
import pytz
from urllib.parse import urlencode


parser = ConfigParser()
parser.read('/home/ab/repos/tracker_watcher/settings.ini')
mysql_user = parser.get('mysql', 'mysql_user')
mysql_host = parser.get('mysql', 'mysql_host')
mysql_db = parser.get('mysql', 'mysql_db')
mysql_pass = parser.get('mysql', 'mysql_pass')

#interval = '1 HOUR'
interval = '20 MINUTE'
# Connect to the database
connection = pymysql.connect(host=mysql_host,
                             user=mysql_user,
                             db=mysql_db,
                             use_unicode=True,
                             charset="utf8",
                             passwd=mysql_pass,
                             cursorclass=pymysql.cursors.DictCursor)

# If u_date which already stored older than fresh u_date
# so going to notify
# Any way update last_check
def check_updates(id, u_date, cursor):
    link = "http://api.rutracker.org/v1/get_tor_topic_data?by=topic_id&val="+id
    with urllib.request.urlopen(link) as url:
        data = json.loads(url.read().decode())
        last_check = dt.datetime.now(tz=pytz.utc)
        new_u_date = dt.datetime.fromtimestamp(int(data['result'][id]['reg_time']), tz=pytz.utc)
        u_date = pytz.utc.localize(u_date)
        if new_u_date > u_date:
            log(logger='Updater',
                line='Update found',
                link=id)
            sql = "SELECT c.username, c.user_id " \
            "FROM notification n LEFT JOIN contact c " \
            "ON n.user_id = c.user_id WHERE n.topic_id = '%s'" % id
            cursor.execute(sql)
            result = cursor.fetchall()
            for contact in result:
                log(logger='Updater',
                    line='Notifying user',
                    user_id=contact['user_id'],
                    link=id)
                msg = "%s has been updated.\n[Open on RuTracker.org](%s)\n`magnet:?xt=urn:btih:%s`" % (
                    data['result'][id]['topic_title'], 
                    'https://rutracker.org/forum/viewtopic.php?t='+id,
                    data['result'][id]['info_hash'])
                send(contact['user_id'], msg)
        else:
            log(logger='Updater',
                line='There is not update',
                link=id)


        with connection.cursor() as cursor:
            # Create a new record
            sql = "UPDATE url SET last_check = %s, u_date = %s WHERE link =  %s"
            cursor.execute(sql, (
                last_check.strftime('%Y-%m-%d %H:%M:%S'),
                new_u_date.strftime('%Y-%m-%d %H:%M:%S'),
                 id))
        connection.commit()        

def log(line, logger='Updater', user_id=0, link=0):
    global connection
    with connection.cursor() as cursor:
        sql = "INSERT INTO log (logger, line, link, user_id) VALUES ('%s', '%s', '%s', '%s')" % (logger, line, link, user_id)
        cursor.execute(sql)


def send(id, msg):
    log(logger='Updater Send',
        line='Senging message for %s. Body: %s' % (id, msg),
        user_id=id)
    url =  parser.get('bot', 'telegram_api') + 'bot'+ parser.get('bot', 'telegram_key') + '/sendMessage'
    post_fields = {
        'text': msg,
        'chat_id': id,
        'parse_mode': 'Markdown',
        'disable_web_page_preview': 1
        }
    request = urllib.request.Request(url, urlencode(post_fields).encode())
    json = urllib.request.urlopen(request).read().decode()
    
log(logger='Updater',
      line='Updater started bt CRON.')
try:
    with connection.cursor() as cursor:
        # Read a single record
        sql = "SELECT * FROM url WHERE last_check < DATE_SUB(NOW(), INTERVAL %s)" % interval
        #sql = "SELECT * FROM url"
        cursor.execute(sql)
        result = cursor.fetchall()
        for line in result:
            print('Going to check %s. Last check was at %s' % (line['link'], line['last_check']))
            check_updates(line['link'], line['u_date'], cursor)
finally:
    connection.close()

