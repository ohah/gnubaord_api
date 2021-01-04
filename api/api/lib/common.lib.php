<?php
$g5['write_prefix'] = G5_TABLE_PREFIX.'write_'; // 게시판 테이블명 접두사
$g5['auth_table'] = G5_TABLE_PREFIX.'auth'; // 관리권한 설정 테이블
$g5['config_table'] = G5_TABLE_PREFIX.'config'; // 기본환경 설정 테이블
$g5['group_table'] = G5_TABLE_PREFIX.'group'; // 게시판 그룹 테이블
$g5['group_member_table'] = G5_TABLE_PREFIX.'group_member'; // 게시판 그룹+회원 테이블
$g5['board_table'] = G5_TABLE_PREFIX.'board'; // 게시판 설정 테이블
$g5['board_file_table'] = G5_TABLE_PREFIX.'board_file'; // 게시판 첨부파일 테이블
$g5['board_good_table'] = G5_TABLE_PREFIX.'board_good'; // 게시물 추천,비추천 테이블
$g5['board_new_table'] = G5_TABLE_PREFIX.'board_new'; // 게시판 새글 테이블
$g5['login_table'] = G5_TABLE_PREFIX.'login'; // 로그인 테이블 (접속자수)
$g5['mail_table'] = G5_TABLE_PREFIX.'mail'; // 회원메일 테이블
$g5['member_table'] = G5_TABLE_PREFIX.'member'; // 회원 테이블
$g5['memo_table'] = G5_TABLE_PREFIX.'memo'; // 메모 테이블
$g5['poll_table'] = G5_TABLE_PREFIX.'poll'; // 투표 테이블
$g5['poll_etc_table'] = G5_TABLE_PREFIX.'poll_etc'; // 투표 기타의견 테이블
$g5['point_table'] = G5_TABLE_PREFIX.'point'; // 포인트 테이블
$g5['popular_table'] = G5_TABLE_PREFIX.'popular'; // 인기검색어 테이블
$g5['scrap_table'] = G5_TABLE_PREFIX.'scrap'; // 게시글 스크랩 테이블
$g5['visit_table'] = G5_TABLE_PREFIX.'visit'; // 방문자 테이블
$g5['visit_sum_table'] = G5_TABLE_PREFIX.'visit_sum'; // 방문자 합계 테이블
$g5['uniqid_table'] = G5_TABLE_PREFIX.'uniqid'; // 유니크한 값을 만드는 테이블
$g5['autosave_table'] = G5_TABLE_PREFIX.'autosave'; // 게시글 작성시 일정시간마다 글을 임시 저장하는 테이블
$g5['cert_history_table'] = G5_TABLE_PREFIX.'cert_history'; // 인증내역 테이블
$g5['qa_config_table'] = G5_TABLE_PREFIX.'qa_config'; // 1:1문의 설정테이블
$g5['qa_content_table'] = G5_TABLE_PREFIX.'qa_content'; // 1:1문의 테이블
$g5['content_table'] = G5_TABLE_PREFIX.'content'; // 내용(컨텐츠)정보 테이블
$g5['faq_table'] = G5_TABLE_PREFIX.'faq'; // 자주하시는 질문 테이블
$g5['faq_master_table'] = G5_TABLE_PREFIX.'faq_master'; // 자주하시는 질문 마스터 테이블
$g5['new_win_table'] = G5_TABLE_PREFIX.'new_win'; // 새창 테이블
$g5['menu_table'] = G5_TABLE_PREFIX.'menu'; // 메뉴관리 테이블
$g5['social_profile_table'] = G5_TABLE_PREFIX.'member_social_profiles'; // 소셜 로그인 테이블
// $dir 을 포함하여 https 또는 http 주소를 반환한다.
require 'jwt/autoload.php';
require 'write_update.php';
use Firebase\JWT\JWT;
class commonlib {
  use write_update;
  public $g5;
  public $dsn = "mysql:host=".G5_MYSQL_HOST.";port=3306;dbname=".G5_MYSQL_DB.";charset=utf8";
  public $db;
  public $is_member = false;
  public $is_guest = false;
  public $is_admin = '';
  public $board = array();
  public $config = array();
  public $group = array();
  public $member = array();
  public $key = 'haskdlfjoieqimfqeif';
  public $cookiename = 'gnu_jwt';
  public function __construct() {
    try {
      $this->db = new PDO($this->dsn, G5_MYSQL_USER, G5_MYSQL_PASSWORD);
      $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
      $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $this->init();
    } catch(PDOException $e) {
      echo $this->msg('DB 연결에 실패하였습니다');
      $e->getMessage();
    }
  }
  // 관리자인가?
  public function is_admin($mb_id) {
    if (!$mb_id) return '';
    $is_authority = '';
    if ($this->config['cf_admin'] == $mb_id){
      $is_authority = 'super';
    } else if (isset($this->$group['gr_admin']) && ($this->$group['gr_admin'] == $mb_id)){
      $is_authority = 'group';
    } else if (isset($this->$board['bo_admin']) && ($this->$board['bo_admin'] == $mb_id)){
      $is_authority = 'board';
    }
    return $is_authority;
  }
  //권한체크
  public function init() {
    global $g5;
    $this->config = $this->sql_fetch("SELECT * FROM {$g5['config_table']}"); //그누보드 설정
    $this->config['cf_captcha'] = $this->config['cf_captcha'] ? $this->config['cf_captcha'] : 'kcaptcha';
    define('G5_CAPTCHA_DIR',    !empty($this->config['cf_captcha']) ? $this->config['cf_captcha'] : 'kcaptcha');
    define('G5_CAPTCHA_URL',    G5_PLUGIN_URL.'/'.G5_CAPTCHA_DIR);
    define('G5_CAPTCHA_PATH',   G5_PLUGIN_PATH.'/'.G5_CAPTCHA_DIR);
    if(isset($_COOKIE[$this->cookiename])) {
      $decoded = JWT::decode($_COOKIE[$this->cookiename], $this->key, array('HS256')); //로그인 여부
      $mb_id = $decoded->aud;
      $this->member = $this->sql_fetch("SELECT * FROM {$g5['member_table']} WHERE mb_id = ?", [$mb_id]); //회원정보 설정
      $this->is_admin = $this->is_admin($mb_id);
      $this->is_member = true;
      $this->is_guest = false;
    }else {
      $this->is_guest = true;
      $this->is_admin = false;
      $this->is_member = false;
      $this->member['mb_id'] = '';
      $this->member['mb_level'] = 1; // 비회원의 경우 회원레벨을 가장 낮게 설정
    }
  }  
  public function sql_query($query, $condition=array()) {
    try {
      $stmt = $this->db->prepare($query);
      if($condition) $stmt->execute($condition);
      else $stmt->execute();
      $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
      return $result;
    } catch(PDOException $e) {
      return $e->getMessage();
    }
  }
  public function sql_fetch($query, $condition=array()) {
    try {
      $stmt = $this->db->prepare($query); 
      if($condition) $stmt->execute($condition);
      else $stmt->execute();
      return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
      return $e->getMessage();
    }
  }
  public function msg($msg) {
    return json_encode(array('msg'=>$msg), JSON_UNESCAPED_UNICODE);
  }
  public function is_use_email_certify($config) {
    if( $config['cf_use_email_certify'] && function_exists('social_is_login_check') ){
      if( $config['cf_social_login_use'] && (get_session('ss_social_provider') || social_is_login_check()) ){      //소셜 로그인을 사용한다면
        $tmp = (defined('G5_SOCIAL_CERTIFY_MAIL') && G5_SOCIAL_CERTIFY_MAIL) ? 1 : 0;
        return $tmp;
      }
    }
    return $config['cf_use_email_certify'];
  }
  public function get_next_num($table) {
    // 가장 작은 번호를 얻어
    $row = $this->sql_fetch("SELECT MIN(wr_num) as min_wr_num FROM {$table}");
    // 가장 작은 번호에 1을 빼서 넘겨줌
    return (int)($row['min_wr_num'] - 1);
  }
  // 문자열 암호화
  public function get_encrypt_string($str) {
    if(defined('G5_STRING_ENCRYPT_FUNCTION') && G5_STRING_ENCRYPT_FUNCTION) {
      $encrypt = call_user_func(G5_STRING_ENCRYPT_FUNCTION, $str);
    } else {
      $encrypt = $this->sql_password($str);
    }
    return $encrypt;
  }

