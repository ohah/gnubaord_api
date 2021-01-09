<?php 
trait password {
  public function password($w='', $bo_table, $wr_id = '', $comment_id = ''){
    global $g5;
    $sca = $this->$sca;
    $sfl = $this->$sfl;
    $stx = $this->$stx;
    $sst = $this->$sst;
    $sod = $this->$sod;
    $spt = $this->$spt;
    $page = $this->$page;
    $write_table = $g5['write_prefix'].$bo_table;
    $write = $this->get_write($write_table, $wr_id);
    $member = $this->member;
    $config = $this->config;
    $board = $this->get_board_db($bo_table);
    $is_admin = $this->is_admin;
    $is_guest = $this->$is_guest;
    $is_member = $this->is_member;
    $qstr = '';
    foreach ($this->qstr as $key => $value) {
      $qstr .= $key.'='.$value;
    }
    $comment_id = $comment_id ? preg_replace('/[^0-9]/', '',$comment_id) : 0;
    $result = array();
    $result['data'] = array();
    switch ($w) {
      case 'u' :
        $result['action'] = 'write';        
        $result['data']['bo_table'] = $bo_table;
        $result['data']['wr_id'] = $wr_id;
        break;
      case 'd' :
        $this->set_session('ss_delete_token', $token = uniqid(time()));
        $result['action'] = 'bbs_delete';        
        $result['data']['bo_table'] = $bo_table;
        $result['data']['wr_id'] = $wr_id;
        $result['data']['token'] = $token;
        break;
      case 'x' :
        $this->set_session('ss_delete_comment_'.$comment_id.'_token', $token = uniqid(time()));        
        $return_url = short_url_clean(G5_HTTP_BBS_URL.'/board.php?bo_table='.$bo_table.'&amp;wr_id='.$row['wr_parent']);
        $result['action'] = 'bbs_comment_delete';        
        $result['data']['bo_table'] = $bo_table;
        $result['data']['comment_id'] = $comment_id;
        $result['data']['token'] = $token;
        break;
      case 's' :        
        if ($is_admin || ($member['mb_id'] == $write['mb_id'] && $write['mb_id'])) {
          return true;
        } else {
          $result['action'] = 'bbs_view';
          $result['data']['bo_table'] = $bo_table;
          $result['data']['wr_id'] = $wr_id;
        }
        break;
      case 'sc' :
        // 비밀번호 창에서 로그인 하는 경우 관리자 또는 자신의 글이면 바로 글보기로 감
        if ($is_admin || ($member['mb_id'] == $write['mb_id'] && $write['mb_id'])) {
          return true;
        } else {
          $result['action'] = 'bbs_view';
          $result['data']['bo_table'] = $bo_table;
          $result['data']['comment_id'] = $comment_id;
        }
        break;
      default :
        $this->alert('w 값이 제대로 넘어오지 않았습니다.');
    }
    return $result;
  }

  public function password_check($w, $bo_table, $wr_id) { 
    global $g5;
    $wr_password = $_POST['wr_password'] ? $_POST['wr_password'] : '';
    $write_table = $g5['write_prefix'].$bo_table;
    if($w == 's') {
      $wr = $this->get_write($write_table, $wr_id);
      if( !$wr['wr_password'] && $wr['mb_id'] ){
        if ( $mb = $this->get_member($wr['mb_id']) ){
          $wr['wr_password'] = $mb['mb_password'];
        }
      }

      if (!$this->check_password($wr_password, $wr['wr_password'])) {
        run_event('password_is_wrong', 'bbs', $wr, $qstr);
        $this->alert('비밀번호가 틀립니다.');
      }

      // 세션에 아래 정보를 저장. 하위번호는 비밀번호없이 보아야 하기 때문임.
      //$ss_name = 'ss_secret.'_'.$bo_table.'_'.$wr_id';
      $ss_name = 'ss_secret_'.$bo_table.'_'.$wr['wr_num'];
      //set_session("ss_secret", "$bo_table|$wr[wr_num]");
      $this->set_session($ss_name, TRUE);
      return true;
    } else if($w == 'sc') {
      $wr = $this->get_write($write_table, $wr_id);
      if( !$wr['wr_password'] && $wr['mb_id'] ){
        if ( $mb = $this->get_member($wr['mb_id']) ){
          $wr['wr_password'] = $mb['mb_password'];
        }
      }
      if (!$this->check_password($wr_password, $wr['wr_password'])){
        run_event('password_is_wrong', 'bbs', $wr, $qstr);
        $this->alert('비밀번호가 틀립니다.');
      }
      // 세션에 아래 정보를 저장. 하위번호는 비밀번호없이 보아야 하기 때문임.
      $ss_name = 'ss_secret_comment_'.$bo_table.'_'.$wr['wr_id'];
      //set_session("ss_secret", "$bo_table|$wr[wr_num]");
      $this->set_session($ss_name, TRUE);
      return true;
    }else {
      $this->alert('w 값이 제대로 넘어오지 않았습니다.');
    }
  }
}