
# TODO - config file. 
import pymysql.cursors
import urllib.request, json 
import datetime as dt
from configparser import ConfigParser
import pytz
from urllib.parse import urlencode


parser = ConfigParser()
parser.read('settings.ini')
mysql_user = parser.get('mysql', 'mysql_user')
mysql_host = parser.get('mysql', 'mysql_host')
mysql_db = parser.get('mysql', 'mysql_db')
mysql_pass = parser.get('mysql', 'mysql_pass')

interval = '1 HOUR'
interval = '1 MINUTE'
# Connect to the database
connection = pymysql.connect(host=mysql_host,
                             user=mysql_user,
                             db=mysql_db,
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
            sql = "SELECT c.username, c.user_id " \
            "FROM notification n LEFT JOIN contact c " \
            "ON n.user_id = c.user_id WHERE n.topic_id = '%s'" % id
            cursor.execute(sql)
            result = cursor.fetchall()
            for contact in result:
                print(contact)
                msg = "%s has been updated. %s" % (
                    data['result'][id]['topic_title'], 
                    'https://rutracker.org/forum/viewtopic.php?t='+id)
                send(contact['user_id'], msg)


        with connection.cursor() as cursor:
            # Create a new record
            sql = "UPDATE url SET last_check = %s, u_date = %s WHERE link =  %s"
            cursor.execute(sql, (
                last_check.strftime('%Y-%m-%d %H:%M:%S'),
                new_u_date.strftime('%Y-%m-%d %H:%M:%S'),
                 id))
        connection.commit()        

def send(id, msg):
    url =  parser.get('bot', 'telegram_api') + 'bot'+ parser.get('bot', 'telegram_key') + '/sendMessage'
    post_fields = {
        'text': msg,
        'chat_id': id
        }
    request = urllib.request.Request(url, urlencode(post_fields).encode())
    json = urllib.request.urlopen(request).read().decode()
    
try:
    with connection.cursor() as cursor:
        # Read a single record
        sql = "SELECT * FROM url WHERE last_check < DATE_SUB(NOW(), INTERVAL %s)" % interval
        sql = "SELECT * FROM url"
        cursor.execute(sql)
        result = cursor.fetchall()
        for line in result:
            print('Going to check %s. Last check was at %s' % (line['link'], line['last_check']))
            check_updates(line['link'], line['u_date'], cursor)
finally:
    connection.close()
