<?php
$config = parse_ini_file("../settings.ini");

$dbh = new PDO('mysql:host='.$config['mysql_host'].';dbname='.$config['mysql_db'], $config['mysql_user']);

$request = file_get_contents('php://input');
$request = json_decode( $request, TRUE );

function register(){
    global $dbh;
    global $config;
    global $user_id;
    global $username;
    $stmt = $dbh->query(
        'SELECT id FROM `contact`
        WHERE user_id = "'.$user_id.'"'
    );
    if($stmt->rowCount() == 0)
    {
        $stmt = $dbh->query(
            'INSERT into contact (username, user_id) 
            VALUES ("'.$username.'","'.$user_id.'")'
            );
        send('Hello! Send me an URL to rutracker.org topic and i will notify you when topic will be updated.');
    }
    else
    {
        send('Send me an URL to rutracker.org topic and i will notify you when it will be updated. 
/stop to remove notification.
/list to show all notifications.', $die = TRUE);
    }
}

function send($msg, $die = FALSE){
    global $config;
    global $user_id;
    $url = $config['telegram_api'].'bot'.$config['telegram_key'].'/sendMessage';
    $data = array(
        'chat_id' => $user_id, 
        'text' => $msg,
        'parse_mode' => 'Markdown',
        disable_web_page_preview => '1'
    );
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        )
    );
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    if ($result === FALSE) { return FALSE; }
    return TRUE;
}

function notify($url){
    global $user_id;
    global $config;
    global $dbh;
    if (
        (parse_url($url, PHP_URL_HOST) == 'rutracker.org') &&
        (parse_url($url, PHP_URL_PATH) == '/forum/viewtopic.php') &&
        (parse_url($url, PHP_URL_QUERY))
        ) {
        
        parse_str((parse_url($url, PHP_URL_QUERY)), $url);
        if (!isset($url['t']))
        {
            send('URL is invalid.', $die = TRUE);
        }
        
        $url = $url['t'];
        $obj = f_info($url);
        $stmt = $dbh->query(
            'SELECT * FROM notification n 
            LEFT JOIN contact c ON n.user_id = c.user_id 
            WHERE c.user_id = "'.$user_id.'" AND n.topic_id = "'.$url.'"'
        );
        if($stmt->rowCount() > 0)
        {
            send('You already subscribed to
'.$obj->topic_title, $die = TRUE);
        }else{
            send('You will be subscribed to
'.$obj->topic_title);
            $stmt = $dbh->query(
                'SELECT * FROM `url`
                WHERE link = "'.$url.'"'
            );
            if($stmt->rowCount() > 0)
            {
                // make notify for new user using presented url id
                $id = $stmt->fetch();
                $stmt = $dbh->query(
                    'INSERT INTO notification (topic_id, user_id) 
                    VALUES ("'.$id['link'].'","'.$user_id.'")'
                    );
            }else{
                // insert new url and user in db

            
                $stmt = $dbh->query(
                    'INSERT into url (link,u_date) 
                    VALUES ("'.$url.'","'.gmdate("Y-m-d H:i:s", $obj->reg_time).'")'
                );
                $stmt = $dbh->query(
                    'INSERT into notification (user_id, topic_id) 
                    VALUES ("'.$user_id.'","'.$url.'")'
                );
            }

        }
    }else{
        send('URL is invalid. Only rutracker supported right now.', $die = TRUE);
    }
}

function list_url(){
    global $dbh;
    global $config;
    global $user_id;

    $stmt = $dbh->query(
        'SELECT c.username, c.user_id, n.topic_id 
        FROM notification n LEFT JOIN contact c 
        ON n.user_id=c.user_id where c.user_id="'.$user_id.'"'
    );
    $msg = '*Your notifications:*
   ';
    $i = 1;
    while ($row = $stmt->fetch())
    {
        $info = f_info($row['topic_id']);
        $url = 'https://rutracker.org/forum/viewtopic.php?t='.$row['topic_id'];
        $msg = $msg."*".$i.")* ".$info->topic_title."
    [Open topic](".$url.') 
Stop notification - /stop'.$row['topic_id'].' 
`__________________________`
   ';
        $i++;
    }
    send($msg);
}

function stop($topic_id){
    global $dbh;
    global $config;
    global $user_id;
    
    $stmt = $dbh->query(
        'SELECT * FROM notification n 
        LEFT JOIN contact c 
        ON c.user_id = n.user_id 
        WHERE n.topic_id = "'.$topic_id.'" 
        AND c.user_id = "'.$user_id.'"'
    );
    if($stmt->rowCount() > 0){
        $stmt = $dbh->query(
            'DELETE FROM notification WHERE 
            user_id = "'.$user_id.'" 
            AND topic_id = "'.$topic_id.'"'
        );
        send('*Notification stoped for:*
'.f_info($topic_id)->topic_title);
    }else{
        send('It is not yours.');
    }

}

// fetch info about topic
function f_info($topic_id){
    $json = file_get_contents(
        'http://api.rutracker.org/v1/get_tor_topic_data?by=topic_id&val='.$topic_id
    );
    $obj = json_decode($json);
    return($obj->result->{$topic_id});
}

if( !$request )
{
    die();
}
elseif( !isset($request['update_id']) || !isset($request['message']) )
{
    die();
}
else
{
    $chatId  = $request['message']['chat']['id'];
    $message = $request['message']['text'];
    $user_id = $request['message']['from']['id'];
    #$user_id = '124317807';
    #$message = 'https://rutracker.org/forum/viewtopic.php?t=5505520';
    $username = $request['message']['from']['username'];


    if(!(filter_var($message, FILTER_VALIDATE_URL) === FALSE)) {
        #send('Recognized as an URL');
        notify($message);
    }
    elseif($message == '/list'){
        list_url();
    }elseif(substr($message, 0, 5) == '/stop'){
        if (is_numeric(substr($message, 5)))
        {
            stop(substr($message, 5));

        }else{
            send('Give me ID');
        }
    }else{
                register();
    }
}
?>