<?php

class News
{
    public function index()
    {
        $reader = $this->libraries->load('rss_php');
        $reader->load("http://feeds.bbci.co.uk/news/politics/rss.xml");
        echo Template::getTemplate('news:news')->parse(array('news' => $reader->getItems()));
    }
}
