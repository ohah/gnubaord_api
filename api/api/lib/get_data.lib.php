<?php
if (!defined('_GNUBOARD_')) exit;
trait get_datalib {
  public function get_config() {
    global $g5;
    
    static $cahce = [];

    $cache = run_replace('get_config_cache', $cache, $is_cache);

    if( $is_cache && !empty($cache) ){
      return $cache;
    }

    $cache = run_replace('get_config', $this->sql_fetch("SELECT * FROM {$g5['config_table']}"));
    return $cache;
  }

  public function get_content_db($co_id, $is_cache = false){
    global $g5;
    $g5_object = $this->$g5_object;
    static $cache = array();
    
    $type = 'content';

    $co_id = preg_replace('/[^a-z0-9_]/i', '', $co_id);
    $co = $g5_object->get($type, $co_id, $type);

    if(!$co) {
      $cache_file_name = "{$type}-{$co_id}-".g5_cache_secret_key();
      $co = g5_get_cache($cache_file_name, 10800);
      if( $co === false ){
        $co = $this->sql_fetch("SELECT * FROM {$g5['content_table']} WHERE co_id = ?", [$co_id]);

        g5_set_cache($cache_file_name, $co, 10800);
      }

      $g5_object->set($type, $co_id, $co, $type);
    }
    return $co;
  }

  public function get_board_names(){
    global $g5;
    static $boards = array();
    $boards = run_replace('get_board_names_cache', $boards);
    if(!$boards ){
      $sql = " select bo_table from {$g5['board_table']} ";
      $result = $this->sql_query("SELECT bo_table FROM {$g5['board_table']}");
      for($i=0;$i<count($result);$i++) {
        $boards[] = $result[$i]['bo_table'];
      }
    }
    return $boards;
  }

  public function get_board_db($bo_table){
    global $g5;

    static $cache;

    $bo_table = preg_replace('/[^a-z0-9_]/i', '', $bo_table);
    $cache = run_replace('get_board_db_cache', $cache, $bo_table, $is_cache);
    $key = md5($bo_table);

    if( $is_cache && isset($cache[$key]) ){
      return $cache[$key];
    }

    if( !($cache[$key] = run_replace('get_board_db', array(), $bo_table)) ){
      $board = $this->sql_fetch("SELECT * FROM {$g5['board_table']} WHERE bo_table = ?", [$bo_table]);
      $board_defaults = array('bo_table'=>'', 'bo_skin'=>'', 'bo_mobile_skin'=>'', 'bo_upload_count' => 0, 'bo_use_dhtml_editor'=>'', 'bo_subject'=>'', 'bo_image_width'=>0);
      $cache[$key] = array_merge($board_defaults, (array) $board);
    }
    
    return $cache[$key];
  }

  public function get_menu_db($use_mobile=0, $is_cache=false){
    global $g5;
    static $cache = array();
    $cache = run_replace('get_menu_db_cache', $cache, $use_mobile, $is_cache);
    $key = md5($use_mobile);
    if( $is_cache && isset($cache[$key]) ){
      return $cache[$key];
    }

    $where = $use_mobile ? "me_mobile_use = '1'" : "me_use = '1'";
    if( !($cache[$key] = run_replace('get_menu_db', array(), $use_mobile)) ) {
      $res = $this->sql_query("SELECT * FROM {$g5['menu_table']} WHERE {$where} AND length(me_code) = '2' ORDER By me_order, me_id");      
      for($i=0;$i<count($res);$i++) {
        $res[$i]['ori_me_link'] = $res[$i]['me_link'];
        $res[$i]['me_link'] = $this->short_url_clean($res[$i]['me_link']);
        $res[$i]['sub'] = isset($res[$i]['sub']) ? $res[$i]['sub'] : array();
        $cache[$key][$i] = $res;

        $row2 = $this->sql_query("SELECT * FROM {$g5['menu_table']} WHERE length(me_code) = ? AND substring(me_code, 1,2) = ? ORDER By me_order, me_id", ['4', $res[$i]['me_code']]);
        for($k=0;$k<count($row2);$k++) {
          $row2[$i]['ori_me_link'] = $row2[$i]['me_link'];
          $row2[$i]['me_link'] = $this->short_url_clean($row2[$i]['me_link']);
          $res[$i]['sub'][$k] = $row2[$i];
          $cache[$key][$i]['sub'][$k] = $row2[$i];
        }
      }
    }
    return $cache[$key][0];
  }

