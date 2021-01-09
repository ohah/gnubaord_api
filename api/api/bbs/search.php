<?php
trait search {
  public function search($bo_table, $wr_id) {
    global $g5;
    $write_table = $g5['write_prefix'].$bo_table;
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
    
    $search_table = Array();
    $table_index = 0;
    $write_pages = "";
    $text_stx = "";
    $srows = 0;

    $stx = strip_tags($stx);
    //$stx = preg_replace('/[[:punct:]]/u', '', $stx); // 특수문자 제거
    $stx = $this->get_search_string($stx); // 특수문자 제거
    if ($stx) {
      $stx = preg_replace('/\//', '\/', trim($stx));
      $sop = strtolower($sop);
      if (!$sop || !($sop == 'and' || $sop == 'or')) $sop = 'and'; // 연산자 and , or
      $srows = isset($_GET['srows']) ? (int)preg_replace('#[^0-9]#', '', $_GET['srows']) : 10;
      if (!$srows) $srows = 10; // 한페이지에 출력하는 검색 행수

      $g5_search['tables'] = Array();
      $g5_search['read_level'] = Array();
      $sql = " select gr_id, bo_table, bo_read_level from {$g5['board_table']} where bo_use_search = 1 and bo_list_level <= '{$member['mb_level']}' ";
      if ($gr_id)
        $sql .= " and gr_id = '{$gr_id}' ";
      $onetable = isset($onetable) ? $onetable : "";
      if ($onetable) // 하나의 게시판만 검색한다면
        $sql .= " and bo_table = '{$onetable}' ";
      $sql .= " order by bo_order, gr_id, bo_table ";
      $result = $this->sql_query($sql);
      for ($i=0; $i<count($result); $i++) {
        $row = $result[$i];
        if ($is_admin != 'super') {
          // 그룹접근 사용에 대한 검색 차단
          $sql2 = " select gr_use_access, gr_admin from {$g5['group_table']} where gr_id = ? ";
          $row2 = $this->sql_fetch($sql2, [$row['gr_id']]);
          // 그룹접근을 사용한다면
          if ($row2['gr_use_access']) {
            // 그룹관리자가 있으며 현재 회원이 그룹관리자라면 통과
            if ($row2['gr_admin'] && $row2['gr_admin'] == $member['mb_id']) {
              ;
            } else {
              $sql3 = " select count(*) as cnt from {$g5['group_member_table']} where gr_id = ? and mb_id = ? and mb_id <> ? ";
              $row3 = $this->sql_fetch($sql3, [$row['gr_id'], $member['mb_id'], '']);
              if (!$row3['cnt'])
                continue;
            }
          }
        }
        $g5_search['tables'][] = $row['bo_table'];
        $g5_search['read_level'][] = $row['bo_read_level'];
      }

      $op1 = '';

      // 검색어를 구분자로 나눈다. 여기서는 공백
      $s = explode(' ', strip_tags($stx));
      
      if( count($s) > 1 ){
        $s = array_slice($s, 0, 2);
        $stx = implode(' ', $s);
      }

      $text_stx = $this->get_text(stripslashes($stx));
      
      $search_query = 'sfl='.urlencode($sfl).'&amp;stx='.urlencode($stx).'&amp;sop='.$sop;

      // 검색필드를 구분자로 나눈다. 여기서는 +
      $field = explode('||', trim($sfl));

      $str = '(';
      for ($i=0; $i<count($s); $i++) {
        if (trim($s[$i]) == '') continue;

        $search_str = $s[$i];

        // 인기검색어
        $this->insert_popular($field, $search_str);

        $str .= $op1;
        $str .= "(";

        $op2 = '';
        // 필드의 수만큼 다중 필드 검색 가능 (필드1+필드2...)
        for ($k=0; $k<count($field); $k++) {
          $str .= $op2;
          switch ($field[$k]) {
            case 'mb_id' :
            case 'wr_name' :
              $str .= "$field[$k] = '$s[$i]'";
              break;
            case 'wr_subject' :
            case 'wr_content' :
              if (preg_match("/[a-zA-Z]/", $search_str))
                $str .= "INSTR(LOWER({$field[$k]}), LOWER('{$search_str}'))";
              else
                $str .= "INSTR({$field[$k]}, '{$search_str}')";
              break;
            default :
              $str .= "1=0"; // 항상 거짓
              break;
          }
          $op2 = " or ";
        }
        $str .= ")";

        $op1 = " {$sop} ";
      }
      $str .= ")";

      $sql_search = $str;

      $str_board_list = array();
      $board_count = 0;

      $time1 = get_microtime();

      $total_count = 0;
      for ($i=0; $i<count($g5_search['tables']); $i++) {
        $tmp_write_table   = $g5['write_prefix'] . $g5_search['tables'][$i];

        $sql = " select wr_id from {$tmp_write_table} where {$sql_search} ";
        $result = $this->sql_query($sql);
        $row['cnt'] = @$this->sql_num_rows($result);

        $total_count += $row['cnt'];
        if ($row['cnt']) {
          $board_count++;
          $search_table[] = $g5_search['tables'][$i];
          $read_level[]   = $g5_search['read_level'][$i];
          $search_table_count[] = $total_count;

          $sql2 = " select bo_subject, bo_mobile_subject from {$g5['board_table']} where bo_table = ?";
          $row2 = $this->sql_fetch($sql2, [$g5_search['tables'][$i]]);
          $sch_class = "";
          $sch_all = "";
          if ($onetable == $g5_search['tables'][$i]) $sch_class = "sch_on";
          else $sch_all = "sch_on";
          $str_board_list[$i]['url'] = $_SERVER['SCRIPT_NAME'].'?'.$search_query.'&amp;gr_id='.$gr_id.'&amp;onetable='.$g5_search['tables'][$i];
          $str_board_list[$i]['bo_subject'] = ($this->is_mobile() && $row2['bo_mobile_subject']) ? $row2['bo_mobile_subject'] : $row2['bo_subject'];
          $str_board_list[$i]['cnt_cmt'] = $row['cnt'];
          $str_board_list[$i]['class'] = $sch_class;

        }
      }

      $rows = $srows;
      $total_page = ceil($total_count / $rows);  // 전체 페이지 계산
      if ($page < 1) { $page = 1; } // 페이지가 없으면 첫 페이지 (1 페이지)
      $from_record = ($page - 1) * $rows; // 시작 열을 구함

      for ($i=0; $i<count($search_table); $i++) {
        if ($from_record < $search_table_count[$i]) {
          $table_index = $i;
          $from_record = $from_record - $search_table_count[$i-1];
          break;
        }
      }

      $bo_subject = array();
      $list = array();

      $k=0;
      for ($idx=$table_index; $idx<count($search_table); $idx++) {
        $sql = " select bo_subject, bo_mobile_subject from {$g5['board_table']} where bo_table = '{$search_table[$idx]}' ";
        $row = sql_fetch($sql);
        $bo_subject[$idx] = ((G5_IS_MOBILE && $row['bo_mobile_subject']) ? $row['bo_mobile_subject'] : $row['bo_subject']);

        $tmp_write_table = $g5['write_prefix'] . $search_table[$idx];

        $sql = " select * from {$tmp_write_table} where {$sql_search} order by wr_id desc limit {$from_record}, {$rows} ";
        $result = sql_query($sql);
        for ($i=0; $row=sql_fetch_array($result); $i++) {
          // 검색어까지 링크되면 게시판 부하가 일어남
          $list[$idx][$i] = $row;
          $list[$idx][$i]['href'] = $this->get_pretty_url($search_table[$idx], $row['wr_parent']);

          if ($row['wr_is_comment'])
          {
              $sql2 = "select wr_subject, wr_option from {$tmp_write_table} where wr_id = ?";
              $row2 = sql_fetch($sql2, [$row['wr_parent']]);
              //$row['wr_subject'] = $row2['wr_subject'];
              $row['wr_subject'] = $this->get_text($row2['wr_subject']);
          }

          // 비밀글은 검색 불가
          if (strstr($row['wr_option'].$row2['wr_option'], 'secret'))
              $row['wr_content'] = '[비밀글 입니다.]';

          $subject = $this->get_text($row['wr_subject']);
          if (strstr($sfl, 'wr_subject'))
            $subject = $this->search_font($stx, $subject);

          if ($read_level[$idx] <= $member['mb_level']) {
            //$content = cut_str($this->get_text(strip_tags($row['wr_content'])), 300, "…");
            $content = strip_tags($row['wr_content']);
            $content = $this->get_text($content, 1);
            $content = strip_tags($content);
            $content = str_replace('&nbsp;', '', $content);
            $content = $this->cut_str($content, 300, "…");

            if (strstr($sfl, 'wr_content'))
              $content = search_font($stx, $content);
          } else{
            $content = '';
          }

          $list[$idx][$i]['subject'] = $subject;
          $list[$idx][$i]['content'] = $content;
          $list[$idx][$i]['name'] = $this->get_sideview($row['mb_id'], $this->get_text($this->cut_str($row['wr_name'], $config['cf_cut_name'])), $row['wr_email'], $row['wr_homepage']);

          $k++;
          if ($k >= $rows)
            break;
        }
        $this->sql_free_result($result);

        if ($k >= $rows)
          break;

        $from_record = 0;
      }

      $write_pages = $this->get_paging($this->is_mobile() ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, $_SERVER['SCRIPT_NAME'].'?'.$search_query.'&amp;gr_id='.$gr_id.'&amp;srows='.$srows.'&amp;onetable='.$onetable.'&amp;page=');
    }

    $k = 0;
    $group_select = array();
    $group_select[0]['name'] = '전체 분류';
    $group_select[0]['value'] = '';
    $group_select = '<label for="gr_id" class="sound_only">게시판 그룹선택</label><select name="gr_id" id="gr_id" class="select"><option value="">전체 분류';
    $sql = " select gr_id, gr_subject from {$g5['group_table']} order by gr_id ";
    $result = $this->sql_query($sql);
    for ($i=0; $i<count($result); $i++) {
      $row = $result[$i];
      $group_select[$k]['name'] = $row['gr_subject'];
      $group_select[$k]['value'] = $row['gr_id'];
      $group_select[$k]['selected'] = $this->get_selected($gr_id, $row['gr_id']);
      $k++;
    }

    if (!$sfl) $sfl = 'wr_subject';
    if (!$sop) $sop = 'or';

    $result = array();
    $result['group_select'] = $group_select;
    $result['write_pages'] = $write_pages;
    $result['list'] = $this->unset_data($list);
    return json_encode($result, JSON_UNESCAPED_UNICODE);
  }
}