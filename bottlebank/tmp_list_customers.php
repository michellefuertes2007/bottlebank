<?php
$mysqli = new mysqli('localhost','root','','bottlebank');
if ($mysqli->connect_error) { die('Conn error: '.$mysqli->connect_error); }
$res = $mysqli->query('SELECT customer_id, canonical_name FROM customer ORDER BY canonical_name');
while($r=$res->fetch_assoc()){ echo $r['customer_id'].' | '.$r['canonical_name'].'\n'; }
$mysqli->close();
