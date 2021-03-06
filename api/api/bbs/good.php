<?php
trait good {
  public function print_result($error, $count) {
    echo '{ "error": "' . $error . '", "count": "' . $count . '" }';
    if($error) {
      exit;
    }
  }
  public function good($bo_table, $wr_id, $good) { 
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

    $error = $count = "";

    if (!$is_member) {
      $error = '회원만 가능합니다.';
      $this->print_result($error, $count);
    }

    if (!($bo_table && $wr_id)) {
      $error = '값이 제대로 넘어오지 않았습니다.';
      $this->print_result($error, $count);
    }

    $ss_name = 'ss_view_'.$bo_table.'_'.$wr_id;
    if (!$this->get_session($ss_name)) {
      $error = '해당 게시물에서만 추천 또는 비추천 하실 수 있습니다.';
      $this->print_result($error, $count);
    }

    $row = $this->sql_fetch(" select count(*) as cnt from {$g5['write_prefix']}{$bo_table} ");
    if (!$row['cnt']) {
      $error = '존재하는 게시판이 아닙니다.';
      $this->print_result($error, $count);
    }

    if ($good == 'good' || $good == 'nogood') {
      if($write['mb_id'] == $member['mb_id']) {
        $error = '자신의 글에는 추천 또는 비추천 하실 수 없습니다.';
        $this->print_result($error, $count);
      }

      if (!$board['bo_use_good'] && $good == 'good') {
        $error = '이 게시판은 추천 기능을 사용하지 않습니다.';
        $this->print_result($error, $count);
      }

      if (!$board['bo_use_nogood'] && $good == 'nogood') {
        $error = '이 게시판은 비추천 기능을 사용하지 않습니다.';
        $this->print_result($error, $count);
      }

      $sql = " select bg_flag from {$g5['board_good_table']}
                  where bo_table = ?
                  and wr_id = ?
                  and mb_id = ?
                  and bg_flag in ('good', 'nogood') ";
      $row = $this->sql_fetch($sql, [$bo_table, $wr_id, $member['mb_id']]);
      if ($row['bg_flag']) {
        if ($row['bg_flag'] == 'good')
          $status = '추천';
        else
          $status = '비추천';

        $error = "이미 $status 하신 글 입니다.";
        $this->print_result($error, $count);
      } else {
        // 추천(찬성), 비추천(반대) 카운트 증가
        $this->sql_query(" update {$g5['write_prefix']}{$bo_table} set wr_{$good} = wr_{$good} + 1 where wr_id = ? ",[$wr_id]);
        // 내역 생성
        $this->sql_query(" insert {$g5['board_good_table']} set bo_table = ?, wr_id = ?, mb_id = ?, bg_flag = ?, bg_datetime = ? ", [$bo_table, $wr_id, $member['mb_id'], $good, G5_TIME_YMDHIS]);

        $sql = " select wr_{$good} as count from {$g5['write_prefix']}{$bo_table} where wr_id = ? ";
        $row = $this->sql_fetch($sql, [$wr_id]);

        $count = $row['count'];
    
        run_event('bbs_increase_good_json', $bo_table, $wr_id, $good);

        $this->print_result($error, $count);
      }
    }
  }
}