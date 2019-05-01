# php-hermitage-quest-backend
Hermitage Quest VK Bot PHP Backend

# Idea
Our goal is to make museums of Saint-P more interesting and interactive by using VK-Bot platform to realize this

# Configuration
To configure your own quest, copy all project files to your server in one folder, then open *config.php* and set: 
* **confirmation_token** -- using to confirm your admin status on VK group and VK-API token
* **token** to manage group using VK-API
* **QUEST_ADMIN** -- VK id of quest administrator
* **db_server, db_user, db_password, db_name** -- database authorization data

Then add your questions, hints, attachments to questions and answers to **questions.php** as array items, configure your Callback server url in VK group settings and try to start the game.

**Warning**: Description is under construction. Thanks for your understanding.
