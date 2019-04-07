<?php 

if (!isset($_REQUEST)) { 
  return; 
} 

// Подключаем основные настройки квеста
include 'config.php';
// Подключаем файл, содержащий вопросы квеста
include 'questions.php';

const VK_API_TOKEN = "YOUR_VK_GROUP_ACCESS_TOKEN_HERE";

/* 
===========================================================================
Основное взаимодействие с игрой осуществляется по id ВКонтакте человека, 
который принял при регистрации на себя роль капитана команды
===========================================================================
*/

// Запрос к БД
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

// Регистрация команды
function teamRegister($name) {
  global $data, $user_id;
  $time=$data->object->date;
  $regQuery="INSERT INTO teams (name,score,cap_vk_id) VALUES ('$name',0,$user_id)";
  // Штамп времени
  $timeQuery="UPDATE teams SET time_start=$time WHERE cap_vk_id=$user_id";
  dbQuery($regQuery);
  dbQuery($timeQuery);
}

// Получить информацию о команде в виде ассоциативного массива по ID VK капитана
// В случае успеха вернёт ассоциативный массив с информацией о команде, в противном случае вернёт -1
function getTeamInfoById($id) {
  $result = dbQuery("SELECT * FROM teams WHERE cap_vk_id=$id LIMIT 1");
  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $info = array(
        "id" => $row["id"],
        "name" => $row["name"],
        "score" => $row["score"],
        "cap_vk_id" => $row["cap_vk_id"],
        "time_start" => $row["time_start"],
        "time_finish" => $row["time_finish"],
        "duration" => $row["duration"],
        "current_task" => $row["current_task"],
        "is_paid" => $row["is_paid"],
        "hints_counter" => $row["hints_counter"],
        "is_hinted" => $row["is_hinted"],
        "members" => $row["members"],
        "attempts" => $row["attempts"],
        "in_game" => $row["in_game"]
      );
    }
    $result->free();
  }
  if (isset($info)) {
    return $info;
  }
  else {
    return -1;
  }
}

// Получить рейтинговую таблицу всех текущих участников в порядке уменьшения их суммы очков в текущий момент
function getScoreTable() {
  $scoreTableQuery="SELECT name,score,current_task,game_played FROM teams ORDER BY score DESC";
  $result = dbQuery($scoreTableQuery);
  $leaders="";
  $place=1;
  if ($result) {
    while ($row = $result->fetch_assoc()) {
      if ($row["game_played"]==false) {
        $leaders=$leaders.$place." | ".$row["name"]." | ".$row["score"]." | ".$row["current_task"]."<br>";
        $place=$place+1;
      }
    }
  $result->free();
  }
  return $leaders;
}

// Функция обновления счёта команды
function updateTeamScore($newScore, $id) {
  $updateScoreQuery = "UPDATE teams SET score = $newScore WHERE cap_vk_id = $id";
  dbQuery($updateScoreQuery);
}

// Функция перебрасывания команды с капитаном $id на задание с номером $newTask
function updateTeamTask($newTask, $id) {
  $updateTaskQuery = "UPDATE teams SET current_task = $newTask WHERE cap_vk_id = $id";
  dbQuery($updateTaskQuery);
}

// Получить счётчик использованных подсказок в течение игры командой
function getHintsCounter($id) {
  return getTeamInfoById($id)["hints_counter"];
}

// Увеличить счётчик подсказок на единицу
function incHintsCounter($id) {
  $newHintsCounter = getHintsCounter($id) + 1;
  $incHintsCounterQuery = "UPDATE teams SET hints_counter = $newHintsCounter WHERE cap_vk_id=$id";
  dbQuery($incHintsCounterQuery);
}

// Получить статус взятия подсказки на текущем задании
function isHinted($id) {
  return getTeamInfoById($id)["is_hinted"];
}

// Установить статус взятия подсказки в 1
function setHinted($id) {
  $setHintedQuery = "UPDATE teams SET is_hinted = 1 WHERE cap_vk_id=$id";
  dbQuery($setHintedQuery);
}

// Установить статус взятия подсказки в 0
function setUnhinted($id) {
  $setUnhintedQuery = "UPDATE teams SET is_hinted = 0 WHERE cap_vk_id=$id";
  dbQuery($setUnhintedQuery);
}

