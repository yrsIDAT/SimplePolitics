<?php

class PollModel
{
    public function __construct($db)
    {
    }

    private function runPollCmd()
    {
        return exec("python PollQuestions.py " . implode(' ', func_get_args()));
    }

    public function vote($id, $question, $choice)
    {
        return $this->runPollCmd('do_vote', $id, $question, $choice) === 'Success';
    }

    public function getResults($id)
    {
        return json_decode($this->runPollCmd('get_results', $id), true);
    }

    public function create($id)
    {
        return $this->runPollCmd('create', $id) === 'Success';
    }

    public function getQuestions()
    {
        return json_decode($this->runPollCmd('get_questions'), true);
    }
}
