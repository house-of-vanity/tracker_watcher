
# TODO - config file. 
import pymysql.cursors
import urllib.request, json 
import datetime as dt

interval = '1 HOUR'
# Connect to the database
connection = pymysql.connect(host='localhost',
                             user='root',
                             db='test',
                             cursorclass=pymysql.cursors.DictCursor)

# If u_date which already stored older than fresh u_date
# so going to notify
# Any way update last_check
def check_updates(id, u_date):
    with urllib.request.urlopen(
        "http://api.rutracker.org/v1/get_tor_topic_data?by=topic_id&val="+id) as url:
        data = json.loads(url.read().decode())
        last_check = dt.datetime.now()
        new_u_date = dt.datetime.fromtimestamp(int(data['result'][id]['reg_time']))
        if new_u_date > u_date:
            print("There is an update. Going to notify.")

        with connection.cursor() as cursor:
            # Create a new record
            sql = "UPDATE url SET last_check = %s, u_date = %s WHERE link =  %s"
            cursor.execute(sql, (
                last_check.strftime('%Y-%m-%d %H:%M:%S'),
                new_u_date.strftime('%Y-%m-%d %H:%M:%S'),
                 id))
        connection.commit()        

try:
    with connection.cursor() as cursor:
        # Read a single record
        sql = "SELECT * FROM url WHERE last_check < DATE_SUB(NOW(), INTERVAL %s)" % interval
        cursor.execute(sql)
        result = cursor.fetchall()
        for line in result:
            print('Going to check %s. Last check was at %s' % (line['link'], line['last_check']))
            check_updates(line['link'], line['u_date'])
finally:
    connection.close()