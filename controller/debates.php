<?php

class Debates
{
    public function fromDate($date)
    {
        if ($date === 'today') {
            $date = date("Y-m-d");
        }
        $twfy = $this->libraries->load('TWFYAPI', TWFY_KEY);
        $debates = @json_decode($twfy->query('getDebates', array("date" => $date, "output" => "js", "type" => 'commons')));
        $this->listDebates($debates, array('dateQuery' => $date));
    }

    public function last($last)
    {
        $twfy = $this->libraries->load('TWFYAPI', TWFY_KEY);
        $debates = @json_decode($twfy->query('getDebates', array("search" => "*", "output" => "js", "type" => 'commons', 'num' => $last)));
        $this->listDebates($debates, array('numQuery' => $last));
    }

    private function listDebates(stdClass $debates, array $tplData = array())
    {
        $tplData = array_merge(array('numQuery' => 5, 'dateQuery' => 'today'), $tplData);
        $debates = isset($debates->rows) && is_array($debates->rows) ? $debates->rows : array();
        foreach ($debates as &$debate) {
            $debate->gid = $this->getDebateGid($debate->listurl);
            $debate->date = date('l, j F H:m', strtotime($debate->hdate . ' ' . $debate->htime));
        }
        echo Template::getTemplate('debates:list', $tplData)->parse(array(
            'debates' => $debates,
            'empty' => count($debates) === 0,
        ));
    }

    public function summary($num = 3)
    {
        $twfy = $this->libraries->load('TWFYAPI', TWFY_KEY);
        $debates = json_decode($twfy->query('getDebates', array("search" => "*", "output" => "js", "type" => 'commons', 'num' => $num)));
        $summaries = array();
        foreach ($debates->rows as $debate) {
            $summary = array(
                'date' => date('l, j F H:m', strtotime($debate->hdate . ' ' . $debate->htime)),
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
        foreach ($debate as &$row) {
            $row->body = preg_replace('#a href=("|\')/mp/\?m=(\d+)\1#', 'a href=\1/mp/redirectProfile/\2\1', $row->body);
            $row->date = date('l, j F H:m', strtotime($row->hdate . ' ' . $row->htime));
        }
        $poll = $this->models->load('PollModel');
        $poll->create($gid);
        $questions = $poll->getQuestions();
        $nQuestions = array();
        foreach ($questions as $q => $opts) {
            list($num, $q) = explode(':', $q);
            $options = array();
            foreach ($opts as $cNum => $opt) {
                list($color, $text) = explode(':', $opt);
                $options[] = array('text' => $text, 'color' => $color, 'choice' => $cNum);
            }
            $nQuestions[((int) $num) - 1] = array('question' => $q, 'options' => $options);
        }
        ksort($nQuestions);
        echo Template::getTemplate('debates:full')->parse(array('debate' => $debate, 'questions' => $nQuestions, 'id' => $gid));
    }

    public function pollVote($debateId, $question, $choice)
    {
        if ($this->models->load('PollModel')->vote($debateId, $question, $choice)) {
            echo 'Vote successful';
        } else {
            http_response_code(500);
            echo 'Unable to submit vote';
        }
    }

    private function getPollResults($poll, $debateId)
    {
        $results = $poll->getResults($debateId);
        $questions = $poll->getQuestions();
        $output = array();
        foreach ($questions as $question => $options) {
            list($i, $question) = explode(':', $question);
            $i = ((int) $i) - 1;
            $opts = array();
            foreach ($options as $j => $option) {
                $opts[] = array('choice' => explode(':', $option)[1], 'count' => $results[$i][$j]);
            }
            $output[$i] = array('question' => $question, 'options' => $opts);
        }
        ksort($output);
        return $output;
    }

    public function pollResults($debateId)
    {
        $output = $this->getPollResults($this->models->load('PollModel'), $debateId);
        header('Content-Type: application/json');
        echo json_encode($output);
    }

    public function pollQuestions()
    {
        echo json_encode($this->models->load('PollModel')->getQuestions());
    }
}