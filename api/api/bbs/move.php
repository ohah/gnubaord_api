<?php
trait move {
  public function move($sw) {
    global $g5;
    $is_admin = $this->is_admin;
    $member = $this->member;
    if ($sw === 'move')
      $act = '이동';
    else if ($sw === 'copy')
      $act = '복사';
    else
      $this->alert('sw 값이 제대로 넘어오지 않았습니다.');

    // 게시판 관리자 이상 복사, 이동 가능
    if ($is_admin != 'board' && $is_admin != 'group' && $is_admin != 'super')
      $this->alert("게시판 관리자 이상 접근이 가능합니다.");

    $g5['title'] = '게시물 ' . $act;
    $wr_id_list = '';
    if ($wr_id)
      $wr_id_list = $wr_id;
    else {
      $comma = '';

      $count_chk_wr_id = (isset($_POST['chk_wr_id']) && is_array($_POST['chk_wr_id'])) ? count($_POST['chk_wr_id']) : 0;

      for ($i=0; $i<$count_chk_wr_id; $i++) {
        $wr_id_val = isset($_POST['chk_wr_id'][$i]) ? preg_replace('/[^0-9]/', '', $_POST['chk_wr_id'][$i]) : 0;
        $wr_id_list .= $comma . $wr_id_val;
        $comma = ',';
      }
    }

    //$sql = " select * from {$g5['board_table']} a, {$g5['group_table']} b where a.gr_id = b.gr_id and bo_table <> '$bo_table' ";
    // 원본 게시판을 선택 할 수 있도록 함.
    $sql = " select gr_subject, bo_subject, bo_table from {$g5['board_table']} a, {$g5['group_table']} b where a.gr_id = b.gr_id ";
    if ($is_admin == 'group')
      $sql .= " and b.gr_admin = '{$member['mb_id']}' ";
    else if ($is_admin == 'board')
      $sql .= " and a.bo_admin = '{$member['mb_id']}' ";
    $sql .= " order by a.gr_id, a.bo_order, a.bo_table ";
    $list = $this->sql_query($sql);
    $result = array();
    $result['list'] = $list;
    $result['wr_id_list'] = $wr_id_list;
    return $this->data_encode($result);
  }
  public function move_update($sw) {

  }
}