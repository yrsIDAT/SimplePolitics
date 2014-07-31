<?php

class Debates
{
    public function __construct($mapper)
    {
        $mapper->map('poll')->setMinParams(1);
        $mapper->map('poll')->when('POSTing')->onto('postPoll');
        $mapper->map('postPoll')->remove();
    }

    public function poll($pollID)
    {
        $poll = $this->models->load('PollModel');
        $data = $poll->getPollData($pollID);
        foreach ($data as $poll) {
            echo $poll['value'] . ' = ' . $poll['count'] . " <a href=\"/debates/pollVote/{$pollID}/{$poll['val_id']}\">Vote</a><br>";
        }
    }

    public function postPoll($pollID)
    {
    
    }

    public function pollVote($pollID, $choiceID)
    {
        $poll = $this->models->load('PollModel');
        if ($poll->voteFor($pollID, $choiceID)) {
            // Success page
            header("Location: /debates/poll/$pollID");
        } else {
            // Could not vote
        }
    }

    public function fromDate($date)
    {
        if ($date === 'today') {
            $date = date("Y-m-d");
        }
        $twfy = $this->libraries->load('TWFYAPI', TWFY_KEY);
        var_dump(json_decode($twfy->query('getDebates', array("date" => $date, "output" => "js", "type" => 'commons'))));
    }

    public function last($last)
    {
        $twfy = $this->libraries->load('TWFYAPI', TWFY_KEY);
        header('Content-Type: application/json');
        echo($twfy->query('getDebates', array("search" => "*", "output" => "js", "type" => 'commons', 'num' => $last)));
    }

    public function summary($num = 3)
    {
        $twfy = $this->libraries->load('TWFYAPI', TWFY_KEY);
        $debates = json_decode($twfy->query('getDebates', array("search" => "*", "output" => "js", "type" => 'commons', 'num' => $num)));
        $summaries = array();
        foreach ($debates->rows as $debate) {
            $summary = array(
                'time' => $debate->hdate . ' ' . $debate->htime,
                'speaker' => $debate->speaker->first_name . ' ' . $debate->speaker->last_name,
                'summary' => $debate->extract,
                'topic' => $debate->parent->body,
                'gid' => $this->getDebateGid($debate->listurl)
            );
            $summaries[] = $summary;
        }
        header('Content-Type: application/json');
        echo json_encode($summaries);
    }

    private function getDebateGid($listURL)
    {
        parse_str(parse_url($listURL)['query'], $query);
        return $query['id'];
    }

    public function full($gid)
    {
        $twfy = $this->libraries->load('TWFYAPI', TWFY_KEY);
        $debate = json_decode($twfy->query('getDebates', array("gid" => $gid, "output" => "js", "type" => 'commons')));
        echo Template::getTemplate('debates:full')->parse(array('debate' => $debate));
    }
}