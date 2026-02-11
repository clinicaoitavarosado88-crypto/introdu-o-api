<?php
include 'includes/connection.php';

$result = [];
$q = ibase_query($conn, "SELECT ID, NOME FROM CONVENIOS ORDER BY NOME");
while ($r = ibase_fetch_assoc($q)) {
  $result[] = [
    'id' => $r['ID'],
    'nome' => mb_convert_encoding($r['NOME'], 'UTF-8', 'Windows-1252')
  ];
}
echo json_encode($result);