  // 게시판 테이블에서 하나의 행을 읽음
  public function get_content_by_field($write_table, $type='bbs', $where_field='', $where_value='', $is_cache=false) {
    global $g5;
    if( $type === 'content' ){
      $check_array = array('co_id', 'co_html', 'co_subject', 'co_content', 'co_seo_title', 'co_mobile_content', 'co_skin', 'co_mobile_skin', 'co_tag_filter_use', 'co_hit', 'co_include_head', 'co_include_tail');
    } else {
      $check_array = array('wr_id', 'wr_num', 'wr_reply', 'wr_parent', 'wr_is_comment', 'ca_name', 'wr_option', 'wr_subject', 'wr_content', 'wr_seo_title', 'wr_link1', 'wr_link2', 'wr_hit', 'wr_good', 'wr_nogood', 'mb_id', 'wr_name', 'wr_email', 'wr_homepage', 'wr_datetime', 'wr_ip', 'wr_1', 'wr_2', 'wr_3', 'wr_4', 'wr_5', 'wr_6', 'wr_7', 'wr_8', 'wr_9', 'wr_10');
    }
    if( ! in_array($where_field, $check_array) ){
      return '';
    }
    return $this->sql_fetch("SELECT * FROM {$write_table} WHERE $where_field = ?", [$where_value]);
  }

  // 게시판 첨부파일 테이블에서 하나의 행을 읽음
  public function get_board_file_db($bo_table, $wr_id, $fields='*', $add_where='', $is_cache=false) {
    global $g5;
    return $this->sql_fetch("SELECT $fields FROM {$g5['board_file_table']}
    WHERE bo_table = ? AND wr_id = ? $add_where ORDER BY bf_no LIMIT 0, 1", [$bo_table, $wr_id]);
  }

  public function get_poll_db($po_id, $is_cache=false){
    global $g5;
    return $this->sql_fetch("SELECT * FROM {$g5['poll_table']} WHERE po_id = ?",[$po_id]);
  }

  public function get_point_db($po_id, $is_cache=false){
    global $g5;
    return $this->sql_fetch("SELECT * FROM {$g5['point_table']} WHERE po_id = ?", [$po_id]);
  }

  public function get_mail_content_db($ma_id, $is_cache=false){
    global $g5;
    return $this->sql_fetch("SELECT * FROM {$g5['mail_table']} WHERE ma_id = ?", [$ma_id]);
  }

  public function get_qacontent_db($qa_id, $is_cache=false){
    global $g5;
    return $this->sql_fetch("SELECT * FROM {$g5['qa_content_table']} WHERE qa_id = ?", [$qa_id]);
  }

  public function get_thumbnail_find_cache($bo_table, $wr_id, $wr_key){

    if( $wr_key === 'content' ){
      $write_table = $g5['write_prefix'].$bo_table;
      return $this->get_write($write_table, $wr_id, true);
    }

    return $this->get_board_file_db($bo_table, $wr_id, 'bf_file, bf_content', "and bf_type between '1' and '3'", true);
  }

  public function get_write_table_name($bo_table){
    global $g5;
    return $g5['write_prefix'].preg_replace('/[^a-z0-9_]/i', '', $bo_table);
  }


  public function get_db_create_replace($sql_str){
    if( in_array(strtolower(G5_DB_ENGINE), array('innodb', 'myisam')) ){
      $sql_str = preg_replace('/ENGINE=MyISAM/', 'ENGINE='.G5_DB_ENGINE, $sql_str);
    } else {
      $sql_str = preg_replace('/ENGINE=MyISAM/', '', $sql_str);
    }
    if( G5_DB_CHARSET !== 'utf8' ){
      $sql_str = preg_replace('/CHARSET=utf8/', 'CHARACTER SET '.get_db_charset(G5_DB_CHARSET), $sql_str);
    }

    return $sql_str;
  }
  
  public function get_class_encrypt(){
    static $cache;
    if( $cache && is_object($cache) ){
      return $cache;
    }
    $cache = run_replace('get_class_encrypt', new str_encrypt());
    return $cache;
  }

  public function get_string_encrypt($str){
    $new = $this->get_class_encrypt();
    $encrypt_str = $new->encrypt($str);
    return $encrypt_str;
  }

  public function get_string_decrypt($str){
    $new = $this->get_class_encrypt();
    $decrypt_str = $new->decrypt($str);
    return $decrypt_str;
  }

  public function get_permission_debug_show(){
    global $member;
    $bool = false;
    if ( defined('G5_DEBUG') && G5_DEBUG ){
      $bool = true;
    }
    return run_replace('get_permission_debug_show', $bool, $member);
  }
  public function get_check_mod_rewrite(){
    if( function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules()) )
      $mod_rewrite = 1;
    elseif( isset($_SERVER['IIS_UrlRewriteModule']) )
      $mod_rewrite = 1;
    else
      $mod_rewrite = 0;
    return $mod_rewrite;
  }