// Получить статус команды "в игре" (0/1).
function isInGame() {
  global $user_id;
  return getTeamInfoById($user_id)["in_game"];
}

// Функция взятия подсказки командой с капитаном $user_id
function getHint($user_id) {
  global $request_params, $request_params_broadcast, $questHints, $maxHintsCounter;
  $currentHintsCounter = getHintsCounter($user_id);
  // Подскаку можно взять только в игре, поэтому проверяем статус команды, то, что она сейчас в игре
  if (isInGame()) {
  // Проверяем, чтобы текущее число подсказок, взятых командой не превышало максимальное число подсказок, которые можно взять за всю игру
  if ($currentHintsCounter <= $maxHintsCounter) {
    // Если подсказка на этом задании не была взята, то
    if (isHinted($user_id) == 0) {
      // Получаем информацию о команде из БД
      $teamInfo = getTeamInfoById($user_id);
      $teamName = $teamInfo["name"];
      $currentTask = $teamInfo["current_task"];
      $currentScore = $teamInfo["score"];
      // Уменьшаем счёт команды за взятие подсказки
      $hintedScore = $currentScore-5;
      // Обновляем его в БД
      updateTeamScore($hintedScore, $user_id);
      // Готовим сообщение для отправки команде, взявшей подсказку (request_params['message']) и всем участникам игры в беседу request_params_broadcast['message'], где транслируются публичные события игры (взятие подсказок, прохождение заданий, завершение игры, ...)
      $request_params["message"] = "Подсказка к заданию № $currentTask:<br>".$questHints[$currentTask-1];
      $request_params_broadcast["message"] = "[Трансляция]\nКоманда $teamName использовала подсказку на станции $currentTask и потратила 5 баллов. Текущий счёт команды: $hintedScore";
      // Устанавливаем статус взятия подсказки в 1
      setHinted($user_id);
      // Увеличиваем счётчик взятых подсказок за всю игру
      incHintsCounter($user_id);
    }
    else {
      $request_params["message"] = "Вы уже брали подсказку к этому заданию!";
    }
  }
  else {
    $request_params["message"] = "Вы использовали максимальное число подсказок за игру!";
  }
}
else {
  $request_params["message"] = "Нельзя взять подсказку вне игры!";
}
}

// Отправка сообщения всем участникам команды
function sendMessageToMembers($message) {
  global $user_id, $request_params_members;
  // Обращаемся к БД один раз и работаем с массивом инфы о команде как со статикой
  $teamInfo = getTeamInfoById($user_id);
  $teamMembers = $teamInfo["members"];
  // request_params_members -- массив параметров для отправки сообщения членам команды
  $request_params_members["user_ids"] = $teamMembers;
  $currentTask = $teamInfo["current_task"];
  $teamName = $teamInfo["name"];
  $request_params_members["message"] = "[Команда $teamName]\n".$message;
}

// Отправка задания всем участникам команды
function sendQuestionToMembers() {
  global $questions, $user_id;
  // Получаем инфу о команде
  $currentTask = getTeamInfoById($user_id)["current_task"];
  // Отправляем членам команды
  sendMessageToMembers($questions[$currentTask-1]);
}

// Полномочия взять подсказку есть только у капитана команды
// После взятия подсказки капитаном, она автоматически отправляется всем участникам команды
function sendHintToMembers() {
  global $questHints, $user_id;
  // Проверяем, чтобы подсказка к этому заданию этой командой не бралась
  if (!isHinted($user_id)) { 
      // Получаем номер текущего задания
      $currentTask = getTeamInfoById($user_id)["current_task"];
      // Отправляем подсказку в заданию всем членам команды
      sendMessageToMembers("Подсказка к заданию $currentTask:<br>".$questHints[$currentTask-1]);
  }
  else {
    sendMessageToMembers("Ваша команда уже брала подсказку к этому заданию!");
  }
}

// Функция, считающая число пробелов в строке
function countSpaces($str) {
  $counter = 0;
  for ($i=0; $i<strlen($str); $i++) {
    if ($str[$i]==" ") {
      $counter++;
    }
  }
}

