<html>
<head>
  <style>
    * {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  color: #333;
}

table {
  text-align: left;
  line-height: 40px;
  border-collapse: separate;
  border-spacing: 0;
  border: 2px solid #ed1c40;
  width: 500px;
  margin: 50px auto;
  border-radius: .25rem;
}

thead tr:first-child {
  background: #ed1c40;
  color: #fff;
  border: none;
}

th:first-child,
td:first-child {
  padding: 0 15px 0 20px;
}

th {
  font-weight: 500;
}

thead tr:last-child th {
  border-bottom: 3px solid #ddd;
}

tbody tr:hover {
  background-color: #f2f2f2;
  cursor: default;
}

tbody tr:last-child td {
  border: none;
}

tbody td {
  border-bottom: 1px solid #ddd;
}

td:last-child {
  text-align: right;
  padding-right: 10px;
}

.button {
  color: #aaa;
  cursor: pointer;
  vertical-align: middle;
  margin-top: -4px;
}

.edit:hover {
  color: #0a79df;
}

.delete:hover {
  color: #dc2a2a;
}
  </style>
</head>


<body>

<?php 

include 'config.php';


function dbQuery($query) {
  global $db_server, $db_user, $db_password, $db_name;
  $mysqli = new mysqli($db_server, $db_user, $db_password, $db_name);
  $result = $mysqli->query($query);
  if (!$result) {
    return $mysqli->error;
  }
  else {
    return $result;
  }
  $mysqli->close();
}

// Выводим на экран окончательный рейтинг квеста за такую-то дату или по такому-то идентификатору
function getScoreTable() {
  $scoreTableQuery="SELECT name,score,current_task,game_played FROM teams ORDER BY score DESC";
  $result = dbQuery($scoreTableQuery);
  $leaders="<table><thead>
    <tr>
      <th colspan=\"3\"><center>Итоги квеста по Эрмитажу</center></th>
    </tr>
    <tr>
      <th colspan=\"3\"><center>Дата проведения: 07.10.2018</center></th>
    </tr>
  </thead>
  <tr><td><b>Место</b></td><td><b>Команда</b></td><td><b>Очки</b></td></tr>";
  $place=1;
  if ($result) {
    while ($row = $result->fetch_assoc()) {
      if ($row["game_played"]==false) {
        $leaders .= "<tr><td>".$place."</td>"."<td>".$row["name"]."</td>"."<td>".$row["score"]."</td></tr>";
        $place=$place+1;
      }
    }
  $result->free();
  $leaders .= "</table>";
  }
  return $leaders;
}

echo getScoreTable();

?> 

</body>
</html>