  public function get_mb_icon_name($mb_id){
    if( $icon_name = run_replace('get_mb_icon_name', '', $mb_id) ){
      return $icon_name;
    }
    return $mb_id;
  }

  // 생성되면 안되는 게시판명
  public function get_bo_table_banned_word(){
    $folders = array();
    foreach(glob(G5_PATH.'/*', GLOB_ONLYDIR) as $dir) {
      $folders[] = basename($dir);
    }
    return run_replace('get_bo_table_banned_word', $folders);
  }

  public function get_board_sort_fields($board=array(), $make_key_return=''){
    $bo_sort_fields = run_replace('get_board_sort_fields', array(
        array('wr_num, wr_reply', '기본'),
        array('wr_datetime asc', '날짜 이전것 부터'),
        array('wr_datetime desc', '날짜 최근것 부터'),
        array('wr_hit asc, wr_num, wr_reply', '조회수 낮은것 부터'),
        array('wr_hit desc, wr_num, wr_reply', '조회수 높은것 부터'),
        array('wr_last asc', '최근글 이전것 부터'),
        array('wr_last desc', '최근글 최근것 부터'),
        array('wr_comment asc, wr_num, wr_reply', '댓글수 낮은것 부터'),
        array('wr_comment desc, wr_num, wr_reply', '댓글수 높은것 부터'),
        array('wr_good asc, wr_num, wr_reply', '추천수 낮은것 부터'),
        array('wr_good desc, wr_num, wr_reply', '추천수 높은것 부터'),
        array('wr_nogood asc, wr_num, wr_reply', '비추천수 낮은것 부터'),
        array('wr_nogood desc, wr_num, wr_reply', '비추천수 높은것 부터'),
        array('wr_subject asc, wr_num, wr_reply', '제목 오름차순'),
        array('wr_subject desc, wr_num, wr_reply', '제목 내림차순'),
        array('wr_name asc, wr_num, wr_reply', '글쓴이 오름차순'),
        array('wr_name desc, wr_num, wr_reply', '글쓴이 내림차순'),
        array('ca_name asc, wr_num, wr_reply', '분류명 오름차순'),
        array('ca_name desc, wr_num, wr_reply', '분류명 내림차순'),
    ), $board, $make_key_return);

    if( $make_key_return ){
        
        $returns = array();
        foreach( $bo_sort_fields as $v ){
            $key = preg_replace("/[\<\>\'\"\\\'\\\"\%\=\(\)\/\^\*\s]/", "", $v[0]);
            $returns[$key] = $v[0];
        }
        
        return $returns;
    }
    return $bo_sort_fields;
  }

  public function get_board_sfl_select_options($sfl){

    $is_admin = $this->is_admin;

    $str = '';
    $str .= '<option value="wr_subject" '.get_selected($sfl, 'wr_subject', true).'>제목</option>';
    $str .= '<option value="wr_content" '.get_selected($sfl, 'wr_content').'>내용</option>';
    $str .= '<option value="wr_subject||wr_content" '.get_selected($sfl, 'wr_subject||wr_content').'>제목+내용</option>';
    if ( $is_admin ){
        $str .= '<option value="mb_id,1" '.get_selected($sfl, 'mb_id,1').'>회원아이디</option>';
        $str .= '<option value="mb_id,0" '.get_selected($sfl, 'mb_id,0').'>회원아이디(코)</option>';
    }
    $str .= '<option value="wr_name,1" '.get_selected($sfl, 'wr_name,1').'>글쓴이</option>';
    $str .= '<option value="wr_name,0" '.get_selected($sfl, 'wr_name,0').'>글쓴이(코)</option>';

    return $str;
  }

  // 읽지 않은 메모 갯수 반환
  public function get_memo_not_read($mb_id, $add_where='') {
    global $g5;

    $row = $this->sql_fetch("SELECT count(*) as cnt FROM {$g5['memo_table']} WHERE me_recv_mb_id = ? AND me_type= ? AND me_read_datetime LIKE ? $add_where", [$mb_id, 'recv', '0%']);

    return $row['cnt'];
  }

  public function get_scrap_totals($mb_id=''){
    global $g5;
    $add_where = $mb_id ? " and mb_id = ? " : '';

    $row = $this->sql_fetch("SELECT count(*) as cnt from {$g5['scrap_table']} where 1=1 $add_where", [$mb_id]);

    return $row['cnt'];
  }
}