// Это какой-то костыль, который нужен для правильного получения списка участников команды при её регистрации капитаном команды
function getInsideIds($userInput) {
  $userInput .= " ";
  $i=0;
  $k=0;
  $currentInsideId = "";
  while ($i!=strlen($userInput)) {
    if ($userInput[$i]==" ") {
      $insideIds[$k] = $currentInsideId;
      $currentInsideId = "";
      $i++;
      $k++;
    }
    else {
      $currentInsideId .= $userInput[$i];
      $i++;
    }
  }
  return $insideIds;
}

// Функция добавления в команду участника
// questId -- это номер участника в списке участников группы "квест по эрмитажу" вк
// Очевидно, надо как-то улучшить этот подход к регистрации, потому что когда групп разрастётся до тысяч участников, выводить списком тысячи имён это совершенно ебанутая идея
function addMemberToTeam($questId) {
  global $user_id, $membersArray;
  // Получить массив участников группы
  $membersArray = getMembersArray();
  // Считаем пробелы в строке, чтобы узнать, сколько участников нам надо добавить в команду
  $membersNumber = countSpaces($questId)+1;
  // Получить внутренний идентификатор участника для добавления (порядковый номер вступления в группу квеста)
  $insideIds = getInsideIds($questId);
  $membersNormalized = "";
  
  // В БД участники команды хранятся в строке members, куда записываются их id vk через запятую
  // Этот цикл преобразует массив идентификаторов в строку с запятой в качестве разделителя
  for ($i=0; $i<count($insideIds); $i++) {
    $membersNormalized .= $membersArray[$insideIds[$i]].",";
  }

  $member = $membersNormalized;
  // Добавляем к текущему составу команды новых участников
  $updatedMembers = (getTeamInfoById($user_id)["members"].$member).',';
  $updatedMembers = substr($updatedMembers,0,-1);
  $addMemberQuery = "UPDATE teams SET members = '$updatedMembers' WHERE cap_vk_id=$user_id";
  dbQuery($addMemberQuery);
}

// Получить число попыток ответа на текущий вопрос
function setAttempts($value) {
  global $user_id;
  $setQuery = "UPDATE teams SET attempts = $value WHERE cap_vk_id = $user_id";
  dbQuery($setQuery);
}

// Получить текущее время команды в игре (сколько она уже находится в игре)
function timeInfo() {
  global $data;
  $currentTime = $data->object->date;
  $beginTime = getTeamInfoById($user_id)["time_start"];
  $alreadyPlayedTime = $currentTime - $beginTime;
  if ($param == "inGame") {
    return date("Hч:iм:sс", mktime(0, 0, $alreadyPlayedTime));
  }
}

// Какой-то костыль
$membersArray[0]="";

// Получить участников группы
function getGroupMembers() {
  global $apiVersion, $membersArray;
  $token = VK_API_TOKEN;
  $data = json_decode(file_get_contents("https://api.vk.com/method/groups.getMembers?group_id=xquests&sort=time_asc&access_token=$token&v=$apiVersion"),true);
  $maxMembers = $data["response"]["count"];
  $membersToShow = "";
  for ($i=0; $i<$maxMembers; $i++) {
    $membersToShow .= $data["response"]["items"][$i].",";
    $membersArray[$i] = $data["response"]["items"][$i];
  }
  return getMemberListGlobal($membersToShow);
}

// Получить участников в виде массива
function getMembersArray() {
  global $apiVersion;
  $token = "";
  $data = json_decode(file_get_contents("https://api.vk.com/method/groups.getMembers?group_id=xquests&sort=time_asc&access_token=$token&v=$apiVersion"),true);
  $maxMembers = $data["response"]["count"];
  for ($i=0; $i<$maxMembers; $i++) {
    $membersArray[$i] = $data["response"]["items"][$i];
  }
  return $membersArray;
}

// Поименный список пользоваетелей, которые состоят в группе
// Прикол в том, что в квесте могут участвовать только участники группы
// (вообще-то это не так, но мы сделали это так для увеличения числа участников группы)
function getMemberListGlobal($user_ids) {
  global $user_id, $apiVersion;
  $token = VK_API_TOKEN;
  $memberList = "";
  $user_info = json_decode(file_get_contents("https://api.vk.com/method/users.get?user_ids=$user_ids&access_token=$token&v=$apiVersion"));
  for ($i=0; $i<count($user_info->response); $i++) {
    $user_name = $user_info->response[$i]->first_name;
    $user_last_name = $user_info->response[$i]->last_name;
    $memberList .= $i.". $user_name $user_last_name <br>";
  }
  return $memberList;
}

