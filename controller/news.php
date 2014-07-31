<?php

class News
{
    public function index()
    {
        $reader = $this->libraries->load('rss_php');
        $reader->load("http://www.theguardian.com/politics/rss");
        echo Template::getTemplate('news:news')->parse(array('news' => $reader->getItems()));
    }
}
