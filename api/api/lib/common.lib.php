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
require API_PATH.'/bbs/board.php';
require API_PATH.'/bbs/write_update.php';
require API_PATH.'/bbs/write.php';
require API_PATH.'/plugin/kcaptcha/kcaptcha.lib.php';
require API_PATH.'/lib/uri.lib.php';
require API_PATH.'/lib/get_data.lib.php';
require API_PATH.'/lib/naver_syndi.lib.php';
use Firebase\JWT\JWT;
class Commonlib {
  use board;
  use write;
  use write_update;
  use KCAPTCHA;
  use urllib;
  use naver_syndilib;
  use get_datalib;
  public $cookiename = 'gnu_jwt';
  public function __construct() {
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

  function get_category_option($bo_table='', $ca_name='') {
    global $g5;
    $is_admin = $this->is_admin;
    $board = $this->get_board_db($bo_table);

    $categories = explode("|", $board['bo_category_list'].($is_admin?"|공지":"")); // 구분자가 | 로 되어 있음
    $str = "";
    for ($i=0; $i<count($categories); $i++) {
      $category = trim($categories[$i]);
      if (!$category) continue;

      $str .= "<option value=\"$categories[$i]\"";
      if ($category == $ca_name) {
        $str .= ' selected="selected"';
      }
      $str .= ">$categories[$i]</option>\n";
    }
    return $str;
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
  public function alert($msg, $url = '') {
    echo json_encode(array('msg'=>$msg, 'url', $url), JSON_UNESCAPED_UNICODE);
    exit;
  }

  // 파일을 보이게 하는 링크 (이미지, 플래쉬, 동영상)
  public function view_file_link($file, $width, $height, $content='') {
    global $g5;
    static $ids;
    $config = $this->config;
    $board = $this->board;
    if (!$file) return;
    $ids++;
    // 파일의 폭이 게시판설정의 이미지폭 보다 크다면 게시판설정 폭으로 맞추고 비율에 따라 높이를 계산
    if ($width > $board['bo_image_width'] && $board['bo_image_width']) {
      $rate = $board['bo_image_width'] / $width;
      $width = $board['bo_image_width'];
      $height = (int)($height * $rate);
    }
    // 폭이 있는 경우 폭과 높이의 속성을 주고, 없으면 자동 계산되도록 코드를 만들지 않는다.
    if ($width)
      $attr = ' width="'.$width.'" height="'.$height.'" ';
    else
      $attr = '';
    if (preg_match("/\.({$config['cf_image_extension']})$/i", $file)) {
      $attr_href = G5_BBS_URL.'/view_image.php?bo_table='.$board['bo_table'].'&fn='.urlencode($file);
      $img = '<a href="'.$attr_href.'" target="_blank" class="view_image">';
      $img .= '<img src="'.G5_DATA_URL.'/file/'.$board['bo_table'].'/'.urlencode($file).'" alt="'.$content.'" '.$attr.'>';
      $img .= '</a>';

      return $img;
    }
  }

  // http://htmlpurifier.org/
  // Standards-Compliant HTML Filtering
  // Safe  : HTML Purifier defeats XSS with an audited whitelist
  // Clean : HTML Purifier ensures standards-compliant output
  // Open  : HTML Purifier is open-source and highly customizable
  public function html_purifier($html) {
    $f = file(G5_PLUGIN_PATH.'/htmlpurifier/safeiframe.txt');
    $domains = array();
    foreach($f as $domain){
      // 첫행이 # 이면 주석 처리
      if (!preg_match("/^#/", $domain)) {
        $domain = trim($domain);
        if ($domain)
          array_push($domains, $domain);
      }
    }
    // 내 도메인도 추가
    array_push($domains, $_SERVER['HTTP_HOST'].'/');
    $safeiframe = implode('|', $domains);

    include_once(G5_PLUGIN_PATH.'/htmlpurifier/HTMLPurifier.standalone.php');
    include_once(G5_PLUGIN_PATH.'/htmlpurifier/extend.video.php');
    $config = HTMLPurifier_Config::createDefault();
    // data/cache 디렉토리에 CSS, HTML, URI 디렉토리 등을 만든다.
    $config->set('Cache.SerializerPath', G5_DATA_PATH.'/cache');
    $config->set('HTML.SafeEmbed', false);
    $config->set('HTML.SafeObject', false);
    $config->set('Output.FlashCompat', false);
    $config->set('HTML.SafeIframe', true);
    if( (function_exists('check_html_link_nofollow') && check_html_link_nofollow('html_purifier')) ){
        $config->set('HTML.Nofollow', true);    // rel=nofollow 으로 스팸유입을 줄임
    }
    $config->set('URI.SafeIframeRegexp','%^(https?:)?//('.$safeiframe.')%');
    $config->set('Attr.AllowedFrameTargets', array('_blank'));
    //유튜브, 비메오 전체화면 가능하게 하기
    $config->set('Filter.Custom', array(new HTMLPurifier_Filter_Iframevideo()));
    $purifier = new HTMLPurifier($config);
    return $purifier->purify($html);
  }
  // 3.31
  // HTML SYMBOL 변환
  // &nbsp; &amp; &middot; 등을 정상으로 출력
  public function html_symbol($str) {
    return preg_replace("/\&([a-z0-9]{1,20}|\#[0-9]{0,3});/i", "&#038;\\1;", $str);
  }
  function autosave_count($mb_id){
    global $g5;
    if ($mb_id) {
      $row = $this->sql_fetch("SELECT count(*) as cnt FROM {$g5['autosave_table']} WHERE mb_id = ?",[$mb_id]);
      return (int)$row['cnt'];
    } else {
      return 0;
    }
  }
  public function is_mobile() {
    return preg_match('/'.G5_MOBILE_AGENT.'/i', $_SERVER['HTTP_USER_AGENT']);
  }
  public function cut_str($str, $len, $suffix="…") {
    $arr_str = preg_split("//u", $str, -1, PREG_SPLIT_NO_EMPTY);
    $str_len = count($arr_str);
    if ($str_len >= $len) {
      $slice_str = array_slice($arr_str, 0, $len);
      $str = join("", $slice_str);

      return $str . ($str_len > $len ? $suffix : '');
    } else {
      $str = join("", $arr_str);
      return $str;
    }
  }
  // TEXT 형식으로 변환
  public function get_text($str, $html=0, $restore=false) {
    $source[] = "<";
    $target[] = "&lt;";
    $source[] = ">";
    $target[] = "&gt;";
    $source[] = "\"";
    $target[] = "&#034;";
    $source[] = "\'";
    $target[] = "&#039;";
    if($restore) $str = str_replace($target, $source, $str);
    // 3.31
    // TEXT 출력일 경우 &amp; &nbsp; 등의 코드를 정상으로 출력해 주기 위함
    if ($html == 0) {
      $str = $this->html_symbol($str);
    }
    if ($html) {
      $source[] = "\n";
      $target[] = "<br/>";
    }
    return str_replace($source, $target, $str);
  }
  // 파일의 용량을 구한다.
  //function get_filesize($file)
  public function get_filesize($size) {
    if ($size >= 1048576) {
      $size = number_format($size/1048576, 1) . "M";
    } else if ($size >= 1024) {
      $size = number_format($size/1024, 1) . "K";
    } else {
      $size = number_format($size, 0) . "byte";
    }
    return $size;
  }

  // 게시글에 첨부된 파일을 얻는다. (배열로 반환)
  public function get_file($bo_table, $wr_id) {
    global $g5;
    $file['count'] = 0;
    $sql = "SELECT * FROM {$g5['board_file_table']} WHERE bo_table = ? AND wr_id = ? ORDER BY bf_no";
    $result = $this->sql_query($sql, [$bo_table, $wr_id]);
    for($i=0;$i<count($result);$i++) {
      $row = $result[$i];
      $no = $row['bf_no'];
      $bf_content = $row['bf_content'] ? $this->html_purifier($row['bf_content']) : '';
      $file[$no]['href'] = G5_BBS_URL."/download.php?bo_table=$bo_table&wr_id=$wr_id&no=$no";
      $file[$no]['download'] = $row['bf_download'];
      // 4.00.11 - 파일 path 추가
      $file[$no]['path'] = G5_DATA_URL.'/file/'.$bo_table;
      $file[$no]['size'] = $this->get_filesize($row['bf_filesize']);
      $file[$no]['datetime'] = $row['bf_datetime'];
      $file[$no]['source'] = addslashes($row['bf_source']);
      $file[$no]['bf_content'] = $bf_content;
      $file[$no]['content'] = $this->get_text($bf_content);
      //$file[$no]['view'] = view_file_link($row['bf_file'], $file[$no]['content']);
      $this->board = $this->get_board_db($bo_table, true);
      $file[$no]['view'] = $this->view_file_link($row['bf_file'], $row['bf_width'], $row['bf_height'], $file[$no]['content']);
      $file[$no]['file'] = $row['bf_file'];
      $file[$no]['image_width'] = $row['bf_width'] ? $row['bf_width'] : 640;
      $file[$no]['image_height'] = $row['bf_height'] ? $row['bf_height'] : 480;
      $file[$no]['image_type'] = $row['bf_type'];
      $file[$no]['bf_fileurl'] = $row['bf_fileurl'];
      $file[$no]['bf_thumburl'] = $row['bf_thumburl'];
      $file[$no]['bf_storage'] = $row['bf_storage'];
      $file['count']++;
    }
    return $file;
  }

  // 게시판 테이블에서 하나의 행을 읽음
  public function get_write($write_table, $wr_id, $is_cache=false) {
    global $g5, $g5_object;
    $wr_bo_table = preg_replace('/^'.preg_quote($g5['write_prefix']).'/i', '', $write_table);
    $write = $g5_object->get('bbs', $wr_id, $wr_bo_table);
    if( !$write || $is_cache == false ){
      $sql = "SELECT * FROM {$write_table} WHERE wr_id = ?";
      $write = $this->sql_fetch($sql, [$wr_id]);
      $g5_object->set('bbs', $wr_id, $write, $wr_bo_table);
    }
    return $write;
  }
  public function board_notice($bo_notice, $wr_id, $insert=false) {
    $notice_array = explode(",", trim($bo_notice));
    if($insert && in_array($wr_id, $notice_array))
      return $bo_notice;

    $notice_array = array_merge(array($wr_id), $notice_array);
    $notice_array = array_unique($notice_array);
    foreach ($notice_array as $key=>$value) {
      if (!trim($value))
        unset($notice_array[$key]);
    }
    if (!$insert) {
      foreach ($notice_array as $key=>$value) {
        if ((int)$value == (int)$wr_id)
          unset($notice_array[$key]);
      }
    }
    return implode(",", $notice_array);
  }
  public function get_selected($field, $value) {
    if( is_int($value) ){
      return ((int) $field===$value) ? true : false;
    }
    return ($field===$value) ? true : false;
  }
  public function utf8_strcut( $str, $size, $suffix='...' ) {
    if( function_exists('mb_strlen') && function_exists('mb_substr') ){
      if(mb_strlen($str)<=$size) {
        return $str;
      } else {
        $str = mb_substr($str, 0, $size, 'utf-8');
        $str .= $suffix;
      }

    } else {
      $substr = substr( $str, 0, $size * 2 );
      $multi_size = preg_match_all( '/[\x80-\xff]/', $substr, $multi_chars );
      if ( $multi_size > 0 )
        $size = $size + intval( $multi_size / 3 ) - 1;
      if ( strlen( $str ) > $size ) {
        $str = substr( $str, 0, $size );
        $str = preg_replace( '/(([\x80-\xff]{3})*?)([\x80-\xff]{0,2})$/', '$1', $str );
        $str .= $suffix;
      }
    }
    return $str;
  }
  public function is_use_email_certify($config) {
    if( $config['cf_use_email_certify'] && function_exists('social_is_login_check') ){
      if( $config['cf_social_login_use'] && ($this->et_session('ss_social_provider') || social_is_login_check()) ){      //소셜 로그인을 사용한다면
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

  // 그룹 설정 테이블에서 하나의 행을 읽음
  public function get_group($gr_id, $is_cache=false) {
    global $g5;
    if( is_array($gr_id) ){
      return array();
    }
    static $cache = array();

    $gr_id = preg_replace('/[^a-z0-9_]/i', '', $gr_id);
    $cache = run_replace('get_group_db_cache', $cache, $gr_id, $is_cache);
    $key = md5($gr_id);

    if( $is_cache && isset($cache[$key]) ){
      return $cache[$key];
    }
    $sql = " select * from {$g5['group_table']} where gr_id = ?";

    $group = run_replace('get_group', $this->sql_fetch($sql, [$gr_id]), $gr_id, $is_cache);
    $cache[$key] = array_merge(array('gr_device'=>'', 'gr_subject'=>''), (array) $group);

    return $cache[$key];
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
  // 세션변수값 얻음
  public function get_session($session_name) {
    return isset($_SESSION[$session_name]) ? $_SESSION[$session_name] : '';
  }
  // 이메일 주소 추출
  public function get_email_address($email){
    preg_match("/[0-9a-z._-]+@[a-z0-9._-]{4,}/i", $email, $matches);
    return $matches[0];
  }
  // 파일명에서 특수문자 제거
  public function get_safe_filename($name) {
    $pattern = '/["\'<>=#&!%\\\\(\)\*\+\?]/';
    $name = preg_replace($pattern, '', $name);

    return $name;
  }
  // 파일명 치환
  function replace_filename($name) {
    @session_start();
    $ss_id = session_id();
    $usec = get_microtime();
    $file_path = pathinfo($name);
    $ext = $file_path['extension'];
    $return_filename = sha1($ss_id.$_SERVER['REMOTE_ADDR'].$usec); 
    if( $ext )
      $return_filename .= '.'.$ext;

    return $return_filename;
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
  // 관리자 정보를 얻음
  function get_admin($admin='super', $fields='*') {
    $config = $this->config;
    global $group, $board;
    global $g5;
    $is = false;
    if ($admin == 'board') {
      $mb = $this->sql_fetch("select {$fields} from {$g5['member_table']} where mb_id in (?) limit 1", [$board['bo_admin']]);
      $is = true;
    }
    if (($is && !$mb['mb_id']) || $admin == 'group') {
      $mb = $this->sql_fetch("select {$fields} from {$g5['member_table']} where mb_id in (?) limit 1 ",[$group['gr_admin']]);
      $is = true;
    }
    if (($is && !$mb['mb_id']) || $admin == 'super') {
      $mb = sql_fetch("select {$fields} from {$g5['member_table']} where mb_id in ('?') limit 1 ", [$config['cf_admin']]);
    }

    return $mb;
  }
    
  // $dir 을 포함하여 https 또는 http 주소를 반환한다.
  public function https_url($dir, $https=true){
    if ($https) {
      if (G5_HTTPS_DOMAIN) {
        $url = G5_HTTPS_DOMAIN.'/'.$dir;
      } else {
        $url = G5_URL.'/'.$dir;
      }
    } else {
      if (G5_DOMAIN) {
        $url = G5_DOMAIN.'/'.$dir;
      } else {
        $url = G5_URL.'/'.$dir;
      }
    }

    return $url;
  }


  //포인트 관련
  public function insert_point($mb_id, $point, $content='', $rel_table='', $rel_id='', $rel_action='', $expire=0) {
    global $g5;
    $config = $this->$config;
    $is_admin = $this->is_admin;
    // 포인트 사용을 하지 않는다면 return
    if (!$config['cf_use_point']) { return 0; }
  
    // 포인트가 없다면 업데이트 할 필요 없음
    if ($point == 0) { return 0; }
  
    // 회원아이디가 없다면 업데이트 할 필요 없음
    if ($mb_id == '') { return 0; }
    $mb = $this->sql_fetch("SELECT mb_id FROM {$g5['member_table']} WHERE mb_id = ?", [$mb_id]);
    if (!$mb['mb_id']) { return 0; }

    // 회원포인트
    $mb_point = $this->get_point_sum($mb_id);

    // 이미 등록된 내역이라면 건너뜀
    if ($rel_table || $rel_id || $rel_action) {
      $sql = "SELECT count(*) as cnt from {$g5['point_table']}
              WHERE mb_id = ?
              AND po_rel_table = ?
              AND po_rel_id = ?
              AND po_rel_action = ? ";
      $row = $this->sql_fetch($sql, [$mb_id, $rel_table, $rel_id, $rel_action]);      
      if ($row['cnt']) return -1;
    }

    // 포인트 건별 생성
    $po_expire_date = '9999-12-31';
    if($config['cf_point_term'] > 0) {
        if($expire > 0)
            $po_expire_date = date('Y-m-d', strtotime('+'.($expire - 1).' days', G5_SERVER_TIME));
        else
            $po_expire_date = date('Y-m-d', strtotime('+'.($config['cf_point_term'] - 1).' days', G5_SERVER_TIME));
    }

    $po_expired = 0;
    if($point < 0) {
        $po_expired = 1;
        $po_expire_date = G5_TIME_YMD;
    }
    $po_mb_point = $mb_point + $point;

    $sql = "INSERT INTO {$g5['point_table']}
            SET mb_id = ?,
            po_datetime = ?,
            po_content = ?,
            po_point = ?,
            po_use_point = ?,
            po_mb_point = ?,
            po_expired = ?,
            po_expire_date = ?,
            po_rel_table = ?,
            po_rel_id = ?,
            po_rel_action = ?";
    $this->sql_query($sql, [$mb_id, G5_TIME_YMDHIS, addslashes($content), $point, '0', $po_mb_point, $po_expired, $po_expire_date, $rel_table, $rel_id, $rel_action]);

    // 포인트를 사용한 경우 포인트 내역에 사용금액 기록
    if($point < 0) {
      $this->insert_use_point($mb_id, $point);
    }

    // 포인트 UPDATE
    $this->sql_query("UPDATE {$g5['member_table']} SET mb_point = ? WHERE mb_id = ?",[$po_mb_point, $mb_id]);

    return 1;
  }
  
  // 사용포인트 입력
  public function insert_use_point($mb_id, $point, $po_id='')
  {
    global $g5;
    $config = $this->config;
    if($config['cf_point_term'])
      $sql_order = " order by po_expire_date asc, po_id asc ";
    else
      $sql_order = " order by po_id asc ";
  
    $point1 = abs($point);
    $sql = "SELECT po_id, po_point, po_use_point
            FROM {$g5['point_table']}
            WHERE mb_id = ?
            AND po_id <> ?
            AND po_expired = ?
            AND ?
            $sql_order ";
    $result = $this->sql_query($sql, [$mb_id, $po_id, '0', 'po_point > po_use_point']);
    for($i=0; $i<count($result); $i++) {
      $row = $result[$i];
      $point2 = $row['po_point'];
      $point3 = $row['po_use_point'];

      if(($point2 - $point3) > $point1) {
        $sql = "UPDATE {$g5['point_table']}
                SET po_use_point = ?
                WHERE po_id = ?";
        sql_query($sql);
        $this->sql_query($sql, ['po_use_point + '.$point1, $row['po_id']]);
        break;
      } else {
        $point4 = $point2 - $point3;
        $sql = "UPDATE {$g5['point_table']}
                SET po_use_point = ?,
                    po_expired = ?'
                WHERE po_id = ?";
        $this->sql_query($sql, ['po_use_point + '.$point4, '100', $row['po_id']]);
        $point1 -= $point4;
      }
    }
  }
  
  // 사용포인트 삭제
  public function delete_use_point($mb_id, $point) {
    global $g5;
    $config = $this->config;

    if($config['cf_point_term'])
      $sql_order = " order by po_expire_date desc, po_id desc ";
    else
      $sql_order = " order by po_id desc ";

    $point1 = abs($point);
    $sql = "SELECT po_id, po_use_point, po_expired, po_expire_date
            FROM {$g5['point_table']}
            WHERE mb_id = ?
            AND ?
            AND ?
            $sql_order ";
    $result = $this->sql_query($sql, [$mb_id, "po_expired <> '1'", "po_use_point > 0"]);
    for($i=0; $i<count($result); $i++) {
      $row = $result[$i];
      $point2 = $row['po_use_point'];

      $po_expired = $row['po_expired'];
      if($row['po_expired'] == 100 && ($row['po_expire_date'] == '9999-12-31' || $row['po_expire_date'] >= G5_TIME_YMD))
        $po_expired = 0;

      if($point2 > $point1) {
        $sql = "UPDATE {$g5['point_table']}
                SET po_use_point = ?,
                    po_expired = ?
                WHERE po_id = ?";
        $this->sql_query($sql, ["po_use_point - '$point1'", $po_expired, $row['po_id']]);
        break;
      } else {
          $sql = "UPDATE {$g5['point_table']}
                  SET po_use_point = ?,
                      po_expired = ?
                  WHERE po_id = ?";
          $this->sql_query($sql, ["0", $po_expired, $row['po_id']]);
          $point1 -= $point2;
      }
    }
  }
  
  // 소멸포인트 삭제
  public function delete_expire_point($mb_id, $point) {
      global $g5;
      $config = $this->config;
  
      $point1 = abs($point);
      $sql = "SELECT po_id, po_use_point, po_expired, po_expire_date
              FROM {$g5['point_table']}
              WHERE mb_id = ?
              AND po_expired = ?
              AND po_point >= ?
              AND po_use_point > ?
              ORDER BY po_expire_date DESC, po_id DESC";
      $result = $this->sql_query($sql, [$mb_id, '1', 0, 0]);
      for($i=0; $i<count($result); $i++) {
        $row = $result[$i];
        $point2 = $row['po_use_point'];
        $po_expired = '0';
        $po_expire_date = '9999-12-31';
        if($config['cf_point_term'] > 0)
          $po_expire_date = date('Y-m-d', strtotime('+'.($config['cf_point_term'] - 1).' days', G5_SERVER_TIME));

        if($point2 > $point1) {
          $sql = "UPDATE {$g5['point_table']}
                  SET po_use_point = po_use_point - ?,
                      po_expired = ?,
                      po_expire_date = ?
                  WHERE po_id = ?";
          $this->sql_query($sql, [$point1, $po_expired, $po_expire_date, $row['po_id']]);
          break;
        } else {
          $sql = "UPDATE {$g5['point_table']}
                  SET po_use_point = ?,
                      po_expired = ?,
                      po_expire_date = ?
                  WHERE po_id = ?";
          $this->sql_query($sql, ['0', $po_expired, $po_expire_date, $row['po_id']]);
          $point1 -= $point2;
        }
      }
  }
  
  // 회원 정보를 얻는다.
  public function get_member($mb_id, $fields='*', $is_cache=false) {
    global $g5;
    $row = $this->sql_fetch("SELECT ? FROM {$g5['member_table']} where mb_id = TRIM(?)", [$fields, $mb_id]);
    return $row;
  }

  // 포인트 내역 합계
  public function get_point_sum($mb_id) {
    global $g5;
    $config = $this->config;
    if($config['cf_point_term'] > 0) {
      // 소멸포인트가 있으면 내역 추가
      $expire_point = $this->get_expire_point($mb_id);
      if($expire_point > 0) {
        $mb = $this->sql_fetch("SELECT mb_point FROM {$g5['member_table']} WHERE mb_id = ?", [$mb_id]);
        $content = '포인트 소멸';
        $rel_table = '@expire';
        $rel_id = $mb_id;
        $rel_action = 'expire'.'-'.uniqid('');
        $point = $expire_point * (-1);
        $po_mb_point = $mb['mb_point'] + $point;
        $po_expire_date = G5_TIME_YMD;
        $po_expired = 1;

        $sql = "INSERT INTO {$g5['point_table']}
                SET mb_id = ?,
                    po_datetime = ?,
                    po_content = ?,
                    po_point = ?,
                    po_use_point = ?,
                    po_mb_point = ?,
                    po_expired = ?,
                    po_expire_date = ?,
                    po_rel_table = ?,
                    po_rel_id = ?,
                    po_rel_action = ?";
        $this->sql_query($sql, [$mb_id, G5_TIME_YMDHIS, addslashes($content), $point, '0', $po_mb_point, $po_expired, $po_expire_date, $rel_table, $rel_id, $rel_action]);
        // 포인트를 사용한 경우 포인트 내역에 사용금액 기록
        if($point < 0) {
          $this->insert_use_point($mb_id, $point);
        }
      }

      // 유효기간이 있을 때 기간이 지난 포인트 expired 체크
      $sql = "UPDATE {$g5['point_table']}
              SET po_expired = ?
              WHERE mb_id = ?
              AND po_expired <> ?
              AND po_expire_date <> ?
              AND po_expire_date < ?";
      $this->sql_query($sql, ['1', $mb_id, '1', '9999-12-32', G5_TIME_YMD]);
    }

    // 포인트합
    $sql = "SELECT sum(po_point) as sum_po_point
            FROM {$g5['point_table']}
            WHERE mb_id = ?";
    $row = $this->sql_fetch($sql, [$mb_id]);

    return $row['sum_po_point'];
  }
  
  // 소멸 포인트
  public function get_expire_point($mb_id) {
    global $g5;
    $config = $this->$config;
    if($config['cf_point_term'] == 0)
      return 0;
    $sql = "SELECT sum(po_point - po_use_point) as sum_point
            from {$g5['point_table']}
            WHERE mb_id = ?
            AND po_expired = '0'
            AND po_expire_date <> '9999-12-31'
            AND po_expire_date < '".G5_TIME_YMD."' ";
    $row = $this->sql_fetch($sql, [$mb_id]);

    return $row['sum_point'];
  }
  
  // 포인트 삭제
  public function delete_point($mb_id, $rel_table, $rel_id, $rel_action) {
    global $g5;

    $result = false;
    if ($rel_table || $rel_id || $rel_action) {
      // 포인트 내역정보
      $sql = "SELECT * FROM {$g5['point_table']}
              WHERE mb_id = ?
              AND po_rel_table = ?
              AND po_rel_id = ?
              AND po_rel_action = ? ";
      $row = $this->sql_fetch($sql, [$mb_id, $rel_table, $rel_id, $rel_action]);

      if($row['po_point'] < 0) {
        $mb_id = $row['mb_id'];
        $po_point = abs($row['po_point']);

        $this->delete_use_point($mb_id, $po_point);
      } else {
        if($row['po_use_point'] > 0) {
          $this->insert_use_point($row['mb_id'], $row['po_use_point'], $row['po_id']);
        }
      }

      $result = $this->sql_query("DELETE from {$g5['point_table']}
                WHERE mb_id = ?
                AND po_rel_table = ?
                AND po_rel_id = ?
                AND po_rel_action = ? ", [$mb_id, $rel_table, $rel_id, $rel_action]);
      // po_mb_point에 반영
      $sql = "UPDATE {$g5['point_table']}
              SET po_mb_point = po_mb_point - ?
              WHERE mb_id = ?
              AND po_id > ?";
      sql_query($sql, [$row['po_point'], $mb_id, $row['po_id']]);

      // 포인트 내역의 합을 구하고
      $sum_point = $this->get_point_sum($mb_id);

      // 포인트 UPDATE
      $sql = "UPDATE {$g5['member_table']} SET mb_point = ? WHERE mb_id = ?";
      $result = $this->sql_qeury($sql, [$sum_point, $mb_id]);
    }
    return $result;
  }


  // 게시판 최신글 캐시 파일 삭제
  public function delete_cache_latest($bo_table) {
    if (!preg_match("/^([A-Za-z0-9_]{1,20})$/", $bo_table)) {
      return;
    }

    g5_delete_cache_by_prefix('latest-'.$bo_table.'-');
  }

  // 게시판 첨부파일 썸네일 삭제
  public function delete_board_thumbnail($bo_table, $file) {
    if(!$bo_table || !$file)
      return;

    $fn = preg_replace("/\.[^\.]+$/i", "", basename($file));
    $files = glob(G5_DATA_PATH.'/file/'.$bo_table.'/thumb-'.$fn.'*');
    if (is_array($files)) {
      foreach ($files as $filename)
        unlink($filename);
    }
  }

  // 에디터 이미지 얻기
  public function get_editor_image($contents, $view=true) {
    if(!$contents)
      return false;

    // $contents 중 img 태그 추출
    if ($view)
      $pattern = "/<img([^>]*)>/iS";
    else
      $pattern = "/<img[^>]*src=[\'\"]?([^>\'\"]+[^>\'\"]+)[\'\"]?[^>]*>/i";
    preg_match_all($pattern, $contents, $matchs);

    return $matchs;
  }

  // 에디터 썸네일 삭제
  public function delete_editor_thumbnail($contents) {
    if(!$contents)
      return;
    
    run_event('delete_editor_thumbnail_before', $contents);

    // $contents 중 img 태그 추출
    $matchs = get_editor_image($contents, false);

    if(!$matchs)
      return;

    for($i=0; $i<count($matchs[1]); $i++) {
      // 이미지 path 구함
      $imgurl = @parse_url($matchs[1][$i]);
      $srcfile = dirname(G5_PATH).$imgurl['path'];
      if(! preg_match('/(\.jpe?g|\.gif|\.png)$/i', $srcfile)) continue;
      $filename = preg_replace("/\.[^\.]+$/i", "", basename($srcfile));
      $filepath = dirname($srcfile);
      $files = glob($filepath.'/thumb-'.$filename.'*');
      if (is_array($files)) {
        foreach($files as $filename)
          unlink($filename);
      }
    }

    run_event('delete_editor_thumbnail_after', $contents, $matchs);
  }

  // 1:1문의 첨부파일 썸네일 삭제
  public function delete_qa_thumbnail($file) {
    if(!$file)
        return;

    $fn = preg_replace("/\.[^\.]+$/i", "", basename($file));
    $files = glob(G5_DATA_PATH.'/qa/thumb-'.$fn.'*');
    if (is_array($files)) {
      foreach ($files as $filename)
        unlink($filename);
    }
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