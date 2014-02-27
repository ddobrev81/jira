<?php
if(!isset($HTTP_RAW_POST_DATA)) {
  echo "http exit";
  exit();
}

require_once dirname(__DIR__).'/jira/vendor/autoload.php';
require "Jira/Autoloader.php";

Jira_Autoloader::register();
$api = new Jira_Api(
  "https://jira.workingpropeople.com",
  new Jira_Api_Authentication_Basic("ddobrev", "Xaxaxa123")
);
$client = new \Gitlab\Client('http://gitlab.workingpropeople.com/api/v3/'); // change here
$client->authenticate('icqeEkNrP9wqBcQqkMdk', \Gitlab\Client::AUTH_URL_TOKEN); // change here

/*
$walker = new Jira_Issues_Walker($api);
$walker->push("project = Danhostel AND assignee=ddobrev AND status=Completed");
foreach ($walker as $issue) {
    var_dump($issue);
    // send custom notification here.
}

$issue = 'PLG-646';
$params = array(
    'transition' => array(
      'id' => 171
    ),
);
$api->transition($issue, $params);


$result = $api->getTransitions($issue);
print_r($result);

$project = $client->api('projects')->create('test project', array(
  'description' => 'This is a project',
  'issues_enabled' => false
));//mydomain.com/hook/push/1');


$list = $client->api('projects')->accessible(1, 5);
print_r($list);

$project_id = 33; //my test proj

$result = $client->api('repositories')->commits($project_id);
print_r($result);

$commit_id = '0d7dc38df092c98c914516be2f9589cd1031e05f';

$result3 = $client->api('repositories')->diff($project_id, $commit_id);
print_r($result3);

$result1 = $client->api('repositories')->commit($project_id, $commit_id);
print_r($result1);
$path2file = '/test-project/';
$result2 = $client->api('repositories')->getFile($project_id, $path2file, $commit_id);
print_r($result2);

 projects/'.urlencode($project_id).'/repository/files'
ddobrev/test-project/tree/master
*/

$git_json = json_decode($HTTP_RAW_POST_DATA, true);

//data
$commit_id = $git_json['after'];
$user_id = $git_json['user_id'];
$user_name = $git_json['user_name'];
$project_id = $git_json['project_id'];
$repo_name = $git_json['repository']['name'];
$commit_info = $git_json['commits'][0];

// commit_info['message'] -- jira task id  regex /[A-Z]+-[0-9]+/

if(preg_match('([A-Z]+-[0-9]+)', $commit_info['message'],$match)) {
  $jira_task_id = $match[0];
} 
else
{
  echo "Commit Message must have a valid JIRA Task ID! preg_match check failed!";
  exit();
}

//get the diff by commit_id
$diff = $client->api('repositories')->diff($project_id, $commit_id);

$msg = array();

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

//sorting
function cmp($a, $b) {
  return strcmp($a["flag"], $b["flag"]);
}
usort($msg, "cmp");

$jira_comment = "Message: {$commit_info['message']}\n";
$jira_comment .= "Commit ID='[{$commit_id}|{$commit_info['url']}]'\n Authored by '$user_name'\n";
$jira_comment .= "Files commited:\n";
foreach($msg as $k) {
  $jira_comment .= "({$k['flag']}) \t {$k['filename']}\n";
}

$params = array(
  'body' => $jira_comment
);
//$api->addComment($jira_task_id, $params);
print_r($jira_comment);
