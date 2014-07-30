<?php

class PollModel
{
    private $db;

    public function __construct(MySQL $db)
    {
        $this->db = $db;
    }

    public function getPollData($id)
    {
        $poll = $this->db->select('val_id, value, count', 'debate_poll')->where('id', '=', $id)->_();
        return $poll;
    }

    public function voteFor($id, $valID)
    {
        return $this->db->exec("UPDATE `debate_poll` SET `count` = `count` + 1 WHERE `id` = $id AND `val_id` = $valID");
    }
}
