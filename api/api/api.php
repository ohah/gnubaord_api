<?php
function api_g5_path() {
  $chroot = substr($_SERVER['SCRIPT_FILENAME'], 0, strpos($_SERVER['SCRIPT_FILENAME'], dirname(__FILE__))); 
  $result['path'] = str_replace('\\', '/', $chroot.dirname(__FILE__)); 
  $server_script_name = preg_replace('/\/+/', '/', str_replace('\\', '/', $_SERVER['SCRIPT_NAME'])); 
  $server_script_filename = preg_replace('/\/+/', '/', str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'])); 
  $tilde_remove = preg_replace('/^\/\~[^\/]+(.*)$/', '$1', $server_script_name); 
  $document_root = str_replace($tilde_remove, '', $server_script_filename); 
  $pattern = '/.*?' . preg_quote($document_root, '/') . '/i';
  $root = preg_replace($pattern, '', $result['path']); 
  $port = ($_SERVER['SERVER_PORT'] == 80 || $_SERVER['SERVER_PORT'] == 443) ? '' : ':'.$_SERVER['SERVER_PORT']; 
  $http = 'http' . ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on') ? 's' : '') . '://'; 
  $user = str_replace(preg_replace($pattern, '', $server_script_filename), '', $server_script_name); 
  $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME']; 
  if(isset($_SERVER['HTTP_HOST']) && preg_match('/:[0-9]+$/', $host)) 
      $host = preg_replace('/:[0-9]+$/', '', $host); 
  $host = preg_replace("/[\<\>\'\"\\\'\\\"\%\=\(\)\/\^\*]/", '', $host); 
  $result['url'] = $http.$host.$port.$user.$root; 
  $result['url'] = str_replace('/api/api', '', $result['url']);
  $result['path'] = str_replace('/api/api', '', $result['path']);
  return $result;
}
$g5_path = api_g5_path();
require "../config.php";
unset($g5_path);
require "../data/dbconfig.php";
require 'jwt/autoload.php';
use Firebase\JWT\JWT;
require 'lib/common.lib.php';
require '../lib/uri.lib.php';
class Gnuboard_api extends commonlib {
  /*
  alert 메시지
  */
  /*
    iss: 발급자
    sub: 제목 
    aud: 대상자 
    exp: 만료시간 type : NumericDate
    nbf: 활성시간(이 시간 후에 토큰 기능이 활성화 된다) type: NumericDate 
    iat: 생성시간
    jti: 고유키
  */
  public function Login($mb_id, $mb_password) {
    require 'lib/pbkdf2.compat.php'; //그누보드 password 처리;
    global $g5;
    if (!$mb_id || !$mb_password) $this->msg('회원아이디나 비밀번호가 공백이면 안됩니다.');
    $mb = $this->sql_fetch("SELECT * FROM {$g5['member_table']} WHERE mb_id = ?",[$mb_id]);
    if(!$this->login_password_check($mb, $mb_password, $mb['mb_password'])) {  //비밀번호가 다르면
      echo $this->msg('가입된 회원아이디가 아니거나 비밀번호가 틀립니다.\r\n비밀번호는 대소문자를 구분합니다.');
    }
    // 차단된 아이디인가?
    if ($mb['mb_intercept_date'] && $mb['mb_intercept_date'] <= date("Ymd", G5_SERVER_TIME)) {
      $date = preg_replace("/([0-9]{4})([0-9]{2})([0-9]{2})/", "\\1년 \\2월 \\3일", $mb['mb_intercept_date']);
      echo $this->msg('회원님의 아이디는 접근이 금지되어 있습니다.\n처리일 : '.$date);
    }
    // 탈퇴한 아이디인가?
    if ($mb['mb_leave_date'] && $mb['mb_leave_date'] <= date("Ymd", G5_SERVER_TIME)) {
      $date = preg_replace("/([0-9]{4})([0-9]{2})([0-9]{2})/", "\\1년 \\2월 \\3일", $mb['mb_leave_date']);
      echo $this->msg('탈퇴한 아이디이므로 접근하실 수 없습니다.\n탈퇴일 : '.$date);
    }
    // 메일인증 설정이 되어 있다면
    $config = json_decode($this->get_config(), true);
    $this->is_use_email_certify($config);
    if ( $this->is_use_email_certify($config) && !preg_match("/[1-9]/", $mb['mb_email_certify'])) {
      $ckey = md5($mb['mb_ip'].$mb['mb_datetime']);
      echo $this->msg('이메일 인증을 받으셔야 로그인이 가능합니다');
    }    
    if($this->login_password_check($mb, $mb_password, $mb['mb_password'])) {  //비밀번호가 같으면 
      $payload = array(
        "iss" => "kbl-ref.com",
        "iss" => "vorfeed",
        "aud" => $mb_id,
        "iat" => time(),
        "nbf" => time(),
        "exp" => strtotime("+7 day", time()),
      );
      $jwt = JWT::encode($payload, $this->key);
      setcookie($this->cookiename, $jwt, strtotime("+7 day", time()), '/');
      $decoded = JWT::decode($jwt, $this->key, array('HS256'));
      echo $this->msg('로그인 성공');
    }
  }
  public function get_board() {
    global $g5;
    $res = $this->sql_query("SELECT * FROM {$g5['board_table']}");
    return json_encode($res, JSON_UNESCAPED_UNICODE);
  }
  public function get_config() {
    global $g5;
    $res = $this->sql_fetch("SELECT * FROM {$g5['config_table']}");
    return json_encode($res, JSON_UNESCAPED_UNICODE);
  }
  public function get_content($co_id) {
    global $g5;
    if($co_id) $res = $this->sql_query("SELECT * FROM {$g5['content_table']} WHERE co_id = ?", [$co_id]);
    else $res = $this->sql_query("SELECT * FROM {$g5['content_table']}");
    return json_encode($res, JSON_UNESCAPED_UNICODE);
  }
  public function get_faq($fa_id) {
    global $g5;
    if($fa_id) $res = $this->sql_query("SELECT * FROM {$g5['faq_table']} WHERE fa_id = ?", [$fa_id]);
    else $res = $this->sql_query("SELECT * FROM {$g5['faq_table']}");
    return json_encode($res, JSON_UNESCAPED_UNICODE);
  }
  public function get_faq_group($fm_id) {
    global $g5;
    $res = $this->sql_query("SELECT * FROM {$g5['faq_master_table']} WHERE fm_id = ?", [$fm_id]);
    return json_encode($res, JSON_UNESCAPED_UNICODE);
  }
  public function get_group() {
    global $g5;
    $res = $this->sql_query("SELECT * FROM {$g5['group_table']}");
    return json_encode($res, JSON_UNESCAPED_UNICODE);
  }
  public function get_member($mb_id) {
    global $g5;
    if($mb_id) $res = $this->sql_fetch("SELECT * FROM {$g5['member_table']} WHERE mb_id = ?", [$mb_id]);
    else $res = $this->sql_fetch("SELECT * FROM {$g5['member_table']}");
    return json_encode($this->unset_data($res), JSON_UNESCAPED_UNICODE);
  }
  public function get_point($mb_id) {
    global $g5;
    $res = $this->sql_query("SELECT * FROM {$g5['point_table']} WHERE mb_id = ?", [$mb_id]);
    return json_encode($res, JSON_UNESCAPED_UNICODE);
  }
  public function get_scrap($mb_id) {
    global $g5;
    $res = $this->sql_query("SELECT * FROM {$g5['scrap_table']} WHERE mb_id = ?", [$mb_id]);
    return json_encode($this->unset_data($res), JSON_UNESCAPED_UNICODE);
  }
  public function get_menu() {
    global $g5;
    $res = $this->sql_query("SELECT * FROM {$g5['menu_table']} WHERE length(me_code) = ? ORDER By me_order, me_id", ['2']);
    for($i=0;$i<count($res);$i++) {
      $res[$i]['ori_me_link'] = $res[$i]['me_link'];
      $res[$i]['me_link'] = short_url_clean($res[$i]['me_link']);
      $row2 = $this->sql_query("SELECT * FROM {$g5['menu_table']} WHERE length(me_code) = ? AND substring(me_code, 1,2) = ? ORDER By me_order, me_id", ['4', $res[$i]['me_code']]);
      for($k=0;$k<count($row2);$k++) {
        $row2[$i]['ori_me_link'] = $row2[$i]['me_link'];
        $row2[$i]['me_link'] = short_url_clean($row2[$i]['me_link']);
        $res[$i]['sub'][$k] = $row2[$i];
      }
    }
    return json_encode($res, JSON_UNESCAPED_UNICODE);
  }
  public function get_board_good($bo_table, $wr_id) {
    global $g5;
    $res = $this->sql_query("SELECT * FROM {$g5['board_good_table']} WHERE bo_table = ? AND wr_id = ?", [$bo_table, $wr_id]);
    return json_encode($this->unset_data($res), JSON_UNESCAPED_UNICODE);
  }
  public function get_board_good_cmt($bo_table, $wr_id, $comment_id) {
    global $g5;
    $res = $this->sql_query("SELECT * FROM {$g5['board_good_table']} WHERE bo_table = ? AND wr_id = ?", [$bo_table, $comment_id]);
    return json_encode($this->unset_data($res), JSON_UNESCAPED_UNICODE);
  }
  public function get_board_file($bo_table, $wr_id) {
    global $g5;
    $res = $this->sql_query("SELECT * FROM {$g5['board_file_table']} WHERE bo_table = ? AND wr_id = ?", [$bo_table, $wr_id]);
    return json_encode($res, JSON_UNESCAPED_UNICODE);
  }
  public function get_board_file_cmt($bo_table, $wr_id, $comment_id) {
    global $g5;
    $res = $this->sql_query("SELECT * FROM {$g5['board_file_table']} WHERE bo_table = ? AND wr_id = ?", [$bo_table, $comment_id]);
    return json_encode($this->unset_data($res), JSON_UNESCAPED_UNICODE);
  }
  public function get_new_articles() {
    global $g5;
    $res = $this->sql_query("SELECT * FROM {$g5['board_new_table']}");
    return json_encode($this->unset_data($res), JSON_UNESCAPED_UNICODE);
  }
  public function get_view($bo_table, $wr_id) {
    global $g5;
    $write_table = $g5['write_prefix'].$bo_table;
    $res = $this->sql_query("SELECT * FROM {$write_table} WHERE wr_id = ?", [$wr_id]);
    return json_encode($this->unset_data($res), JSON_UNESCAPED_UNICODE);
  }
  public function get_autosave() {
    global $g5;
    if($this->is_guest) {
      echo $this->msg('비회원은 접근할 수 없습니다.');
      exit;
    };
    $res = $this->sql_query("SELECT * FROM {$g5['autosave_table']} WHERE wr_id = ?", [$this->member['mb_id']]);
    return json_encode($this->unset_data($res), JSON_UNESCAPED_UNICODE);
  }
  public function get_list($bo_table) {
    global $g5;
    $write_table = $g5['write_prefix'].$bo_table;
    $res = $this->sql_query("SELECT * FROM {$write_table}");
    return json_encode($this->unset_data($res), JSON_UNESCAPED_UNICODE);
  }
  public function get_view_cmt_list($bo_table, $wr_id) {
    global $g5;
    $write_table = $g5['write_prefix'].$bo_table;
    $res = $this->sql_query("SELECT * FROM {$write_table} WHERE wr_parent = ? AND wr_id <> ?", [$wr_id, $wr_id]);
    return json_encode($this->unset_data($res), JSON_UNESCAPED_UNICODE);
  }
  public function get_view_cmt($bo_table, $wr_id, $comment_id) {
    global $g5;
    $write_table = $g5['write_prefix'].$bo_table;
    $res = $this->sql_query("SELECT * FROM {$write_table} WHERE wr_id = ?", [$comment_id]);
    return json_encode($this->unset_data($res), JSON_UNESCAPED_UNICODE);
  }
  public function get_view_good($bo_table, $wr_id) {
    global $g5;
    $write_table = $g5['write_prefix'].$bo_table;
    $res = $this->sql_query("SELECT * FROM {$write_table} WHERE wr_id = ?", [$wr_id]);
    return json_encode($this->unset_data($res), JSON_UNESCAPED_UNICODE);
  }
}
/*
  
*/
?>