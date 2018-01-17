<?php
// TODO. Rewrite connection bs 
$dbh = new PDO('mysql:host=localhost;dbname=test', 'root');

// Check all vars presented
if (
    !(isset($_POST['url']) && isset($_POST['telegram'])) ||
    strlen($_POST['url']) == 0 ||
    strlen($_POST['telegram']) == 0     
    )
{
    die("Check your parameters is valid.");
}
else{
    $url = $_POST['url'];
    // check url is valid
    if (
        filter_var($url, FILTER_VALIDATE_URL) === FALSE ||
        !((parse_url($url, PHP_URL_HOST) == 'rutracker.org')) ||
        !((parse_url($url, PHP_URL_PATH) == '/forum/viewtopic.php')) ||
        !((parse_url($url, PHP_URL_QUERY)))
        ) {
        die('Not a valid URL');
    }    
    parse_str((parse_url($url, PHP_URL_QUERY)), $url);
    if (!isset($url['t']))
    {
        die('Not a valid URL');
    }
    $url = $url['t'];
    $username = $_POST['telegram'];
}

// check if the same user already reqested notify about the same this topic
$stmt = $dbh->query(
    'SELECT c.username, u.link FROM `contact` c 
    LEFT JOIN `url` u ON u.id = c.topic_id 
    WHERE c.username = "'.$username.'" AND u.link = "'.$url.'"'
);
if($stmt->rowCount() > 0)
{
    die("Exist");
}

// trying to find same url in db
$stmt = $dbh->query(
    'SELECT id FROM `url`
    WHERE link = "'.$url.'"'
);
if($stmt->rowCount() > 0)
{
    // make notify for new user using presented url id
    $id = $stmt->fetch();
    $stmt = $dbh->query(
        'INSERT into contact (username, topic_id) 
        VALUES ("'.$username.'","'.$id['id'].'")'
        );
}else{
    // insert new url and user in db
    $json = file_get_contents(
        'http://api.rutracker.org/v1/get_tor_topic_data?by=topic_id&val='.$url
    );
    $obj = json_decode($json);

    $stmt = $dbh->query(
        'INSERT into url (link,u_date) 
        VALUES ("'.$url.'","'.gmdate("Y-m-d H:i:s", $obj->result->{$url}->reg_time).'")'
    );
    $stmt = $dbh->query(
        'SELECT id FROM url WHERE link="'.$url.'"'
    );
    $new_topic_id = $stmt->fetch();
    $stmt = $dbh->query(
        'INSERT into contact (username, topic_id) 
        VALUES ("'.$username.'","'.$new_topic_id['id'].'")'
    );
}

/*
while ($row = $stmt->fetch())
{
    echo $row['link'] . "\n";
}
*/
?>
