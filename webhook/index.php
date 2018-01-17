<?php
$config = parse_ini_file("../settings.ini");
$dbh = new PDO('mysql:host=localhost;dbname=test', 'root');

$request = file_get_contents( 'php://input' );
$request = json_decode( $request, TRUE );

function send($id, $msg, $config) {
    $url = $config['telegram_api'].'bot'.$config['telegram_key'].'/sendMessage';
    $data = array(
        'chat_id' => $id, 
        'text' => $msg
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
    $username = $request['message']['from']['username'];
    
    if($message == '/start')
    {
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
            send(
                $user_id, 
                'Hello! Send me an URL and i will notify you when topic will be updated.', 
                $config
            );
        }
        else
        {
            send(
                $user_id, 
                'Hello again. Send me an URL and i will notify you when topic will be updated.', 
                $config
            );
            die();
        }
    // if message is an URL
    }elseif(!(filter_var($message, FILTER_VALIDATE_URL) === FALSE)) {
        send(
            $user_id, 
            'Recognized as an URL', 
            $config
        );
    }
}
?>