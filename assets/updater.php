<?php
// TODO. Rewrite connection bs 
// UTC time
$dbh = new PDO('mysql:host=localhost;dbname=test', 'root');

$stmt = $dbh->query(
    'SELECT * FROM `url`'
);
while ($url = $stmt->fetch())
{
    $json = file_get_contents(
        'http://api.rutracker.org/v1/get_tor_topic_data?by=topic_id&val='.$url['link']
    );
    $obj = json_decode($json);
    printf(
        "Topic id is %s. Add date %s, Update date %s. Title %s.<br>", 
        $url['link'],
        $url['c_date'], 
        gmdate("Y-m-d H:i:s", $obj->result->{$url['link']}->reg_time),
        $obj->result->{$url['link']}->topic_title
    );
}
/*
01.16
Topic id is 5504902. Add date 2018-01-16 23:34:35, Update date 2018-01-13 20:43:04. Title Цитрус / Citrus (Такахаси Такэо) [TV] [1-2 из 12] [Без хардсаба] [JAP+SUB] [2018, Драма, романтика, школа, сёдзе ай, WEBRip] [1080p].
Topic id is 5506142. Add date 2018-01-16 23:48:59, Update date 2018-01-13 22:55:55. Title Повелитель (ТВ-2) / Overlord II (Ито Наоюки) [TV] [1 из 13] [Без хардсаба] [JAP+SUB] [2018,приключения, фэнтези, WEBRip] [720p].
*/
?>