// Получить текущий состав команды
function getMemberList() {
  global $user_id, $token, $apiVersion;
  $memberList = "";
  $teamInfo = getTeamInfoById($user_id);
  $user_ids = $teamInfo["members"];
  $captain_info = json_decode(file_get_contents("https://api.vk.com/method/users.get?user_ids=$user_id&access_token=$token&v=$apiVersion"));
  $capName = "*".($captain_info->response[0]->first_name)." ".($captain_info->response[0]->last_name)."*";
  $user_info = json_decode(file_get_contents("https://api.vk.com/method/users.get?user_ids=$user_ids&access_token=$token&v=$apiVersion"));
  $teamName = $teamInfo["name"];
  for ($i=0; $i<count($user_info->response); $i++) {
    $user_name = $user_info->response[$i]->first_name;
    $user_last_name = $user_info->response[$i]->last_name;
    $memberList .= "$user_name $user_last_name <br>";
  }
  return "Состав команды $teamName:<br>".$capName."<br>".$memberList;
}

// Пропуск задания
function skipTask() {
  global $user_id;
  $currentTask = getTeamInfoById($user_id)["current_task"];
  $currentTask++;
  updateTeamTask($currentTask, $user_id);
  setSkippedTasks(getSkippedTasks($user_id)+1,$user_id);
}

// Установить статус команды "в игре" в 1
function setInGame($value) {
  global $user_id;
  $setQuery = "UPDATE teams SET in_game = $value WHERE cap_vk_id = $user_id";
  dbQuery($setQuery);
}

// Получить статус запуска игры (устанавливается админом квеста)
function isGameStarted() {
  $query = "SELECT * FROM game_settings";
  $result = dbQuery($query);
  $isGameStarted = $result->fetch_all(MYSQLI_NUM);
  return $isGameStarted[0][0];
}

// Установить статус игры в значение value(0/1)
function setGameStarted($value) {
  $query = "UPDATE game_settings SET is_started = $value";
  dbQuery($query);
}

// Проверить, является ли текущий пользователь бота членом какой-либо команды
// (чтобы исключить возможность повторной регистрации в нескольких командах,
// становления капитаном в другой команде и т.п. Однако подобные функционал
// пока не реализован)
function isMember($id) {
  $searchFlag = false;
  $query = "SELECT members FROM teams";
  $result = dbQuery($query);
  $capsNumber = $result->num_rows;
  $members = $result->fetch_all(MYSQLI_NUM);
  
  for ($i=0; $i<$capsNumber; $i++) {
    if (stripos($members[$i][0], strval($id)) !== false)
    $searchFlag = true;
  }

  return $searchFlag;
}

// Проверить, является ли участник квеста капитаном команды
function isCaptain($id) {
  // Если игрок не состоит ни в какой команде как участник, то проверим, может быть, он капитан команды
  $searchFlag = false;
  if(!isMember($id)) {
    $query = "SELECT cap_vk_id FROM teams";
    $result = dbQuery($query);
    $capsNumber = $result->num_rows;
    $caps = $result->fetch_all(MYSQLI_NUM);
  
    for ($i=0; $i<$capsNumber; $i++) {
      if ($id == $caps[$i][0]) {
        $searchFlag = true;
      }
    }
  }
  return $searchFlag;
}

// Является ли юзер квеста его админом
function isAdmin($id) {
  if ($id == QUEST_ADMIN) {
    return true;
  }
  else {
    return false;
  }
}

// Получить уровень доступа к квесту 3 - админ, 2 - капитан, 1 - участник команды, 0 - незарегистрированный ни в качестве капитана ни в качестве участника команды, ноубади, ноунейм
function getAccessLayer($id) {
  if (isAdmin($id)) {
    return 3;
  }
  else if (isCaptain($id)) {
    return 2;
  }
  else if (isMember($id)) {
    return 1;
  }
  else {
    return 0;
  }
}

