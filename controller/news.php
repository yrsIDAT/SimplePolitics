<?php

class News
{
    public function index()
    {
        $reader = $this->libraries->load('rss_php');
        $reader->load("http://www.theguardian.com/politics/rss");
        $items = $reader->getItems();
        foreach ($items as &$item) {
            $item['pubDate'] = date('l, j F H:m:s', strtotime($item['pubDate']));
        }
        echo Template::getTemplate('news:news')->parse(array('news' => $items));
    }
}
