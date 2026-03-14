<?php
$mysqli = new mysqli('localhost','root','','bottlebank');
if ($mysqli->connect_error) { echo "CONNECT ERROR\n"; exit(1); }
function cols($conn,$t){
  $res=$conn->query("SHOW COLUMNS FROM `$t`");
  if(!$res){echo "ERROR $t: ".$conn->error."\n"; return;}
  echo "\n$t columns:\n";
  while($r=$res->fetch_assoc()) echo $r['Field']."\n";
}
cols($mysqli,'deposit');
cols($mysqli,'returns');
$mysqli->close();