  // 비밀번호 비교
  public function check_password($pass, $hash) {
    if(defined('G5_STRING_ENCRYPT_FUNCTION') && G5_STRING_ENCRYPT_FUNCTION === 'create_hash') {
      return validate_password($pass, $hash);
    }
    $password = $this->get_encrypt_string($pass);
    return ($password === $hash);
  }

  // 로그인 패스워드 체크
  public function login_password_check($mb, $pass, $hash) {
    global $g5;
    $mb_id = isset($mb['mb_id']) ? $mb['mb_id'] : '';
    if(!$mb_id)
      return false;
    if(G5_STRING_ENCRYPT_FUNCTION === 'create_hash' && (strlen($hash) === G5_MYSQL_PASSWORD_LENGTH || strlen($hash) === 16)) {      
      if($this->sql_password($pass) === $hash){
        if(!isset($mb['mb_password2']) ){
          $sql = "ALTER TABLE `{$g5['member_table']}` ADD `mb_password2` varchar(255) NOT NULL default '' AFTER `mb_password`";
          $this->sql_query($sql);
        }    
        $new_password = create_hash($pass);
        $sql = "UPDATE {$g5['member_table']} SET mb_password = ?, mb_password2 = ? WHERE mb_id = ?";
        $this->sql_query($sql, [$new_password, $hash, $mb_id]);
        return true;
      }
    }
    return $this->check_password($pass, $hash);
  }
  // 세션변수 생성
  public function set_session($session_name, $value) {
    static $check_cookie = null;
    if( $check_cookie === null ){
      $cookie_session_name = session_name();
      if( ! ($cookie_session_name && isset($_COOKIE[$cookie_session_name]) && $_COOKIE[$cookie_session_name]) && ! headers_sent() ){
        @session_regenerate_id(false);
      }
      $check_cookie = 1;
    }
    if (PHP_VERSION < '5.3.0')
      session_register($session_name);
      // PHP 버전별 차이를 없애기 위한 방법
    $$session_name = $_SESSION[$session_name] = $value;
  }
  // 이메일 주소 추출
  public function get_email_address($email){
    preg_match("/[0-9a-z._-]+@[a-z0-9._-]{4,}/i", $email, $matches);
    return $matches[0];
  }
  // XSS 관련 태그 제거
  public function clean_xss_tags($str, $check_entities=0) {
    $str_len = strlen($str);
    $i = 0;
    while($i <= $str_len){
      $result = preg_replace('#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i', '', $str);
      if( $check_entities ){
        $result = str_replace(array('&colon;', '&lpar;', '&rpar;', '&NewLine;', '&Tab;'), '', $result);
      }        
      $result = preg_replace('#([^\p{L}]|^)(?:javascript|jar|applescript|vbscript|vbs|wscript|jscript|behavior|mocha|livescript|view-source)\s*:(?:.*?([/\\\;()\'">]|$))#ius','$1$2', $result);
      if((string)$result === (string)$str) break;
      $str = $result;
      $i++;
    }
    return $str;
  }
  public function board_permission($bo_table, $wr_id = '') {
    global $g5;
    $is_member = $is_guest = false;
    $is_admin = '';
    $write_table = $g5['write_prefix'].$bo_table;
    $config = $this->config;
    $board = $this->sql_fetch("SELECT count(*) cnt FROM {$g5['board_table']} WHERE bo_table = ?",[$bo_table]); //보드 설정
    if($board['cnt'] == 0) {
      echo $this->msg('존재하지 않는 게시판ID입니다');
      exit;
    }
    $board = $this->sql_fetch("SELECT * FROM {$g5['board_table']} WHERE bo_table = ?",[$bo_table]); //보드 설정    
    $group = $this->sql_fetch("SELECT * FROM {$g5['group_table']} WHERE gr_id = ?", [$board['gr_id']]); //그룹 설정
    $write = $this->sql_fetch("SELECT * FROM {$write_table} WHERE wr_id = ?", [$wr_id]);
    if ((isset($wr_id) && $wr_id)) {
      // 글이 없을 경우 해당 게시판 목록으로 이동
      if(!$write['wr_id']) {
        echo $this->msg('글이 존재하지 않습니다\r\n글이 삭제 되었거나 이동된 경우입니다.');
        exit;
      }
      if (isset($group['gr_use_access']) && $group['gr_use_access']) {
        if($this->is_guest) {
          echo $this->msg('비회원은 이 게시판에 접근할 권한이 없습니다.\r\n회원이시라면 로그인 후 이용해 보십시오.');
          exit;
        }
        if ($this->is_admin == "super" || $this->is_admin == "group") {
          ;
        } else {
          // 그룹접근        
          $row = $this->sql_fetch("SELECT count(*) as cnt FROM {$g5['group_member_table']} WHERE gr_id = ? AND mb_id = ?", [$board['gr_id'], $member['mb_id']]);
          if (!$row['cnt']) {
            echo $this->msg('접근 권한이 없으므로 글읽기가 불가합니다.\r\n궁금하신 사항은 관리자에게 문의 바랍니다.');
            exit;
          }
        }
      }
      // 로그인된 회원의 권한이 설정된 읽기 권한보다 작다면
      if ($this->member['mb_level'] < $board['bo_read_level']) {
        if ($this->is_member) {
          echo $this->msg('글을 읽을 권한이 없습니다.');
          exit;
        } else {
          echo $this->msg('글을 읽을 권한이 없습니다.\\n\\n회원이시라면 로그인 후 이용해 보십시오.');
          exit;
        }
      }
      // 본인확인을 사용한다면
      if ($config['cf_cert_use'] && !$this->is_admin) {
        // 인증된 회원만 가능
        if ($board['bo_use_cert'] != '' && $this->is_guest) {
          echo $this->msg('이 게시판은 본인확인 하신 회원님만 글읽기가 가능합니다.\\n\\n회원이시라면 로그인 후 이용해 보십시오.');
          exit;
        }
        if ($board['bo_use_cert'] == 'cert' && !$this->member['mb_certify']) {
          echo $this->msg('이 게시판은 본인확인 하신 회원님만 글읽기가 가능합니다.\\n\\n회원정보 수정에서 본인확인을 해주시기 바랍니다.');
          exit;
        }
        if ($board['bo_use_cert'] == 'adult' && !$this->member['mb_adult']) {
          echo $this->msg('이 게시판은 본인확인으로 성인인증 된 회원님만 글읽기가 가능합니다.\\n\\n현재 성인인데 글읽기가 안된다면 회원정보 수정에서 본인확인을 다시 해주시기 바랍니다.');
          exit;
        }
        if ($board['bo_use_cert'] == 'hp-cert' && $this->member['mb_certify'] != 'hp') {
          echo $this->msg('이 게시판은 휴대폰 본인확인 하신 회원님만 글읽기가 가능합니다.\\n\\n회원정보 수정에서 휴대폰 본인확인을 해주시기 바랍니다.');
          exit;
        }
        if ($board['bo_use_cert'] == 'hp-adult' && (!$this->member['mb_adult'] || $this->member['mb_certify'] != 'hp')) {
          echo $this->msg('이 게시판은 휴대폰 본인확인으로 성인인증 된 회원님만 글읽기가 가능합니다.\\n\\n현재 성인인데 글읽기가 안된다면 회원정보 수정에서 휴대폰 본인확인을 다시 해주시기 바랍니다.');
          exit;
        }
      }

      // 자신의 글이거나 관리자라면 통과
      if (($write['mb_id'] && $write['mb_id'] === $this->member['mb_id']) || $this->is_admin) {
        ;
      } else {
        // 비밀글이라면
        if (strstr($write['wr_option'], "secret")) {
          // 회원이 비밀글을 올리고 관리자가 답변글을 올렸을 경우
          // 회원이 관리자가 올린 답변글을 바로 볼 수 없던 오류를 수정
          $is_owner = false;
          if ($write['wr_reply'] && $this->member['mb_id']) {
            $row = $this->sql_fetch("SELECT mb_id {$write_table} WHERE wr_num = ? AND wr_reply = ? AND wr_is_comment = ?", [$write['wr_num'], '', '0']);
            if ($row['mb_id'] === $this->member['mb_id']) $is_owner = true;
          }
          $ss_name = 'ss_secret_'.$bo_table.'_'.$write['wr_num'];
          if (!$is_owner) {
            if (!get_session($ss_name)) {
              echo $this->msg('비밀번호를 입력하세요');
              exit;
            }
          }
          $this->set_session($ss_name, TRUE);
        }
      }
      // 한번 읽은글은 브라우저를 닫기전까지는 카운트를 증가시키지 않음
      $ss_name = 'ss_view_'.$bo_table.'_'.$wr_id;
      if (!$this->get_session($ss_name)) {
        $this->sql_query("UPDATE {$write_table} SET wr_hit = ? WHERE wr_id = ?", ["wr_hit + 1", $wr_id]);
        // 자신의 글이면 통과
        if ($write['mb_id'] && $write['mb_id'] === $this->member['mb_id']) {
          ;
        } else if ($is_guest && $board['bo_read_level'] == 1 && $write['wr_ip'] == $_SERVER['REMOTE_ADDR']) {
          // 비회원이면서 읽기레벨이 1이고 등록된 아이피가 같다면 자신의 글이므로 통과
          ;
        } else {
          // 글읽기 포인트가 설정되어 있다면
          if ($config['cf_use_point'] && $board['bo_read_point'] && $member['mb_point'] + $board['bo_read_point'] < 0) {
            echo $this->msg('보유하신 포인트('.number_format($member['mb_point']).')가 없거나 모자라서 글읽기('.number_format($board['bo_read_point']).')가 불가합니다.\r\n포인트를 모으신 후 다시 글읽기 해 주십시오.');
            exit;
          }
          //인서트 포인트 함수 추가해야함
          //insert_point($member['mb_id'], $board['bo_read_point'], ((G5_IS_MOBILE && $board['bo_mobile_subject']) ? $board['bo_mobile_subject'] : $board['bo_subject']).' '.$wr_id.' 글읽기', $bo_table, $wr_id, '읽기');
        }
        $this->set_session($ss_name, TRUE);
      }
    }else { //리스트 검사
      if ($this->member['mb_level'] < $board['bo_list_level']) {
        if ($this->member['mb_id']) {
          echo $this->msg('목록을 읽을 권한이 없습니다.');
          exit;
        }else {
          echo $this->msg('목록을 볼 권한이 없습니다.\r\n회원이시라면 로그인 후 이용해 보십시오.');
          exit;
        }
      }

      // 본인확인을 사용한다면
      if ($config['cf_cert_use'] && !$this->is_admin) {
        // 인증된 회원만 가능
        if ($board['bo_use_cert'] != '' && $this->is_guest) {
          echo $this->msg('이 게시판은 본인확인 하신 회원님만 글읽기가 가능합니다.\r\n회원이시라면 로그인 후 이용해 보십시오.');
        }
        if ($board['bo_use_cert'] == 'cert' && !$this->member['mb_certify']) {
          echo $this->msg('이 게시판은 본인확인 하신 회원님만 글읽기가 가능합니다.\r\n회원정보 수정에서 본인확인을 해주시기 바랍니다.');
          exit;
        }
        if ($board['bo_use_cert'] == 'adult' && !$this->member['mb_adult']) {
          echo $this->msg('이 게시판은 본인확인으로 성인인증 된 회원님만 글읽기가 가능합니다.\r\n현재 성인인데 글읽기가 안된다면 회원정보 수정에서 본인확인을 다시 해주시기 바랍니다.');
          exit;
        }
        if ($board['bo_use_cert'] == 'hp-cert' && $this->member['mb_certify'] != 'hp') {
          echo $this->msg('이 게시판은 휴대폰 본인확인 하신 회원님만 글읽기가 가능합니다.\r\n회원정보 수정에서 휴대폰 본인확인을 해주시기 바랍니다.');
          exit;
        }
        if ($board['bo_use_cert'] == 'hp-adult' && (!$this->member['mb_adult'] || $this->member['mb_certify'] != 'hp')) {
          echo $this->msg('이 게시판은 휴대폰 본인확인으로 성인인증 된 회원님만 글읽기가 가능합니다.\r\n현재 성인인데 글읽기가 안된다면 회원정보 수정에서 휴대폰 본인확인을 다시 해주시기 바랍니다.');
          exit;
        }
      }
    }
    return "";
  }
  public function unset_data($data) { //권한이 없는 사용자들에게 노출되면 안되는 그누보드 내용
    if(!$this->is_admin) {
      unset($data['cf_icode_id']);
      unset($data['cf_icode_pw']);
      unset($data['cf_googl_shorturl_apikey']);
      unset($data['cf_google_clientid']);
      unset($data['cf_google_secret']);
      unset($data['cf_icode_server_ip']);
      unset($data['cf_icode_server_port']);
      unset($data['cf_icode_token_key']);
      unset($data['cf_icode_token_key']);
      unset($data['config']['cf_icode_id']);
      unset($data['config']['cf_icode_pw']);
      unset($data['config']['cf_googl_shorturl_apikey']);
      unset($data['config']['cf_google_clientid']);
      unset($data['config']['cf_google_secret']);
      unset($data['config']['cf_icode_server_ip']);
      unset($data['config']['cf_icode_server_port']);
      unset($data['config']['cf_icode_token_key']);
      unset($data['config']['cf_icode_token_key']);
      unset($data['member']['mb_password']);
      unset($data['ss_name']);
      unset($data['sst']);
      unset($data['stx']);
      unset($data['sql']);
      unset($data['sql2']);
      unset($data['sql3']);
      unset($data['sql_search']);
      unset($data['sql_common']);
      unset($data['sql_order']);
      unset($data['result']);
      unset($data['config']['cf_recaptcha_secret_key']);
      unset($data['config']['cf_recaptcha_site_key']);
      if(is_array($data)){
        for ($i=0; $i < count($data); $i++) { 
          if(isset($data[$i]['wr_ip'])) $data[$i]['wr_ip'] = preg_replace("/([0-9]+).([0-9]+).([0-9]+).([0-9]+)/", G5_IP_DISPLAY, $data[$i]['wr_ip']);
          unset($data[$i]['wr_password']);
        }
      }
      unset($data['wr_password']);
      if(isset($data['wr_ip'])) $data['wr_ip'] = preg_replace("/([0-9]+).([0-9]+).([0-9]+).([0-9]+)/", G5_IP_DISPLAY, $data['wr_ip']);

      unset($data['mb_password']);
      unset($data['mb_login_ip']);
      unset($data['mb_ip']);
      unset($data['mb_email']);
      unset($data['mb_addr1']);
      unset($data['mb_addr2']);
      unset($data['mb_addr3']);
      unset($data['mb_addr_jibeon']);
      unset($data['mb_birth']);
      unset($data['mb_tel']);
      unset($data['mb_hp']);
    }
    return $data;
  }
}
?>