// Получить число пропущенный заданий (по правилам нельзя пропускать более 3 заданий за игру)
function getSkippedTasks($user_id) {
  $query = "SELECT skipped_tasks FROM teams WHERE cap_vk_id = $user_id";
  $result = dbQuery($query);
  $skippedTasks = $result->fetch_all(MYSQLI_NUM);
  return $skippedTasks[0][0];
}

// Установка числа пропущенный заданий
function setSkippedTasks($value, $user_id) {
  $query = "UPDATE teams SET skipped_tasks = $value WHERE cap_vk_id = $user_id";
  dbQuery($query);
}

// Функция отправки сообщений всем активным участникам квеста (у которых game_played = 0, то есть они ещё в игре)
function sendMessageToAll($message) {
  global $request_params_all;
  $query = "SELECT cap_vk_id, members FROM teams WHERE game_played = 0";
  $result = dbQuery($query);
  $membersToSend = "";
  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $membersToSend .= $row["cap_vk_id"].",".$row["members"];
    }
    $result->free();
  }
  $request_params_all["message"] = "[Сообщение от Администратора]\n".$message;
  $request_params_all["user_ids"] = $membersToSend;
}

// Установить для всех команд статус "сыграно" в 1
function setGamePlayed() {
  $query = "UPDATE teams SET game_played = 1";
  dbQuery($query);
}

// Слои доступа к игре
/*
0 -- стандартное состояние, по умолчанию доступное всем
доступные команды:
привет
команда ИмяКоманды -- защититься от повторных и пустых регистраций
участники
справка

1 -- игроки команды

2 -- капитаны команд, утвержденные администратором квеста
играть
добавить
подсказка
очки
игроки

3 -- администратор квеста

Запретить командам входить в игру после запуска игры
*/

//Получаем и декодируем уведомление 
$data = json_decode(file_get_contents('php://input')); 

