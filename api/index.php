<?php
//error_reporting(E_ALL);
//ini_set("display_errors", 1);
require 'router/autoload.php';
require 'api/api.php';
$api = new Gnuboard_api();
date_default_timezone_set("Asia/Seoul");
$router = new \Bramus\Router\Router();
$router->get('/', function() use ($api) {
  //echo "??";
  //$api->sql_query();
});
$router->match('GET|POST', '/Auth/{mb_id}', function($mb_id) use ($api) {
  if(!$_POST['mb_password']) {
    $api->msg('패스워드를 입력해주세요');
    exit;
  }
  $mb_password = $_POST['mb_password'];
  $api->Login($mb_id, $mb_password);
});
$router->match('GET', '/configs', function() use ($api){
  echo $api->get_config();
});
$router->mount('/contents', function() use ($router, $api) {
  $router->get('/', function() use ($api) {
    echo $api->get_content($co_id);
  });
  $router->get('/(\w+)', function($co_id) use ($api) {
    echo $api->get_content($co_id);
  });
});
$router->mount('/faqs', function() use ($router, $api) {
  $router->get('/', function() use ($api) {
    echo $api->get_faq($fa_id);
  });
  $router->get('/(\w+)', function($fa_id) use ($api) {
    echo $api->get_faq($fa_id);
  });
});
$router->match('GET', '/faqsgroup/{fm_id}', function() use ($api){
  echo $api->get_faq_group($fa_id);
});
$router->match('GET', '/groups', function() use ($api){
  echo $api->get_group($fa_id);
});
$router->match('GET', '/members', function() use ($api){
  echo $api->get_member(null);
});
$router->mount('/member', function() use ($router, $api) {
  $router->get('/{mb_id}/scraps', function($mb_id) use ($api) {
    echo $api->get_scrap($mb_id);
  });
  $router->get('/{mb_id}/points', function($mb_id) use ($api) {
    echo $api->get_point($mb_id);
  });
  $router->get('/{mb_id}', function($mb_id) use ($api) {
    echo $api->get_member($mb_id);
  });
});
$router->match('GET', '/boards', function() use ($api){
  echo $api->get_board();
});
$router->mount('/board', function() use ($router, $api) {
  $router->match('GET', '/new_articles', function() use ($api){
    echo $api->get_new_articles();
  });
  $router->match('GET', '/new_comments', function() use ($api){
    echo '지원예정';
  });
  $router->get('/{bo_table}/{wr_id}/good', function($bo_table, $wr_id) use ($api) {
    $api->board_permission($bo_table, $wr_id);
    echo $api->get_board_good($bo_table, $wr_id);
  });
  $router->get('/{bo_table}/{wr_id}/files', function($bo_table, $wr_id) use ($api) {
    $api->board_permission($bo_table, $wr_id);
    echo $api->get_board_file($bo_table, $wr_id);
  });
  $router->get('/{bo_table}/{wr_id}/comments', function($bo_table, $wr_id) use ($api) {
    $api->board_permission($bo_table, $wr_id);
    echo $api->get_view_cmt_list($bo_table, $wr_id);
  });
  $router->get('/{bo_table}/{wr_id}/comment/{comment_id}/good', function($bo_table, $wr_id, $comment_id) use ($api) {
    $api->board_permission($bo_table, $wr_id);
    echo $api->get_board_good_cmt($bo_table, $wr_id, $comment_id);
  });
  $router->get('/{bo_table}/{wr_id}/comment/{comment_id}/files', function($bo_table, $wr_id, $comment_id) use ($api) {
    $api->board_permission($bo_table, $wr_id);
    echo $api->get_board_file_cmt($mb_id);
  });
  $router->get('/{bo_table}/{wr_id}/comment/{comment_id}', function($bo_table, $wr_id, $comment_id) use ($api) {
    $api->board_permission($bo_table, $wr_id);
    echo $api->get_view_cmt($mb_id);
  });
  $router->get('/{bo_table}/{wr_id}', function($bo_table, $wr_id) use ($api) {
    $api->board_permission($bo_table, $wr_id);
    echo $api->get_view($bo_table, $wr_id);
  });
  $router->get('/{bo_table}', function($bo_table) use ($api) {
    $api->board_permission($bo_table);
    echo $api->get_list($bo_table);
  });
});
$router->mount('/write', function() use ($router, $api) {
  $router->post('/{bo_table}/{wr_id}', function($bo_table, $wr_id) use ($api) {
    $api->board_permission($bo_table);
    //echo $api->g($bo_table);
  });
  $router->post('/{bo_table}', function($bo_table) use ($api) {
    $api->board_permission($bo_table);
    echo $api->write_update($bo_table);
  });
});
$router->match('GET', '/menus', function() use ($api){
  echo $api->get_menu();
});
$router->match('GET', '/autosave', function() use ($api){
  echo $api->get_autosave();
});
$router->run();