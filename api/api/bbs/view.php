<?php
trait view {
  public function get_view($bo_table, $wr_id) {
    global $g5;
    $write_table = $g5['write_prefix'].$bo_table;
    $res = $this->sql_query("SELECT * FROM {$write_table} WHERE wr_id = ?", [$wr_id]);
    return json_encode($this->unset_data($res), JSON_UNESCAPED_UNICODE);
  }
}