<?php
//error_reporting(E_ALL);
//ini_set("display_errors", 1);
header('Content-Type: application/json');  // <-- header declaration
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
  echo json_encode($api->get_config(), JSON_UNESCAPED_UNICODE);
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
  echo json_encode($api->get_group($fa_id), JSON_UNESCAPED_UNICODE);
});
$router->match('GET', '/members', function() use ($api){
  echo $api->get_members();
});
$router->mount('/member', function() use ($router, $api) {
  $router->get('/{mb_id}/scraps', function($mb_id) use ($api) {
    echo $api->get_scrap($mb_id);
  });
  $router->get('/{mb_id}/points', function($mb_id) use ($api) {
    echo $api->get_point($mb_id);
  });
  $router->get('/{mb_id}', function($mb_id) use ($api) {
    echo json_encode($api->get_member($mb_id), JSON_UNESCAPED_UNICODE);
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
    echo $api->get_board_good($bo_table, $wr_id);
  });
  /**
   * 작성자 제외, 회원만 가능
   * @param bo_table address;
   * @param wr_id address;
   * @param good,nogood address;
   */
  $router->match('POST|PUT', '/{bo_table}/{wr_id}/{good}', function($bo_table, $wr_id, $good) use ($api) {
    echo $api->good($bo_table, $wr_id, $good);
  });
  $router->get('/{bo_table}/{wr_id}/files', function($bo_table, $wr_id) use ($api) {
    $api->board_chk($bo_table, $wr_id);
    echo $api->get_board_file($bo_table, $wr_id);
  });
  $router->get('/{bo_table}/{wr_id}/comments', function($bo_table, $wr_id) use ($api) {
    $api->board_chk($bo_table, $wr_id);
    echo $api->get_cmt_list($bo_table, $wr_id);
  });
  $router->get('/{bo_table}/{wr_id}/comment/{comment_id}/good', function($bo_table, $wr_id, $comment_id) use ($api) {
    $api->board_chk($bo_table, $wr_id);
    echo $api->get_board_good_cmt($bo_table, $wr_id, $comment_id);
  });
  $router->get('/{bo_table}/{wr_id}/comment/{comment_id}/files', function($bo_table, $wr_id, $comment_id) use ($api) {
    $api->board_chk($bo_table, $wr_id);
    echo $api->get_board_file_cmt($mb_id);
  });
  $router->get('/{bo_table}/{wr_id}/comment/{comment_id}', function($bo_table, $wr_id, $comment_id) use ($api) {
    $api->board_chk($bo_table, $wr_id);
    echo $api->get_view_cmt($mb_id);
  });
  /**
   * 작성자, 관리자만 가능
   * @param bo_table address;
   * @param wr_id address;
   * @param comment_id address;
   */
  $router->match('DELETE|POST', '/{bo_table}/{wr_id}/comment/{comment_id}', function($bo_table, $wr_id, $comment_id) use ($api) {    
    echo $api->delete_comment($bo_table, $wr_id);
  });
  $router->get('/{bo_table}/{wr_id}', function($bo_table, $wr_id) use ($api) {
    $api->board_chk($bo_table, $wr_id);
    echo $api->get_views($bo_table, $wr_id);
  });
  /**
   * 작성자, 관리자만 가능
   * @param bo_table address;
   * @param wr_id address;
   * @param wr_password post;
   */
  $router->match('DELETE|POST', '/{bo_table}/{wr_id}', function($bo_table, $wr_id) use ($api) { //삭제 
    echo $api->delete($bo_table, $wr_id);
  });
  $router->get('/{bo_table}', function($bo_table) use ($api) {
    $api->board_chk($bo_table);
    echo $api->get_bbs_list($bo_table);
  });
  /**
   * 관리자만 가능
   * @param bo_table address;
   * @param POST chk_wr_id;
   */
  $router->match('DELETE|POST', '/{bo_table}/all', function($bo_table, $wr_id) use ($api) { //삭제 
    echo $api->delete_all($bo_table, $wr_id);
  });
});
$router->mount('/write', function() use ($router, $api) {
  $router->match('GET|POST', '/{bo_table}/{wr_id}/{$w}', function($bo_table, $wr_id, $w) use ($api) {
    $api->write($bo_table, $wr_id, $w);
  });
  $router->match('PUT|POST', '/{bo_table}/{wr_id}', function($bo_table, $wr_id) use ($api) {
    $api->board_chk($bo_table);
    echo $api->write_update($bo_table, $wr_id);
  });
  $router->match('PUT|POST', '/{bo_table}', function($bo_table) use ($api) {
    $api->board_chk($bo_table);
    echo $api->write_update($bo_table);
  });
  $router->match('GET', '/{bo_table}', function($bo_table) use ($api) {
    $api->write($bo_table);
  });
});
$router->match('GET', '/menus', function() use ($api){
  echo json_encode($api->get_menu_db(), JSON_UNESCAPED_UNICODE);
});
$router->match('GET', '/autosave', function() use ($api){
  echo $api->get_autosave();
});
$router->match('GET', '/profile/{mb_id}', function($mb_id) use ($api){
  echo $api->profile($mb_id);
});
$router->match('POST', '/password/{w}/{bo_table}/{wr_id}', function($w, $bo_table, $wr_id) use ($api){
  echo $api->password_check($w, $bo_table, $wr_id);
});
$router->match('GET', '/latest(/[a-z0-9_-]+)?(/[0-9_-]+)?(/[0-9_-]+)?', function($bo_table, $rows, $subject_len) use ($api) {
  $rows = $rows ? $rows : 10;
  $subject_len = $subject_len ? $subject_len : 40;
  echo $api->latest($bo_table, $rows, $subject_len);
});
$router->match('GET', '/popular(/[0-9_-]+)?(/[0-9_-]+)?', function($pop_cnt = 7, $date_cnt = 3) use ($api){
  $pop_cnt = $pop_cnt ? $pop_cnt : 7;
  $date_cnt = $date_cnt ? $date_cnt : 7;
  echo json_encode($api->popular($pop_cnt, $date_cnt), JSON_UNESCAPED_UNICODE);
});

/**
  * 관리자만 가능
  * @param qa_id address;
*/
$router->match('GET', '/qa/{qa_id}', function($qa_id) use ($api){
  echo json_encode($api->qaview($qa_id), JSON_UNESCAPED_UNICODE);
});

/**
 * 전부다 포스트로 처리
 * @param _POST post;
 */
$router->match('POST|PUT', '/register', function() use ($api) {
  echo json_encode($api->register(), JSON_UNESCAPED_UNICODE);
});
$router->match('POST|PUT', '/register_form', function() use ($api) {
  echo json_encode($api->register_form(), JSON_UNESCAPED_UNICODE);
});
$router->match('POST|PUT', '/register_form_update', function() use ($api) {
  echo json_encode($api->register_form_update(), JSON_UNESCAPED_UNICODE);
});
$router->match('GET', '/test', function() use ($api) {
  echo json_encode($api->kcaptcha_json(), JSON_UNESCAPED_UNICODE);
});
$router->match('GET', '/t', function() use ($api) {
  echo json_encode($api->chk_captcha(), JSON_UNESCAPED_UNICODE);
});
$router->mount('/captcha', function() use ($router, $api) {
  $router->match('GET', '/K', function() use ($api) {
    require API_PATH.'/plugin/kcaptcha/kcaptcha_image.php';
  });
});

$router->run();