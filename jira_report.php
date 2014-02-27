<?php
//acceess restricted
if(!isset($HTTP_RAW_POST_DATA)) {
  echo "http exit";
  exit();
}

//includes
require_once dirname(__DIR__).'/jira/vendor/autoload.php';
require "Jira/Autoloader.php";

//Jira setup
Jira_Autoloader::register();
$api = new Jira_Api(
  "https://jira.workingpropeople.com",
  new Jira_Api_Authentication_Basic("username", "password")
);

//GitLab setup
$client = new \Gitlab\Client('http://gitlab.workingpropeople.com/api/v3/'); 
$client->authenticate('icqeEkNrP9wqBcQqkMdk', \Gitlab\Client::AUTH_URL_TOKEN);


$git_json = json_decode($HTTP_RAW_POST_DATA, true);
//$git_json = json_decode(file_get_contents('/tmp/gitlab.report'), true);

//data
$commit_id = $git_json['after'];
$user_id = $git_json['user_id'];
$user_name = $git_json['user_name'];
$project_id = $git_json['project_id'];
$commit_info = $git_json['commits'][0];

// get the task id!      commit_info['message'] -- jira task id  regex /[A-Z]+-[0-9]+/
if(preg_match('([A-Z]+-[0-9]+)', $commit_info['message'],$match)) {
  $jira_task_id = $match[0];
}
else {
  echo "Commit Message must have a valid JIRA Task ID! preg_match check failed!\n";
  exit();
}

//get the diff by commit_id
$diff = $client->api('repositories')->diff($project_id, $commit_id);

$msg = array();

//populate the message
foreach($diff as $k => $v) {
  $msg[$k]['filename'] = $diff[$k]['new_path'];
  if($diff[$k]['new_file'] == 1) {
    $msg[$k]['flag'] = 'A';
  }
  if($diff[$k]['deleted_file'] == 1) {
    $msg[$k]['flag'] = 'D';
  }
  if($diff[$k]['renamed_file'] == 1) {
    $msg[$k]['flag'] = 'R';
  }
  if(!isset($msg[$k]['flag'])) {
    $msg[$k]['flag'] = 'M';
  }
}

//sort it
function cmp($a, $b) {
  return strcmp($a["flag"], $b["flag"]);
}
usort($msg, "cmp");

//build the comment
$jira_comment = "Message: {$commit_info['message']}\n";
$jira_comment .= "Commit ID: [{$commit_id}|{$commit_info['url']}]\n Authored by: '$user_name'\n";
$jira_comment .= "Files commited:\n";
foreach($msg as $k) {
  $jira_comment .= "\t\t({$k['flag']}) \t {$k['filename']}\n";
}


//post the comment
$params = array(
  'body' => $jira_comment
);
$api->addComment($jira_task_id, $params);