if (isset($data->type)) { 
//Проверяем, что находится в поле "type" 
switch ($data->type) { 
  //Если это уведомление для подтверждения адреса сервера... 
  case 'confirmation': 
    //...отправляем строку для подтверждения адреса 
    echo $confirmation_token; 
    break; 

//Если это уведомление о новом сообщении... 
  case 'message_new': 
    echo "ok";
    //Получаем информацию о пользователе
    $user_id = $data->object->user_id; 
    $user_info = json_decode(file_get_contents("https://api.vk.com/method/users.get?user_ids=$user_id&access_token=$token&v=$apiVersion"));
    $user_name = $user_info->response[0]->first_name;
    $user_last_name = $user_info->response[0]->last_name; 

if (isset(getTeamInfoById($user_id)["cap_vk_id"])) {
  $teamInfo = getTeamInfoById($user_id);
  $currtask = $teamInfo["current_task"];
  $attempts = $teamInfo["attempts"];
}

// ================================ //
// ОСНОВНЫЕ НАСТРОЙКИ ДИНАМИКИ ИГРЫ //
// ================================ //

    $usermsg=$data->object->body;

// Сразу сформируем шаблоны запроса к АПИ для пользователя и для трансляции событий игры в чат
$random_id = microtime(true);

$request_params = array(
  'message' => "", 
  'user_id' => $user_id, 
  'access_token' => $token, 
  'v' => $apiVersion,
  'read_state' => 1,
  'random_id' => $random_id,
  'attachment' => ""
);

$request_params_broadcast = array( 
  'message' => "",
  'chat_id' => $currentChat, 
  'access_token' => $token, 
  'v' => $apiVersion,
  'read_state' => 1,
  'random_id' => $random_id
);

$request_params_members = array(
  'message' => "",
  'user_ids' => "",
  'access_token' => $token, 
  'v' => $apiVersion,
  'read_state' => 1,
  'random_id' => $random_id
);

$request_params_all = array(
  'message' => "",
  'user_ids' => "",
  'access_token' => $token, 
  'v' => $apiVersion,
  'read_state' => 1,
  'random_id' => $random_id
);

// Таким образом, редактируем только параметры message & attachment

// Admin panel
if (getAccessLayer($user_id)==3) {

    if ($usermsg=="status") {
      $request_params["message"] = isGameStarted();
    }

    else if ($usermsg == "start") {
      setGameStarted(1);
      $request_params["message"] = "Started successfully!";
    }

    else if ($usermsg == "stop") {
      setGameStarted(0);
      $request_params["message"] = "Finished successfully!";
    }
    // Send to all active users
    else if (mb_substr($usermsg,0,4)=="всем" || mb_substr($usermsg,0,8)=="Всем") {
      $message = mb_substr($usermsg, 5, strlen($usermsg));
      sendMessageToAll($message);
      $request_params["message"] = "Sent";
    }
}

// Captain panel
if (getAccessLayer($user_id)==2) {

  // НАЧАЛО ИГРЫ
  if ($usermsg=="играть" || $usermsg=="Играть") {
    if (!isInGame() && isGameStarted()) {
      $request_params["message"] = $questions[0];
      $request_params["attachment"] = $questions_attachments[$currtask-1];
      setInGame(1);
      sendQuestionToMembers();
      $request_params_members["attachment"] = $questions_attachments[0];
    }
    else {
      $request_params["message"] = "Вы уже приступили к игре или игра ещё не началась!";
    }
  }

  else if ($usermsg=="справка" || $usermsg=="Справка") {
    $request_params["message"] = $sysmsg["rules"];
  }

  // ПОКАЗАТЬ РЕЙТИНГ
  else if ($usermsg=="рейтинг" || $usermsg=="Рейтинг") {
    if (isGameStarted()) {
      $scoreTable = getScoreTable();
      $request_params["message"] = "Место | Команда | Очки | Станция<br>".$scoreTable;
    }
    else {
      $request_params["message"] = "Игра ещё не началась!";
    }
  }

  // add member
  else if (mb_substr($usermsg,0,8)=="добавить" || mb_substr($usermsg,0,8)=="Добавить") {
    if (!isGameStarted()) {
      $newMember = mb_substr($usermsg, 9, strlen($usermsg));
      addMemberToTeam($newMember);
      $request_params["message"] = "Игроки с номерами: $newMember успешно добавлены в вашу команду! Чтобы добавить ещё игроков, повторите ввод этой команды с новыми игроками. Чтобы проверить текущий состав команды, напишите \"игроки\". А затем напишите \"играть\", чтобы приступить к игре в таком составе.";
    }
    else {
      $request_params["message"] = "Нельзя добавлять игроков после старта игры!";
    }
  }

  // show team players
  else if ($usermsg=="игроки" || $usermsg=="Игроки") {
    //$request_params["message"] = getTeamInfoById($user_id)["members"];
    $request_params["message"] = getMemberList();
  }

  // show current score
  else if ($usermsg=="очки" || $usermsg=="Очки") {
    $currentscore = $teamInfo["score"];
    $request_params["message"] = "Ваш текущий счёт в игре: ".$currentscore;
  }

  else if ($usermsg == "участники" || $usermsg == "Участники") {
    $request_params["message"] = getGroupMembers();
  }

  // Показ заданий на основании текущей станции (вопроса)
  else if (($usermsg==$answers[$currtask-1]) && ($currtask<$taskQuantity) && ($currtask>0) && ($attempts<=$maxAttempts) && isInGame()) {
  // В случае правильного ответа команды обновляем счёт и перекидываем на следующую станцию
  // Обновляем счёт
    $currentscore = $teamInfo["score"];
    $newScore=$currentscore+$scores[$currtask-1];
    updateTeamScore($newScore, $user_id);

  // Обновляем задание
    $currtask = $currtask+1;
    updateTeamTask($currtask, $user_id);
    $teamname = $teamInfo["name"];
    $request_params_broadcast["message"] = "[Трансляция]\nКоманда $teamname получила ".$scores[$currtask-2]." очков за прохождение станции № ".($currtask-1)."!";

  // Генерируем сообщения в личный и общий чаты
    $request_params["message"]="=============== \nВы ответили верно. За это задание вам начислено ".$scores[$currtask-2]." очков. Следующее задание -- $currtask, ждёт вас ниже. Успехов! Используйте команду \"очки\", чтобы узнать текущий счёт команды. \n===============\n\n".$questions[$currtask-1];
    $request_params["attachment"] = $questions_attachments[$currtask-1];

  // Устанавливаем маркер взятия подсказки в 0
    setUnhinted($user_id);
    sendQuestionToMembers();
    $request_params_members["attachment"] = $questions_attachments[$currtask-1];

  // Обнуляем счётчик попыток при верном ответе
    setAttempts(1);
} // end of the else-if construcion to show tasks

  else if ($usermsg == "подсказка" || $usermsg == "Подсказка") {
    if (isInGame() && isGameStarted()) {
      sendHintToMembers();
      getHint($user_id);
    }
    else {
      $request_params["message"] = "Нельзя брать подсказки вне игры!";
    }
  }

// Если это последнее задание в квесте
  else if (($usermsg==$answers[$currtask-1]) && ($currtask==$taskQuantity) && isInGame()) {
    setInGame(0); // ставим флаг "в игре" в положение неактивен
    $currentscore = $teamInfo["score"];

    // Считаем очки за время прохождения
    $questEndTime=$data->object->date;
    $stampFinishTimeQuery="UPDATE teams SET time_finish=$questEndTime WHERE cap_vk_id=$user_id";
    dbQuery($stampFinishTimeQuery);
    $duration = $questEndTime - $teamInfo["time_start"];
    $timescore=round(100*(1-($duration/$maxduration)));
    $finalScore=$currentscore+$scores[$currtask-1]+$timescore;
    $score = $currentscore+$scores[$currtask-1];
    updateTeamScore($finalScore, $user_id);
    updateTeamTask($taskQuantity,$user_id);
    $stampDuration = "UPDATE teams SET duration=$duration WHERE cap_vk_id=$user_id";
    dbQuery($stampDuration);
    $duration_form = date("Hч:iм:sс", mktime(0, 0, $duration));
    $teamName = $teamInfo["name"];

    // Сообщение пользователю (капитану команды)
    $request_params["message"] = "Игра пройдена, огромное спасибо вам за участие в квесте по Эрмитажу! Это был нелёгкий путь, но смогли достойно его пройти! Взгляните, как это сделали другие команды, воспользовавшись командой \"рейтинг\". Оставшееся от прохождения квеста время мы предлагаем вам потратить на свободный осмотр наиболее интересных для вас экспонатов музейного комплекса. \n\n Общий сбор назначаем в 15:30 у Александровской колонны в центре Дворцовой площади. \n\n ИТОГИ ИГРЫ:\n\nОчки за задания: $score \nОчки за время: $timescore.\nОбщий счёт в игре: $finalScore.";

    // Сообщение в беседу квеста
    $request_params_broadcast["message"] = "[Трансляция]\nКоманда $teamName завершила прохождение квеста, набрав ".$finalScore." очков: за задания -- $score, за время -- $timescore!";

  }

  // Если вне игры, то показываем сообщение об ошибке
  // Если в игре, то любое сообщение, не подходящее под команды, считаем за неверный ответ на текущий вопрос
  else if (isInGame()) {
    $currentAttempts = $teamInfo["attempts"];
    if ($currentAttempts < $maxAttempts) {
      $currentAttempts++;
      setAttempts($currentAttempts);
      $currentTask = $teamInfo["current_task"];
      $attemptsAvaliable = $maxAttempts - $currentAttempts + 1;
      $teamName = $teamInfo["name"];
      $request_params["message"] = "Ответ неверный! Будьте осторожны, у вас осталось $attemptsAvaliable попыток!";
      $request_params_broadcast["message"] = "[Трансляция]\nКоманда $teamName неверно дала ответ на станции № $currentTask! На это задание у них осталось $attemptsAvaliable попыток.";
    }
    else if ($currentAttempts >= $maxAttempts) {
      $teamName = $teamInfo["name"];
      $currentTask = $teamInfo["current_task"];
      $currentSkippedTasks = getSkippedTasks($user_id);
      if (($currentTask < $taskQuantity) && ($currentSkippedTasks<=$maxSkipTasks)) {
        $request_params["message"] = "Вы использовали все попытки. Станция считается НЕПРОЙДЕННОЙ и баллы за неё не даются. Ниже представлено следующее задание:\n\n".$questions[$currentTask];
        $request_params_members["message"] = "[Команда $teamName]\n".$questions[$currentTask-1];
        setUnhinted($user_id);
        $request_params_broadcast["message"] = "[Трансляция]\nКоманда $teamName не справилась с заданием № $currentTask и НЕ получила баллы на этой станции.";
        skipTask();
        setAttempts(1);
      }
      else if ($currentTask == $taskQuantity) {
        $request_params["message"] = "Ответ неверный, но вам необходимо пройти последнее задание любой ценой!";
      }
      else {
        $request_params["message"] = "Вы больше не можете пропускать задания после трёх неверных ответов. Вам необходимо ответить верно для перехода на следующую станцию!";
      }
    }
  }
  else {
      $request_params["message"] = "Неизвестная команда или неверная логика взаимодействия с квестом. Воспльзуйтесь командой справка.";
  }  
}

// Member panel
if (getAccessLayer($user_id)==1) {
  // ПОКАЗАТЬ РЕЙТИНГ
  if ($usermsg=="рейтинг" || $usermsg=="Рейтинг") {
    if (isGameStarted()) {
      $scoreTable = getScoreTable();
      $request_params["message"] = "Место | Команда | Очки | Станция<br>".$scoreTable;
    }
    else {
      $request_params["message"] = "Игра ещё не началась!";
    }
  }

  else if ($usermsg=="справка" || $usermsg=="Справка") {
    $request_params["message"] = $sysmsg["rules"];
  }

  else {
    $request_params["message"] = "Неверный уровень доступа, команда или логика взаимодействия. Воспользуйтесь командой справка.";
  }

}

// Nobodys panel
// Приветствие
if (getAccessLayer($user_id)==0) {
  if ($usermsg=='привет' || $usermsg=='Привет') {
    $request_params["message"] = "$user_name! Рады приветствовать тебя на квесте по Эрмитажу, который был разработан одним @khokhlyavin(интересным молодым человеком). Скорее вводи \"справка\", читай правила, регистрируй команду и вступай в игру!";
    $request_params["attachment"] = $system_attachments["hellophoto"];
}

else if (mb_substr($usermsg,0,7)=="команда" || mb_substr($usermsg,0,7)=="Команда") {
    $teamname = mb_substr($usermsg, 8, strlen($usermsg));
    if ($teamname != "") {
      // Сообщение пользователю
	   $request_params["message"] = "Регистрация прошла успешно! Приветствуем $teamname в квесте! Пиши \"участники\" для просмотра номеров. А затем \"добавить 1 2 3\" (номера участников через пробел), чтобы добавить участников № 1, 2, 3. Номера используй из списка выше!";
      // Транслировать событие регистрации в игре
      $request_params_broadcast["message"] = "[Трансляция]\nКоманда $teamname теперь в квесте!";
      // Регистрация команды в базе данных
      teamRegister($teamname);
    }
    else {
      $request_params["message"] = "Имя команды не может быть пустой строкой! Повторите ввод!";
    }
  }

else if ($usermsg == "участники" || $usermsg == "Участники") {
  $request_params["message"] = getGroupMembers();
}

// Правила и справка по квесту, доступная всем
else if ($usermsg=="справка" || $usermsg=="Справка") {
  $request_params["message"] = $sysmsg["rules"];
}

else {
  $request_params["message"] = "Неверный уровень доступа, команда или логика взаимодействия. Воспользуйтесь командой справка.";
}

}


$get_params = http_build_query($request_params); 
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.vk.com/method/messages.send');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $get_params);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

// ТРАНСЛИРУЕМ
if (isset($request_params_broadcast["message"])) {
  $get_params_broadcast = http_build_query($request_params_broadcast); 
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://api.vk.com/method/messages.send');
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $get_params_broadcast);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  $response = curl_exec($ch);
  curl_close($ch);
}

// АВТОПОСТИНГ участиникам команды
if (isset($request_params_members["message"])) {
  $get_params_members = http_build_query($request_params_members); 
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://api.vk.com/method/messages.send');
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $get_params_members);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  $response = curl_exec($ch);
  curl_close($ch);
}

if (isset($request_params_all["message"])) {
  // sendMessageToAll
  $get_params_all = http_build_query($request_params_all); 
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://api.vk.com/method/messages.send');
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $get_params_all);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  $response = curl_exec($ch);
  curl_close($ch);
}

break; 
} 
}