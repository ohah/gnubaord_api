<?php
function set_session($session_name, $value) {
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
function get_session($session_name) {
  return isset($_SESSION[$session_name]) ? $_SESSION[$session_name] : '';
}
// 쿠키변수 생성
function set_cookie($cookie_name, $value, $expire) {
  global $g5;
  setcookie(md5($cookie_name), base64_encode($value), G5_SERVER_TIME + $expire, '/', G5_COOKIE_DOMAIN);
}
// 쿠키변수값 얻음
function get_cookie($cookie_name) {
  $cookie = md5($cookie_name);
  if (array_key_exists($cookie, $_COOKIE))
    return base64_decode($_COOKIE[$cookie]);
  else
    return "";
}
trait write_update{
  public function write_update($bo_table) {
    global $g5;
    if($this->config['cf_captcha'] == 'kcaptcha') {
      require G5_CAPTCHA_PATH.'/kcaptcha.lib.php';
    }else if($this->config['cf_captcha'] == 'recaptcha') {
      require G5_CAPTCHA_PATH.'/recaptcha.class.php';
      require G5_CAPTCHA_PATH.'/recaptcha.user.lib.php';
    }else if($this->config['cf_captcha'] == 'recaptcha_inv') {
      require G5_CAPTCHA_PATH.'/recaptcha.class.php';
      require G5_CAPTCHA_PATH.'/recaptcha.user.lib.php';
    }
    extract($_POST);
    $write_table = $g5['write_prefix'].$bo_table;
    $board = $this->sql_fetch("SELECT count(*) cnt FROM {$g5['board_table']} WHERE bo_table = ?",[$bo_table]); //보드 설정
    if($board['cnt'] == 0) {
      echo $this->msg('존재하지 않는 게시판ID입니다');
      exit;
    }
    $board = $this->sql_fetch("SELECT * FROM {$g5['board_table']} WHERE bo_table = ?",[$bo_table]); //보드 설정    
    
    if($board['bo_use_category']) {
      $ca_name = trim($_POST['ca_name']);
      if(!$ca_name) {
        echo $this->msg('분류를 선택하세요');
        exit;
      } else {
        $categories = array_map('trim', explode("|", $board['bo_category_list'].($this->is_admin ? '|공지' : '')));
        if(!empty($categories) && !in_array($ca_name, $categories)) {
          $msg[] = '분류를 올바르게 입력하세요.';
        }
        if(empty($categories)) {
          $ca_name = '';
        }
      }
    } else {
      $ca_name = '';
    }
    
    $wr_subject = '';
    if (isset($_POST['wr_subject'])) {
      $wr_subject = substr(trim($_POST['wr_subject']),0,255);
      $wr_subject = preg_replace("#[\\\]+$#", "", $wr_subject);
    }
    if ($wr_subject == '') {
      echo $this->msg('제목을 입력하세요.');
      exit;
    }
    $wr_content = '';
    if (isset($_POST['wr_content'])) {
      $wr_content = substr(trim($_POST['wr_content']),0,65536);
      $wr_content = preg_replace("#[\\\]+$#", "", $wr_content);
    }
    if ($wr_content == '') {
      echo $this->msg('내용을 입력하세요.');
      exit;
    }
    $wr_link1 = '';
    if (isset($_POST['wr_link1'])) {
      $wr_link1 = substr($_POST['wr_link1'],0,1000);
      $wr_link1 = trim(strip_tags($wr_link1));
      $wr_link1 = preg_replace("#[\\\]+$#", "", $wr_link1);
    }
    $wr_link2 = '';
    if (isset($_POST['wr_link2'])) {
      $wr_link2 = substr($_POST['wr_link2'],0,1000);
      $wr_link2 = trim(strip_tags($wr_link2));
      $wr_link2 = preg_replace("#[\\\]+$#", "", $wr_link2);
    }
    // 090710
    if (substr_count($wr_content, '&#') > 50) {
      echo $this->msg('내용에 올바르지 않은 코드가 다수 포함되어 있습니다.');
      exit;
    }
    $upload_max_filesize = ini_get('upload_max_filesize');
    if (empty($_POST)) {
      echo $this->msg("파일 또는 글내용의 크기가 서버에서 설정한 값을 넘어 오류가 발생하였습니다.\r\npost_max_size=".ini_get('post_max_size')." , upload_max_filesize=".$upload_max_filesize."\r\n게시판관리자 또는 서버관리자에게 문의 바랍니다.");      
    }

    $notice_array = explode(",", $board['bo_notice']);
    if ($w == 'u' || $w == 'r') {
      $wr = $this->sql_fetch("SELECT * FROM {$write_table} WHERE wr_id = ?", [$wr_id]);
      if (!$wr['wr_id']) {
        $this->msg("글이 존재하지 않습니다.\\n글이 삭제되었거나 이동하였을 수 있습니다.");
        exit;
      }
    }
    // 외부에서 글을 등록할 수 있는 버그가 존재하므로 비밀글은 사용일 경우에만 가능해야 함
    if (!$this->is_admin && !$board['bo_use_secret'] && (stripos($_POST['html'], 'secret') !== false || stripos($_POST['secret'], 'secret') !== false || stripos($_POST['mail'], 'secret') !== false)) {
      $this->msg('비밀글 미사용 게시판 이므로 비밀글로 등록할 수 없습니다.');
      exit;
    }

    $secret = '';
    if (isset($_POST['secret']) && $_POST['secret']) {
      if(preg_match('#secret#', strtolower($_POST['secret']), $matches))
        $secret = $matches[0];
    }

    // 외부에서 글을 등록할 수 있는 버그가 존재하므로 비밀글 무조건 사용일때는 관리자를 제외(공지)하고 무조건 비밀글로 등록
    if (!$this->is_admin && $board['bo_use_secret'] == 2) {
      $secret = 'secret';
    }

    $html = '';
    if (isset($_POST['html']) && $_POST['html']) {
      if(preg_match('#html(1|2)#', strtolower($_POST['html']), $matches))
        $html = $matches[0];
    }

    $mail = '';
    if (isset($_POST['mail']) && $_POST['mail']) {
      if(preg_match('#mail#', strtolower($_POST['mail']), $matches))
        $mail = $matches[0];
    }
    $notice = '';
    if (isset($_POST['notice']) && $_POST['notice']) {
      $notice = $_POST['notice'];
    }
    for ($i=1; $i<=10; $i++) {
      $var = "wr_$i";
      $$var = "";
      if (isset($_POST['wr_'.$i]) && settype($_POST['wr_'.$i], 'string')) {
        $$var = trim($_POST['wr_'.$i]);
      }
    }

    if ($w == '' || $w == 'u') {
      // 외부에서 글을 등록할 수 있는 버그가 존재하므로 공지는 관리자만 등록이 가능해야 함
      if (!$this->is_admin && $notice) {
        echo $this->msg('관리자만 공지할 수 있습니다.');
        exit;
      }
      //회원 자신이 쓴글을 수정할 경우 공지가 풀리는 경우가 있음 
      if($w =='u' && !$is_admin && $board['bo_notice'] && in_array($wr['wr_id'], $notice_array)){
        $notice = 1;
      }
      // 김선용 1.00 : 글쓰기 권한과 수정은 별도로 처리되어야 함
      if($w =='u' && $this->member['mb_id'] && $wr['mb_id'] === $this->member['mb_id']) {
        ;
      } else if ($this->member['mb_level'] < $board['bo_write_level']) {
        //echo $this->msg('글을 쓸 권한이 없습니다.');
        exit;
      }
    } else if ($w == 'r') {
      if (in_array((int)$wr_id, $notice_array)) {
        echo $this->msg('공지에는 답변 할 수 없습니다.');
        exit;
      }
      if ($this->member['mb_level'] < $board['bo_reply_level']) {
        echo $this->msg('글을 답변할 권한이 없습니다.');
        exit;
      }
      // 게시글 배열 참조
      $reply_array = &$wr;
      // 최대 답변은 테이블에 잡아놓은 wr_reply 사이즈만큼만 가능합니다.
      if (strlen($reply_array['wr_reply']) == 10) {
        echo $this->msg("더 이상 답변하실 수 없습니다.\\n답변은 10단계 까지만 가능합니다.");
        exit;
      }
      $reply_len = strlen($reply_array['wr_reply']) + 1;
      if ($board['bo_reply_order']) {
        $begin_reply_char = 'A';
        $end_reply_char = 'Z';
        $reply_number = +1;
        $sql = " select MAX(SUBSTRING(wr_reply, ?, 1)) as reply from ? where wr_num = ? and SUBSTRING(wr_reply, ?, 1) <> '' ";
      } else {
        $begin_reply_char = 'Z';
        $end_reply_char = 'A';
        $reply_number = -1;
        $sql = " select MIN(SUBSTRING(wr_reply, ?, 1)) as reply from ? where wr_num = ? and SUBSTRING(wr_reply, ?, 1) <> '' ";
      }
      if ($reply_array['wr_reply']) $sql .= " and wr_reply like ? ";
      $row = sql_fetch($sql);
      if ($reply_array['wr_reply']) {
        $this->sql_fetch($sql, [$reply_len, $write_table, $reply_array['wr_num'] ,$reply_len, $reply_array['wr_reply'].'%']);
      }else {
        $this->sql_fetch($sql, [$reply_len, $write_table, $reply_array['wr_num'] ,$reply_len]);
      } 
      if (!$row['reply']) {
        $reply_char = $begin_reply_char;
      } else if ($row['reply'] == $end_reply_char) { // A~Z은 26 입니다.
        echo $this->msg("더 이상 답변하실 수 없습니다.\\n답변은 26개 까지만 가능합니다.");
        exit;
      } else {
        $reply_char = chr(ord($row['reply']) + $reply_number);
      }
      $reply = $reply_array['wr_reply'] . $reply_char;
    } else {
      echo $this->msg('w 값이 제대로 넘어오지 않았습니다.');
      exit;
    }
    
    $is_use_captcha = ((($board['bo_use_captcha'] && $w !== 'u') || $this->is_guest) && !$this->is_admin) ? 1 : 0;
    if ($is_use_captcha && !chk_captcha()) {
      echo $this->msg('자동등록방지 숫자가 틀렸습니다.');
      //exit;
    }

    if ($w == '' || $w == 'r') {
      if (isset($_SESSION['ss_datetime'])) {
        if ($_SESSION['ss_datetime'] >= (G5_SERVER_TIME - $config['cf_delay_sec']) && !$this->is_admin) {
          echo $this->msg('너무 빠른 시간내에 게시물을 연속해서 올릴 수 없습니다.');
          exit;
        }
      }
      set_session("ss_datetime", G5_SERVER_TIME);
    }
    if (!isset($_POST['wr_subject']) || !trim($_POST['wr_subject'])) {
      echo $this->msg('제목을 입력하여 주십시오.');
      exit;
    }
    //$wr_seo_title = exist_seo_title_recursive('bbs', generate_seo_title($wr_subject), $write_table, $wr_id);

    if ($w == '' || $w == 'r') {
      if ($this->member['mb_id']) {
        $mb_id = $this->member['mb_id'];
        $wr_name = addslashes($this->clean_xss_tags($board['bo_use_name'] ? $this->member['mb_name'] : $this->member['mb_nick']));
        $wr_password = '';
        $wr_email = addslashes($this->member['mb_email']);
        $wr_homepage = addslashes($this->clean_xss_tags($this->member['mb_homepage']));
      } else {
        $mb_id = '';
        // 비회원의 경우 이름이 누락되는 경우가 있음
        $wr_name = $this->clean_xss_tags(trim($_POST['wr_name']));
        if (!$wr_name) {
          echo $this->msg('이름은 필히 입력하셔야 합니다.');
          //exit;
        }
        $wr_password = $this->get_encrypt_string($wr_password);
        $wr_email = $this->get_email_address(trim($_POST['wr_email']));
        $wr_homepage = $this->clean_xss_tags($wr_homepage);
      }

      if ($w == 'r') {
        // 답변의 원글이 비밀글이라면 비밀번호는 원글과 동일하게 넣는다.
        if ($secret) $wr_password = $wr['wr_password'];
        $wr_id = $wr_id . $reply;
        $wr_num = $write['wr_num'];
        $wr_reply = $reply;
      } else {
        $wr_num = $this->get_next_num($write_table);
        $wr_reply = '';
      }

      echo $sql = "INSERT INTO {$write_table}
      SET wr_num = ?,
           wr_reply = ?,
           wr_comment = ?,
           ca_name = ?,
           wr_option = ?,
           wr_subject = ?,
           wr_content = ?,
           wr_seo_title = ?,
           wr_link1 = ?,
           wr_link2 = ?,
           wr_link1_hit = ?,
           wr_link2_hit = ?,
           wr_hit = ?,
           wr_good = ?,
           wr_nogood = ?,
           mb_id = ?,
           wr_password = ?,
           wr_name = ?,
           wr_email = ?,
           wr_homepage = ?,
           wr_datetime = ?,
           wr_last = ?,
           wr_ip = ?,
           wr_1 = ?,
           wr_2 = ?,
           wr_3 = ?,
           wr_4 = ?,
           wr_5 = ?,
           wr_6 = ?,
           wr_7 = ?,
           wr_8 = ?,
           wr_9 = ?,
           wr_10 = ? ";
           
      $this->sql_query($sql, [$wr_num, $wr_reply, '0', $ca_name, $html.','.$secret.','.$mail, $wr_subject, $wr_content, $wr_seo_title, $wr_link1, $wr_link2, '0','0','0','0','0', $this->member['mb_id'], $wr_password, $wr_name, $wr_email, $wr_homepage, G5_TIME_YMDHIS, G5_TIME_YMDHIS, $_SERVER['REMOTE_ADDR'], $wr_1, $wr_2, $wr_3, $wr_4, $wr_5, $wr_6, $wr_7, $wr_8, $wr_9, $wr_10]);

      echo $wr_id = $this->db->lastInsertId();
      echo "개";
    }
  }
}